<?php

namespace App\Services\Agents;

use App\Enums\AgentCommissionStatus;
use App\Enums\AgentStatus;
use App\Enums\AgentTerminalStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentTerminal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogService;
use App\Services\Wallet\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AgentTransactionService
{
    public const COMMISSION_RATE = 0.008;

    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(Agent $agent): array
    {
        $agent->load(['terminals' => fn ($q) => $q->orderBy('serial_number')]);

        return [
            'agent' => [
                'id' => $agent->id,
                'code' => $agent->code,
                'business_name' => $agent->business_name,
                'proprietor_name' => $agent->proprietor_name,
                'location' => $agent->location,
                'status' => $agent->status->value,
                'float_balance' => (float) $agent->float_balance,
                'tier' => $agent->tier->value,
            ],
            'terminals' => $agent->terminals->map(fn (AgentTerminal $terminal) => [
                'id' => $terminal->id,
                'serial_number' => $terminal->serial_number,
                'model' => $terminal->model,
                'status' => $terminal->status->value,
                'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
            ])->values()->all(),
            'demo_accounts' => $this->demoAccounts(),
        ];
    }

    /**
     * @return array{account_number: string, account_name: string, bank_name: string, balance: float}
     */
    public function resolveAccount(string $rawAccount): array
    {
        $accountNumber = preg_replace('/\D/', '', $rawAccount) ?? '';

        if (strlen($accountNumber) !== 10) {
            throw new InvalidArgumentException('Enter a valid 10-digit wallet account number.');
        }

        $wallet = Wallet::query()
            ->with('user')
            ->where('account_number', $accountNumber)
            ->where('status', WalletStatus::Active)
            ->first();

        if (! $wallet?->user) {
            throw new InvalidArgumentException('Account not found. Check the wallet number and try again.');
        }

        $name = trim("{$wallet->user->firstname} {$wallet->user->lastname}");

        return [
            'account_number' => $wallet->account_number,
            'account_name' => strtoupper($name),
            'bank_name' => config('app.wallet_bank_name', 'Xpress MFB'),
            'balance' => (float) $wallet->balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cashIn(
        Agent $agent,
        ?int $terminalId,
        string $customerAccount,
        float $amount,
        User $actor,
        ?string $remark = null,
    ): array {
        $this->assertActiveAgent($agent);
        $this->assertMinAmount($amount);

        $resolved = $this->resolveAccount($customerAccount);
        $terminal = $this->resolveTerminal($agent, $terminalId);

        return DB::transaction(function () use ($agent, $terminal, $resolved, $amount, $actor, $remark) {
            $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);

            if ((float) $lockedAgent->float_balance < $amount) {
                throw new InvalidArgumentException('Insufficient agent float for this cash-in.');
            }

            $wallet = Wallet::query()
                ->where('account_number', $resolved['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAgent->decrement('float_balance', $amount);
            $wallet->increment('balance', $amount);

            $sessionId = $this->sessionId();
            $reference = $this->reference();
            $commission = $this->commissionFor($amount);

            $transaction = Transaction::query()->create([
                'reference' => $reference,
                'session_id' => $sessionId,
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'agent_id' => $lockedAgent->id,
                'type' => TransactionType::CashIn,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => strtoupper($lockedAgent->business_name),
                'counterparty_account' => $lockedAgent->code,
                'counterparty_bank' => config('app.wallet_bank_name', 'Xpress MFB'),
                'narrative' => $remark ?: "Agent cash-in via {$lockedAgent->code}",
                'meta' => $this->terminalMeta($lockedAgent, $terminal, 'cash_in'),
            ]);

            $this->accrueCommission($lockedAgent, $amount, $commission);
            $this->touchTerminal($terminal);
            $this->audit($actor, 'agent.simulate.cash_in', $reference, $amount, $lockedAgent);

            $resolved['balance'] = (float) $wallet->fresh()->balance;

            return $this->receipt($transaction, $lockedAgent, $resolved, $commission, (float) $lockedAgent->fresh()->float_balance);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function cashOut(
        Agent $agent,
        ?int $terminalId,
        string $customerAccount,
        float $amount,
        User $actor,
        ?string $remark = null,
    ): array {
        $this->assertActiveAgent($agent);
        $this->assertMinAmount($amount);

        $resolved = $this->resolveAccount($customerAccount);
        $terminal = $this->resolveTerminal($agent, $terminalId);

        return DB::transaction(function () use ($agent, $terminal, $resolved, $amount, $actor, $remark) {
            $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);

            $wallet = Wallet::query()
                ->where('account_number', $resolved['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $wallet->balance < $amount) {
                throw new InvalidArgumentException('Insufficient customer wallet balance for cash-out.');
            }

            $wallet->decrement('balance', $amount);
            $lockedAgent->increment('float_balance', $amount);

            $sessionId = $this->sessionId();
            $reference = $this->reference();
            $commission = $this->commissionFor($amount);

            $transaction = Transaction::query()->create([
                'reference' => $reference,
                'session_id' => $sessionId,
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'agent_id' => $lockedAgent->id,
                'type' => TransactionType::CashOut,
                'direction' => TransactionDirection::Debit,
                'amount' => $amount,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => strtoupper($lockedAgent->business_name),
                'counterparty_account' => $lockedAgent->code,
                'counterparty_bank' => config('app.wallet_bank_name', 'Xpress MFB'),
                'narrative' => $remark ?: "Agent cash-out via {$lockedAgent->code}",
                'meta' => $this->terminalMeta($lockedAgent, $terminal, 'cash_out'),
            ]);

            $this->accrueCommission($lockedAgent, $amount, $commission);
            $this->touchTerminal($terminal);
            $this->audit($actor, 'agent.simulate.cash_out', $reference, $amount, $lockedAgent);

            $resolved['balance'] = (float) $wallet->fresh()->balance;

            return $this->receipt($transaction, $lockedAgent, $resolved, $commission, (float) $lockedAgent->fresh()->float_balance);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function walletTransfer(
        Agent $agent,
        ?int $terminalId,
        string $fromAccount,
        string $toAccount,
        float $amount,
        User $actor,
        ?string $remark = null,
    ): array {
        $this->assertActiveAgent($agent);
        $this->assertMinAmount($amount);

        $from = $this->resolveAccount($fromAccount);
        $to = $this->resolveAccount($toAccount);
        $terminal = $this->resolveTerminal($agent, $terminalId);

        if ($from['account_number'] === $to['account_number']) {
            throw new InvalidArgumentException('Sender and recipient accounts must be different.');
        }

        $fee = TransactionService::TRANSFER_FEE;
        $totalDebit = $amount + $fee;

        return DB::transaction(function () use ($agent, $terminal, $from, $to, $amount, $fee, $totalDebit, $actor, $remark) {
            $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);

            $senderWallet = Wallet::query()
                ->where('account_number', $from['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            $recipientWallet = Wallet::query()
                ->where('account_number', $to['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $senderWallet->balance < $totalDebit) {
                throw new InvalidArgumentException('Insufficient sender balance for this transfer.');
            }

            $sessionId = $this->sessionId();
            $debitRef = $this->reference();
            $creditRef = $this->reference();
            $feeRef = $this->reference();
            $commission = $this->commissionFor($amount);
            $metaBase = $this->terminalMeta($lockedAgent, $terminal, 'wallet_transfer');

            $senderWallet->decrement('balance', $totalDebit);
            $recipientWallet->increment('balance', $amount);

            $debit = Transaction::query()->create([
                'reference' => $debitRef,
                'session_id' => $sessionId,
                'user_id' => $senderWallet->user_id,
                'wallet_id' => $senderWallet->id,
                'agent_id' => $lockedAgent->id,
                'type' => TransactionType::WalletTransferOut,
                'direction' => TransactionDirection::Debit,
                'amount' => $amount,
                'fee' => $fee,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => $to['account_name'],
                'counterparty_account' => $to['account_number'],
                'counterparty_bank' => $to['bank_name'],
                'narrative' => $remark ?: "Agent-assisted transfer to {$to['account_name']}",
                'meta' => array_merge($metaBase, [
                    'from_name' => $from['account_name'],
                    'from_account' => $from['account_number'],
                ]),
            ]);

            $credit = Transaction::query()->create([
                'reference' => $creditRef,
                'session_id' => $sessionId,
                'user_id' => $recipientWallet->user_id,
                'wallet_id' => $recipientWallet->id,
                'agent_id' => $lockedAgent->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => $from['account_name'],
                'counterparty_account' => $from['account_number'],
                'counterparty_bank' => $from['bank_name'],
                'narrative' => "Agent-assisted transfer from {$from['account_name']}",
                'linked_transaction_id' => $debit->id,
                'meta' => array_merge($metaBase, [
                    'from_name' => $from['account_name'],
                    'from_account' => $from['account_number'],
                ]),
            ]);

            $debit->update(['linked_transaction_id' => $credit->id]);

            Transaction::query()->create([
                'reference' => $feeRef,
                'session_id' => $sessionId,
                'user_id' => $senderWallet->user_id,
                'wallet_id' => $senderWallet->id,
                'agent_id' => $lockedAgent->id,
                'type' => TransactionType::TransferFee,
                'direction' => TransactionDirection::Debit,
                'amount' => $fee,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => 'Transfer fee',
                'counterparty_bank' => $from['bank_name'],
                'narrative' => 'Wallet transfer fee',
                'linked_transaction_id' => $debit->id,
                'meta' => $metaBase,
            ]);

            $this->accrueCommission($lockedAgent, $amount, $commission);
            $this->touchTerminal($terminal);
            $this->audit($actor, 'agent.simulate.wallet_transfer', $debitRef, $amount, $lockedAgent);

            return [
                'transaction_reference' => $debitRef,
                'session_id' => $sessionId,
                'type' => 'wallet_transfer',
                'amount' => $amount,
                'fee' => $fee,
                'commission' => $commission,
                'agent' => [
                    'code' => $lockedAgent->code,
                    'float_balance' => (float) $lockedAgent->float_balance,
                ],
                'sender' => $from,
                'recipient' => $to,
                'sender_balance' => (float) $senderWallet->fresh()->balance,
                'recipient_balance' => (float) $recipientWallet->fresh()->balance,
                'occurred_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return list<array{account_number: string, account_name: string, balance: float}>
     */
    private function demoAccounts(): array
    {
        return Wallet::query()
            ->with('user:id,firstname,lastname')
            ->where('status', WalletStatus::Active)
            ->orderBy('account_number')
            ->limit(8)
            ->get()
            ->map(fn (Wallet $wallet) => [
                'account_number' => $wallet->account_number,
                'account_name' => strtoupper(trim("{$wallet->user?->firstname} {$wallet->user?->lastname}")),
                'balance' => (float) $wallet->balance,
            ])
            ->values()
            ->all();
    }

    private function assertActiveAgent(Agent $agent): void
    {
        if ($agent->status !== AgentStatus::Active) {
            throw new InvalidArgumentException('Selected agent is not active.');
        }
    }

    private function assertMinAmount(float $amount): void
    {
        if ($amount < TransactionService::MIN_TRANSFER) {
            throw new InvalidArgumentException('Minimum transaction amount is ₦'.number_format(TransactionService::MIN_TRANSFER, 0));
        }
    }

    private function resolveTerminal(Agent $agent, ?int $terminalId): ?AgentTerminal
    {
        if (! $terminalId) {
            return null;
        }

        $terminal = AgentTerminal::query()
            ->where('id', $terminalId)
            ->where('agent_id', $agent->id)
            ->first();

        if (! $terminal) {
            throw new InvalidArgumentException('Terminal not found for this agent.');
        }

        if ($terminal->status !== AgentTerminalStatus::Active) {
            throw new InvalidArgumentException('Selected terminal is not active.');
        }

        return $terminal;
    }

    /**
     * @return array<string, mixed>
     */
    private function terminalMeta(Agent $agent, ?AgentTerminal $terminal, string $operation): array
    {
        return [
            'channel' => 'pos',
            'simulator' => true,
            'operation' => $operation,
            'agent_code' => $agent->code,
            'terminal_id' => $terminal?->id,
            'terminal_serial' => $terminal?->serial_number,
        ];
    }

    private function sessionId(): string
    {
        return now()->format('YmdHis').Str::upper(Str::random(8));
    }

    private function reference(): string
    {
        return 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));
    }

    private function commissionFor(float $amount): float
    {
        return round($amount * self::COMMISSION_RATE, 2);
    }

    private function accrueCommission(Agent $agent, float $volume, float $commission): void
    {
        $period = now()->format('Y-m');

        $row = AgentCommission::query()->firstOrNew([
            'agent_id' => $agent->id,
            'period' => $period,
        ]);

        if (! $row->exists) {
            $row->status = AgentCommissionStatus::Accrued;
            $row->gross_volume = 0;
            $row->commission_amount = 0;
        }

        $row->gross_volume = (float) $row->gross_volume + $volume;
        $row->commission_amount = (float) $row->commission_amount + $commission;
        $row->save();
    }

    private function touchTerminal(?AgentTerminal $terminal): void
    {
        if ($terminal) {
            $terminal->update(['last_seen_at' => now()]);
        }
    }

    private function audit(User $actor, string $action, string $reference, float $amount, Agent $agent): void
    {
        $this->auditLog->record(
            $actor,
            $action,
            'Transaction',
            $reference,
            "Agent terminal simulation: {$action} ₦".number_format($amount, 2)." via {$agent->code}",
            [
                'reference' => $reference,
                'agent_id' => $agent->id,
                'amount' => $amount,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function receipt(
        Transaction $transaction,
        Agent $agent,
        array $resolved,
        float $commission,
        float $floatBalance,
    ): array {
        return [
            'transaction_reference' => $transaction->reference,
            'session_id' => $transaction->session_id,
            'type' => $transaction->type->value,
            'amount' => (float) $transaction->amount,
            'fee' => (float) $transaction->fee,
            'commission' => $commission,
            'agent' => [
                'code' => $agent->code,
                'float_balance' => $floatBalance,
            ],
            'customer' => $resolved,
            'customer_balance' => $resolved['balance'] ?? null,
            'occurred_at' => $transaction->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
