<?php

namespace App\Services\Wallet;

use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFreeze;
use App\Services\Audit\AuditLogService;
use InvalidArgumentException;

class WalletRestrictionService
{
    public function __construct(
        private TierLimitService $tierLimitService,
        private AuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function activeRestrictionForUser(User $user): ?array
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->first();
        if (! $wallet) {
            return null;
        }

        $restriction = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->latest()
            ->first();

        if (! $restriction) {
            return null;
        }

        return $this->formatRestriction($restriction, $wallet);
    }

    public function assertDebitAllowed(Wallet $wallet): void
    {
        if ($wallet->status === WalletStatus::Frozen) {
            throw new InvalidArgumentException('Your wallet is frozen. Contact support for assistance.');
        }

        if ($wallet->status === WalletStatus::Pnd) {
            $message = $this->customerMessageForWallet($wallet)
                ?? 'Your account is on Post No Debit (PND). Outbound transactions are blocked until this is resolved.';
            throw new InvalidArgumentException($message);
        }

        if ($wallet->status === WalletStatus::Closed) {
            throw new InvalidArgumentException('This wallet is closed.');
        }
    }

    public function checkBalanceLimitAfterCredit(User $user, Wallet $wallet): void
    {
        if ($wallet->status === WalletStatus::Frozen) {
            return;
        }

        $limits = $this->tierLimitService->limitsForUser($user);
        $balanceLimit = (float) ($limits['balance_limit'] ?? 0);
        if ($balanceLimit <= 0) {
            return;
        }

        $wallet->refresh();
        if ((float) $wallet->balance <= $balanceLimit) {
            return;
        }

        $existing = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->where('source', 'tier_balance_limit')
            ->first();

        if ($existing) {
            return;
        }

        $tierLabel = $limits['tier_label'] ?? 'your current tier';
        $nextTier = $this->tierLimitService->snapshot($user)['next_tier'] ?? 'tier_2';

        $this->placePnd(
            wallet: $wallet,
            reason: "Balance exceeded {$tierLabel} limit of ₦".number_format($balanceLimit, 0),
            customerMessage: "Your wallet balance exceeded the ₦".number_format($balanceLimit, 0)." limit for {$tierLabel}. "
                .'Upgrade to '.str_replace('_', ' ', (string) $nextTier).' to continue using outbound transactions.',
            source: 'tier_balance_limit',
            actor: null,
        );
    }

    public function placePnd(
        Wallet $wallet,
        string $reason,
        ?string $customerMessage = null,
        string $source = 'compliance',
        ?User $actor = null,
    ): WalletFreeze {
        $existing = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Wallet already has an active restriction.');
        }

        $restriction = WalletFreeze::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'case_id' => null,
            'reason' => $reason,
            'restriction_type' => 'pnd',
            'customer_message' => $customerMessage ?? $reason,
            'source' => $source,
            'frozen_by_id' => $actor?->id ?? $wallet->user_id,
            'active' => true,
        ]);

        $wallet->update(['status' => WalletStatus::Pnd]);

        if ($actor) {
            $this->auditLog->record(
                $actor,
                'compliance.wallet.pnd',
                'WalletFreeze',
                (string) $restriction->id,
                "Placed PND on wallet {$wallet->account_number}",
                [
                    'wallet_id' => $wallet->id,
                    'account_number' => $wallet->account_number,
                    'user_id' => $wallet->user_id,
                    'reason' => $reason,
                    'customer_message' => $customerMessage,
                    'source' => $source,
                ],
            );
        }

        return $restriction->fresh(['wallet', 'user']);
    }

    public function liftRestriction(WalletFreeze $restriction, User $actor, ?string $notes = null): WalletFreeze
    {
        if (! $restriction->active) {
            throw new InvalidArgumentException('Restriction is not active.');
        }

        $restriction->update([
            'active' => false,
            'unfrozen_at' => now(),
        ]);

        $wallet = $restriction->wallet;
        if ($wallet) {
            $otherActive = WalletFreeze::query()
                ->where('wallet_id', $wallet->id)
                ->where('active', true)
                ->where('id', '!=', $restriction->id)
                ->exists();

            if (! $otherActive) {
                $wallet->update(['status' => WalletStatus::Active]);
            }
        }

        $this->auditLog->record(
            $actor,
            'compliance.wallet.restriction_lifted',
            'WalletFreeze',
            (string) $restriction->id,
            'Lifted wallet restriction',
            [
                'wallet_id' => $restriction->wallet_id,
                'restriction_type' => $restriction->restriction_type,
                'source' => $restriction->source,
                'notes' => $notes,
            ],
        );

        return $restriction->fresh(['wallet', 'user']);
    }

    public function liftTierBalancePndForUser(User $user): void
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->first();
        if (! $wallet) {
            return;
        }

        $restriction = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->where('source', 'tier_balance_limit')
            ->first();

        if (! $restriction) {
            return;
        }

        $restriction->update([
            'active' => false,
            'unfrozen_at' => now(),
        ]);

        $otherActive = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->exists();

        if (! $otherActive) {
            $wallet->update(['status' => WalletStatus::Active]);
        }
    }

    protected function customerMessageForWallet(Wallet $wallet): ?string
    {
        $restriction = WalletFreeze::query()
            ->where('wallet_id', $wallet->id)
            ->where('active', true)
            ->latest()
            ->first();

        return $restriction?->customer_message;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRestriction(WalletFreeze $restriction, Wallet $wallet): array
    {
        return [
            'id' => $restriction->id,
            'type' => $restriction->restriction_type,
            'source' => $restriction->source,
            'reason' => $restriction->reason,
            'customer_message' => $restriction->customer_message,
            'wallet_status' => $wallet->status->value,
            'requires_tier_upgrade' => $restriction->source === 'tier_balance_limit',
            'created_at' => $restriction->created_at?->toIso8601String(),
        ];
    }
}
