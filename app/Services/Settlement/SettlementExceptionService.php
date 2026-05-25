<?php

namespace App\Services\Settlement;

use App\Enums\SettlementExceptionStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\ApprovalRequest;
use App\Models\SettlementException;
use App\Models\SettlementExceptionEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\ApprovalRequestService;
use App\Services\Governance\MakerCheckerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementExceptionService
{
    public function __construct(
        private MakerCheckerService $makerChecker,
        private ApprovalRequestService $approvalRequests,
        private AuditLogService $auditLog,
    ) {}

    public function find(string $reference): SettlementException
    {
        return SettlementException::query()
            ->with(['cycle', 'events.actor', 'maker', 'checker', 'resolvedBy'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function resolve(SettlementException $exception, User $actor, ?string $notes = null): SettlementException
    {
        if ($exception->status === SettlementExceptionStatus::Resolved) {
            throw new InvalidArgumentException('Exception is already resolved.');
        }

        $exception->update([
            'status' => SettlementExceptionStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'resolution_notes' => $notes,
        ]);

        $this->addEvent($exception, $actor, 'Exception resolved', $notes);

        $this->auditLog->record(
            $actor,
            'settlement.exception.resolved',
            'SettlementException',
            $exception->reference,
            "Resolved settlement exception {$exception->reference}",
            [
                'reference' => $exception->reference,
                'amount' => (float) $exception->amount,
                'category' => $exception->category->value,
                'notes' => $notes,
            ],
        );

        return $exception->fresh(['cycle', 'events.actor', 'maker', 'checker', 'resolvedBy']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function creditWallet(SettlementException $exception, User $maker, array $data): array
    {
        if ($exception->status === SettlementExceptionStatus::Resolved) {
            throw new InvalidArgumentException('Cannot credit a resolved exception.');
        }

        $amount = (float) ($data['amount'] ?? $exception->amount);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        $threshold = (float) config('settlement.credit_threshold', 1_000_000);
        $policy = $this->makerChecker->assertMaker($maker, 'settlement_recon');

        if ($amount > $threshold) {
            $approvalRequest = $this->approvalRequests->submit(
                $maker,
                'settlement_recon',
                'settlement_exception',
                $exception->reference,
                [
                    'action' => 'settlement_manual_credit',
                    'exception_reference' => $exception->reference,
                    'amount' => $amount,
                    'wallet_account' => $data['wallet_account'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                "Manual wallet credit of ₦".number_format($amount, 2)." for {$exception->reference}",
            );

            $exception->update(['maker_id' => $maker->id]);

            $this->addEvent(
                $exception,
                $maker,
                'Manual credit submitted for checker approval',
                $data['notes'] ?? null,
            );

            return [
                'mode' => 'approval_required',
                'approval_request' => $approvalRequest->load(['policy', 'maker']),
                'exception' => $exception->fresh(['cycle', 'events.actor', 'maker', 'checker', 'resolvedBy']),
            ];
        }

        $exception = $this->executeCredit($exception, $maker, $amount, $data);

        return [
            'mode' => 'direct',
            'exception' => $exception,
        ];
    }

    public function executeCredit(
        SettlementException $exception,
        User $actor,
        float $amount,
        array $data = [],
        ?User $checker = null,
    ): SettlementException {
        return DB::transaction(function () use ($exception, $actor, $amount, $data, $checker) {
            $wallet = $this->resolveWallet($exception, $data['wallet_account'] ?? null);

            if ($wallet) {
                $wallet->increment('balance', $amount);

                Transaction::query()->create([
                    'reference' => $this->generateCreditReference(),
                    'session_id' => $exception->transaction_reference ?? $exception->reference,
                    'user_id' => $wallet->user_id,
                    'wallet_id' => $wallet->id,
                    'type' => TransactionType::WalletTransferIn,
                    'direction' => TransactionDirection::Credit,
                    'amount' => $amount,
                    'status' => TransactionStatus::Success,
                    'counterparty_name' => 'Settlement manual credit',
                    'counterparty_bank' => config('settlement.channel', 'NIBSS'),
                    'narrative' => "Settlement manual credit · {$exception->reference}",
                    'meta' => [
                        'settlement_exception' => $exception->reference,
                        'actor_id' => $actor->id,
                        'checker_id' => $checker?->id,
                    ],
                ]);
            }

            $exception->update([
                'maker_id' => $actor->id,
                'checker_id' => $checker?->id,
            ]);

            $this->addEvent(
                $exception,
                $actor,
                'Manual wallet credit posted',
                $data['notes'] ?? null,
            );

            $this->auditLog->record(
                $actor,
                'settlement.manual_credit.posted',
                'SettlementException',
                $exception->reference,
                "Posted manual credit of ₦".number_format($amount, 2)." for {$exception->reference}",
                [
                    'reference' => $exception->reference,
                    'amount' => $amount,
                    'wallet_account' => $wallet?->account_number,
                    'checker_id' => $checker?->id,
                ],
            );

            return $exception->fresh(['cycle', 'events.actor', 'maker', 'checker', 'resolvedBy']);
        });
    }

    public function addEvent(
        SettlementException $exception,
        User $actor,
        string $action,
        ?string $notes = null,
    ): SettlementExceptionEvent {
        return SettlementExceptionEvent::query()->create([
            'exception_id' => $exception->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    private function resolveWallet(SettlementException $exception, ?string $walletAccount): ?Wallet
    {
        if ($walletAccount) {
            return Wallet::query()->where('account_number', $walletAccount)->first();
        }

        $trace = $exception->trace ?? [];
        $beneficiary = $trace['beneficiary'] ?? '';

        if (preg_match('/W-(\d+)/', $beneficiary, $matches)) {
            return Wallet::query()->where('account_number', $matches[1])->first()
                ?? Wallet::query()->where('account_number', 'like', '%'.$matches[1])->first();
        }

        if ($exception->transaction_reference) {
            $tx = Transaction::query()
                ->where('reference', $exception->transaction_reference)
                ->orWhere('session_id', $exception->transaction_reference)
                ->first();

            if ($tx?->wallet_id) {
                return Wallet::query()->find($tx->wallet_id);
            }
        }

        return null;
    }

    private function generateCreditReference(): string
    {
        $year = now()->year;
        $latest = Transaction::query()
            ->where('reference', 'like', "SMC-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if ($latest && preg_match('/^SMC-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('SMC-%d-%04d', $year, $sequence);
            $sequence++;
        } while (Transaction::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
