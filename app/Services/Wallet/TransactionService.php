<?php

namespace App\Services\Wallet;

use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\PinService;
use App\Services\Treasury\FeeScheduleService;
use App\Services\Wallet\TierLimitService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    /** @deprecated Use FeeScheduleService::DEFAULT_TRANSFER_FEE */
    public const TRANSFER_FEE = FeeScheduleService::DEFAULT_TRANSFER_FEE;

    public const MIN_TRANSFER = 100.0;

    public function __construct(
        private WalletService $walletService,
        private PinService $pinService,
        private AuditLogService $auditLog,
        private TierLimitService $tierLimitService,
        private WalletRestrictionService $walletRestrictionService,
        private FeeScheduleService $feeScheduleService,
    ) {}

    public function transferFee(): float
    {
        return $this->feeScheduleService->walletTransferFee();
    }

    /**
     * @return array{account_number: string, account_name: string, bank_name: string}
     */
    public function resolveWalletAccount(User $sender, string $rawAccount): array
    {
        $accountNumber = preg_replace('/\D/', '', $rawAccount) ?? '';

        if (strlen($accountNumber) !== 10) {
            throw new InvalidArgumentException('Enter a valid 10-digit wallet account number.');
        }

        $senderWallet = $this->walletService->getNgnBalance($sender);

        if ($senderWallet->account_number === $accountNumber) {
            throw new InvalidArgumentException('You cannot transfer to your own wallet.');
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transfer(
        User $sender,
        string $toAccount,
        float $amount,
        string $pin,
        ?string $remark = null,
    ): array {
        if ($amount < self::MIN_TRANSFER) {
            throw new InvalidArgumentException('Minimum transfer amount is ₦'.number_format(self::MIN_TRANSFER, 0));
        }

        $this->tierLimitService->assertTransferAllowed($sender, $amount);

        $resolved = $this->resolveWalletAccount($sender, $toAccount);
        $this->pinService->verify($sender, $pin);

        $transferFee = $this->transferFee();

        return DB::transaction(function () use ($sender, $resolved, $amount, $remark, $transferFee) {
            $senderWallet = Wallet::query()
                ->where('user_id', $sender->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletRestrictionService->assertDebitAllowed($senderWallet);

            $recipientWallet = Wallet::query()
                ->where('account_number', $resolved['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            $totalDebit = $amount + $transferFee;

            if ((float) $senderWallet->balance < $totalDebit) {
                throw new InvalidArgumentException('Insufficient wallet balance for this transfer.');
            }

            $sessionId = now()->format('YmdHis').Str::upper(Str::random(8));
            $debitRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));
            $creditRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));
            $feeRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));

            $recipient = $recipientWallet->user;
            $senderName = trim("{$sender->firstname} {$sender->lastname}");
            $recipientName = $resolved['account_name'];
            $bank = $resolved['bank_name'];

            $senderWallet->decrement('balance', $totalDebit);
            $recipientWallet->increment('balance', $amount);

            $debit = Transaction::query()->create([
                'reference' => $debitRef,
                'session_id' => $sessionId,
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'type' => TransactionType::WalletTransferOut,
                'direction' => TransactionDirection::Debit,
                'amount' => $amount,
                'fee' => $transferFee,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => $recipientName,
                'counterparty_account' => $recipientWallet->account_number,
                'counterparty_bank' => $bank,
                'narrative' => $remark ?: "Transfer to {$recipientName}",
                'meta' => [
                    'from_name' => $senderName,
                    'from_account' => $senderWallet->account_number,
                    'from_bank' => $bank,
                ],
            ]);

            $credit = Transaction::query()->create([
                'reference' => $creditRef,
                'session_id' => $sessionId,
                'user_id' => $recipient->id,
                'wallet_id' => $recipientWallet->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => strtoupper($senderName),
                'counterparty_account' => $senderWallet->account_number,
                'counterparty_bank' => $bank,
                'narrative' => "Transfer from ".strtoupper($senderName),
                'linked_transaction_id' => $debit->id,
                'meta' => [
                    'from_name' => strtoupper($senderName),
                    'from_account' => $senderWallet->account_number,
                    'from_bank' => $bank,
                ],
            ]);

            $debit->update(['linked_transaction_id' => $credit->id]);

            if ($recipient) {
                $this->walletRestrictionService->checkBalanceLimitAfterCredit(
                    $recipient->fresh(),
                    $recipientWallet->fresh(),
                );
            }

            Transaction::query()->create([
                'reference' => $feeRef,
                'session_id' => $sessionId,
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'type' => TransactionType::TransferFee,
                'direction' => TransactionDirection::Debit,
                'amount' => $transferFee,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => 'Transfer fee',
                'counterparty_bank' => $bank,
                'narrative' => 'Wallet transfer fee',
                'linked_transaction_id' => $debit->id,
            ]);

            return [
                'transaction_reference' => $debitRef,
                'session_id' => $sessionId,
                'amount' => $amount,
                'fee' => $transferFee,
                'recipient' => $resolved,
                'balance' => (float) $senderWallet->fresh()->balance,
            ];
        });
    }

    public function listForUser(User $user, ?string $search = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->userTransactionsQuery($user->id, $search)
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $reference): Transaction
    {
        return $this->userTransactionsQuery($user->id)
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function adminList(?string $search = null, ?string $status = null, int $perPage = 40): LengthAwarePaginator
    {
        return $this->adminQuery($search, $status)
            ->paginate($perPage);
    }

    public function adminFind(string $reference): Transaction
    {
        return Transaction::query()
            ->with([
                'user:id,firstname,lastname,email,phone',
                'wallet:id,account_number,balance,currency,status',
                'agent:id,code,business_name,proprietor_name,location,status',
                'linkedTransaction',
            ])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function linkedTransactionsFor(Transaction $transaction): Collection
    {
        return Transaction::query()
            ->with(['user:id,firstname,lastname,email', 'wallet:id,account_number'])
            ->where('id', '!=', $transaction->id)
            ->where(function (Builder $q) use ($transaction) {
                $q->where('linked_transaction_id', $transaction->id)
                    ->orWhere('id', $transaction->linked_transaction_id)
                    ->orWhere(function (Builder $inner) use ($transaction) {
                        if ($transaction->session_id) {
                            $inner->where('session_id', $transaction->session_id);
                        }
                    });
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Builder<Transaction>
     */
    public function adminQuery(?string $search = null, ?string $status = null): Builder
    {
        $query = Transaction::query()
            ->with(['user:id,firstname,lastname,email,phone', 'wallet:id,account_number'])
            ->orderByDesc('created_at');

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', $term)
                    ->orWhere('session_id', 'like', $term)
                    ->orWhere('counterparty_name', 'like', $term)
                    ->orWhere('counterparty_account', 'like', $term)
                    ->orWhere('narrative', 'like', $term);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function retryFailed(Transaction $transaction, User $actor, ?string $note = null): Transaction
    {
        if ($transaction->status !== TransactionStatus::Failed) {
            throw new InvalidArgumentException('Only failed transactions can be retried.');
        }

        $meta = $transaction->meta ?? [];
        $meta['retry'] = [
            'retried_at' => now()->toIso8601String(),
            'retried_by' => $actor->email,
            'previous_status' => TransactionStatus::Failed->value,
            'note' => $note,
        ];

        $transaction->update([
            'status' => TransactionStatus::Pending,
            'meta' => $meta,
        ]);

        $meta['retry']['completed_at'] = now()->toIso8601String();
        $transaction->update([
            'status' => TransactionStatus::Success,
            'meta' => $meta,
        ]);

        $this->auditLog->record(
            $actor,
            'transaction.retried',
            'Transaction',
            $transaction->reference,
            "Retried failed transaction {$transaction->reference}",
            [
                'reference' => $transaction->reference,
                'note' => $note,
            ],
        );

        return $transaction->fresh([
            'user:id,firstname,lastname,email,phone',
            'wallet:id,account_number',
            'agent:id,code,business_name',
        ]);
    }

    public function resolve(
        Transaction $transaction,
        User $actor,
        TransactionStatus $status,
        ?string $notes = null,
    ): Transaction {
        if (! in_array($transaction->status, [TransactionStatus::Failed, TransactionStatus::Pending], true)) {
            throw new InvalidArgumentException('Only failed or pending transactions can be resolved.');
        }

        if (! in_array($status, [TransactionStatus::Success, TransactionStatus::Failed], true)) {
            throw new InvalidArgumentException('Resolution status must be success or failed.');
        }

        $meta = $transaction->meta ?? [];
        $meta['resolution'] = [
            'resolved_at' => now()->toIso8601String(),
            'resolved_by' => $actor->email,
            'previous_status' => $transaction->status->value,
            'notes' => $notes,
        ];

        $transaction->update([
            'status' => $status,
            'meta' => $meta,
        ]);

        $this->auditLog->record(
            $actor,
            'transaction.resolved',
            'Transaction',
            $transaction->reference,
            "Resolved transaction {$transaction->reference} as {$status->value}",
            [
                'reference' => $transaction->reference,
                'status' => $status->value,
                'notes' => $notes,
            ],
        );

        return $transaction->fresh([
            'user:id,firstname,lastname,email,phone',
            'wallet:id,account_number',
            'agent:id,code,business_name',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForAdmin(Transaction $tx, bool $includeLinked = false): array
    {
        $payload = [
            'id' => $tx->reference,
            'reference' => $tx->reference,
            'session_id' => $tx->session_id,
            'type' => $tx->type->value,
            'direction' => $tx->direction->value,
            'amount' => (float) $tx->amount,
            'fee' => (float) $tx->fee,
            'currency' => $tx->currency,
            'status' => $tx->status->value,
            'counterparty_name' => $tx->counterparty_name,
            'counterparty_account' => $tx->counterparty_account,
            'counterparty_bank' => $tx->counterparty_bank,
            'narrative' => $tx->narrative,
            'meta' => $tx->meta,
            'occurred_at' => $tx->created_at?->toIso8601String(),
            'created_at' => $tx->created_at?->toIso8601String(),
            'updated_at' => $tx->updated_at?->toIso8601String(),
            'user' => $tx->user ? [
                'id' => $tx->user->id,
                'name' => trim("{$tx->user->firstname} {$tx->user->lastname}"),
                'email' => $tx->user->email,
                'phone' => $tx->user->phone,
            ] : null,
            'wallet' => $tx->wallet ? [
                'id' => $tx->wallet->id,
                'account_number' => $tx->wallet->account_number,
                'balance' => isset($tx->wallet->balance) ? (float) $tx->wallet->balance : null,
                'currency' => $tx->wallet->currency ?? null,
                'status' => $tx->wallet->status instanceof \BackedEnum
                    ? $tx->wallet->status->value
                    : $tx->wallet->status,
            ] : null,
            'agent' => $tx->agent_id && $tx->relationLoaded('agent') && $tx->agent ? [
                'id' => $tx->agent->id,
                'code' => $tx->agent->code,
                'business_name' => $tx->agent->business_name,
                'proprietor_name' => $tx->agent->proprietor_name,
                'location' => $tx->agent->location,
                'status' => $tx->agent->status->value,
            ] : null,
            'linked_transaction_id' => $tx->linked_transaction_id,
        ];

        if ($includeLinked) {
            $linked = $this->linkedTransactionsFor($tx);
            $payload['linked_transactions'] = $linked
                ->map(fn (Transaction $linkedTx) => $this->formatForAdmin($linkedTx))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, int|float>
     */
    public function adminStats(): array
    {
        $today = now()->startOfDay();

        $todayQuery = Transaction::query()->where('created_at', '>=', $today);

        return [
            'total_today' => (int) $todayQuery->count(),
            'volume_today' => (float) $todayQuery->sum('amount'),
            'successful_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Success)->count(),
            'failed_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Failed)->count(),
            'pending_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Pending)->count(),
            'wallet_transfers_today' => (int) (clone $todayQuery)
                ->whereIn('type', [
                    TransactionType::WalletTransferOut,
                    TransactionType::WalletTransferIn,
                ])
                ->count(),
        ];
    }

    private function userTransactionsQuery(int $userId, ?string $search = null): Builder
    {
        $query = Transaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', $term)
                    ->orWhere('counterparty_name', 'like', $term)
                    ->orWhere('narrative', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForMobile(Transaction $tx, User $user): array
    {
        $wallet = $user->wallet;
        $meta = $tx->meta ?? [];
        $isCredit = $tx->direction === TransactionDirection::Credit;

        $fromName = $isCredit
            ? ($tx->counterparty_name ?? '')
            : ($meta['from_name'] ?? trim("{$user->firstname} {$user->lastname}"));
        $fromAccount = $isCredit
            ? ($tx->counterparty_account ?? '')
            : ($meta['from_account'] ?? $wallet?->account_number ?? '');
        $toName = $isCredit
            ? (trim("{$user->firstname} {$user->lastname}"))
            : ($tx->counterparty_name ?? '');
        $toAccount = $isCredit
            ? ($wallet?->account_number ?? '')
            : ($tx->counterparty_account ?? '');

        return [
            'id' => $tx->reference,
            'reference' => $tx->reference,
            'session_id' => $tx->session_id,
            'title' => $this->titleFor($tx),
            'amount' => (float) $tx->amount,
            'fee' => (float) $tx->fee,
            'is_credit' => $isCredit,
            'status' => $tx->status->value,
            'type' => $tx->type->value,
            'occurred_at' => $tx->created_at?->toIso8601String(),
            'from_name' => $fromName,
            'from_account' => $fromAccount,
            'from_bank' => $meta['from_bank'] ?? config('app.wallet_bank_name', 'Xpress MFB'),
            'to_name' => $toName,
            'to_account' => $toAccount,
            'to_bank' => $tx->counterparty_bank ?? config('app.wallet_bank_name', 'Xpress MFB'),
            'remark' => $tx->narrative,
        ];
    }

    private function titleFor(Transaction $tx): string
    {
        return match ($tx->type) {
            TransactionType::WalletTransferOut => 'Transfer to '.($tx->counterparty_name ?? 'recipient'),
            TransactionType::WalletTransferIn => 'Transfer from '.($tx->counterparty_name ?? 'sender'),
            TransactionType::TransferFee => 'Transfer fee',
            TransactionType::Airtime => 'Airtime purchase',
            default => $tx->narrative ?? 'Transaction',
        };
    }
}
