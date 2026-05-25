<?php

namespace App\Services\Aml;

use App\Enums\WalletStatus;
use App\Models\AmlCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFreeze;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\MakerCheckerService;
use InvalidArgumentException;

class WalletFreezeService
{
    public function __construct(
        private MakerCheckerService $makerChecker,
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function freeze(array $data, User $maker): WalletFreeze
    {
        $this->makerChecker->assertMaker($maker, 'aml_cases');

        $wallet = $this->resolveWallet($data);
        $case = null;

        if (! empty($data['case_id'])) {
            $case = AmlCase::query()->where('reference', $data['case_id'])->first()
                ?? AmlCase::query()->find($data['case_id']);
        }

        $existing = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Wallet is already frozen.');
        }

        $freeze = WalletFreeze::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'case_id' => $case?->id,
            'reason' => $data['reason'],
            'frozen_by_id' => $maker->id,
            'active' => true,
        ]);

        $wallet->update(['status' => WalletStatus::Frozen]);

        $this->auditLog->record(
            $maker,
            'aml.wallet.frozen',
            'WalletFreeze',
            (string) $freeze->id,
            "Froze wallet {$wallet->account_number} under AML case",
            [
                'wallet_id' => $wallet->id,
                'account_number' => $wallet->account_number,
                'user_id' => $wallet->user_id,
                'case_id' => $case?->reference,
                'reason' => $data['reason'],
                'compliance_notified' => true,
            ],
        );

        return $freeze->fresh(['wallet', 'user', 'amlCase', 'frozenBy']);
    }

    public function unfreeze(WalletFreeze $freeze, User $actor, ?string $notes = null): WalletFreeze
    {
        if (! $freeze->active) {
            throw new InvalidArgumentException('Wallet freeze is not active.');
        }

        $freeze->update([
            'active' => false,
            'unfrozen_at' => now(),
        ]);

        $wallet = $freeze->wallet;
        if ($wallet) {
            $otherActive = WalletFreeze::query()
                ->where('wallet_id', $wallet->id)
                ->where('active', true)
                ->where('id', '!=', $freeze->id)
                ->exists();

            if (! $otherActive) {
                $wallet->update(['status' => WalletStatus::Active]);
            }
        }

        $this->auditLog->record(
            $actor,
            'aml.wallet.unfrozen',
            'WalletFreeze',
            (string) $freeze->id,
            'Unfroze wallet under AML review',
            [
                'wallet_id' => $freeze->wallet_id,
                'case_id' => $freeze->amlCase?->reference,
                'notes' => $notes,
            ],
        );

        return $freeze->fresh(['wallet', 'user', 'amlCase', 'frozenBy']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveWallet(array $data): Wallet
    {
        if (! empty($data['wallet_id'])) {
            return Wallet::query()->findOrFail($data['wallet_id']);
        }

        if (! empty($data['wallet_account'])) {
            $wallet = Wallet::query()->where('account_number', $data['wallet_account'])->first();
            if ($wallet) {
                return $wallet;
            }
        }

        if (! empty($data['user_id'])) {
            $wallet = Wallet::query()->where('user_id', $data['user_id'])->first();
            if ($wallet) {
                return $wallet;
            }
        }

        throw new InvalidArgumentException('Wallet not found. Provide wallet_id, wallet_account, or user_id.');
    }
}
