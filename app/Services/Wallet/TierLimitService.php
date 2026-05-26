<?php

namespace App\Services\Wallet;

use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\TierDefinition;
use App\Models\Transaction;
use App\Models\User;
use InvalidArgumentException;

class TierLimitService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $tier = $user->account_tier ?? 'tier_1';
        $limits = $this->limitsForUser($user);
        $usage = $this->usageToday($user);

        $dailyLimit = (float) ($limits['daily_transfer'] ?? 0);
        $dailyUsed = (float) ($usage['daily_transfer_used'] ?? 0);
        $dailyRemaining = max(0, $dailyLimit - $dailyUsed);

        return [
            'account_tier' => $tier,
            'tier_label' => $limits['tier_label'] ?? strtoupper(str_replace('_', ' ', $tier)),
            'single_transfer' => (float) ($limits['single_transfer'] ?? 0),
            'daily_transfer' => $dailyLimit,
            'daily_transfer_used' => $dailyUsed,
            'daily_transfer_remaining' => $dailyRemaining,
            'balance_limit' => (float) ($limits['balance_limit'] ?? 0),
            'next_tier' => $this->nextTier($tier),
            'next_tier_limits' => $this->nextTierLimits($tier),
        ];
    }

    public function assertTransferAllowed(User $user, float $amount): void
    {
        $limits = $this->limitsForUser($user);
        $usage = $this->usageToday($user);

        $singleLimit = (float) ($limits['single_transfer'] ?? 0);
        if ($singleLimit > 0 && $amount > $singleLimit) {
            throw new InvalidArgumentException(
                'This amount exceeds your '.($limits['tier_label'] ?? 'account').' single transfer limit of ₦'.number_format($singleLimit, 0).'.'
            );
        }

        $dailyLimit = (float) ($limits['daily_transfer'] ?? 0);
        $dailyUsed = (float) ($usage['daily_transfer_used'] ?? 0);
        if ($dailyLimit > 0 && ($dailyUsed + $amount) > $dailyLimit) {
            $remaining = max(0, $dailyLimit - $dailyUsed);
            throw new InvalidArgumentException(
                'This transfer would exceed your daily limit. You have ₦'.number_format($remaining, 0).' remaining today.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForUser(User $user): array
    {
        return $this->limitsForTier($user->account_tier ?? 'tier_1');
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForTier(string $tier, string $applicantType = 'customer'): array
    {
        $definition = TierDefinition::query()
            ->where('applicant_type', $applicantType)
            ->where('tier', $tier)
            ->first();

        if ($definition?->limits) {
            return array_merge(
                ['tier' => $tier, 'tier_label' => $definition->label],
                $definition->limits,
            );
        }

        $defaults = config("onboarding.tier_requirements.{$applicantType}.{$tier}.limits", []);

        return array_merge(
            ['tier' => $tier, 'tier_label' => $definition?->label ?? strtoupper(str_replace('_', ' ', $tier))],
            $this->normalizeLimits($defaults),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function usageToday(User $user): array
    {
        $used = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('direction', TransactionDirection::Debit)
            ->where('type', TransactionType::WalletTransferOut)
            ->where('status', TransactionStatus::Success)
            ->whereDate('created_at', today())
            ->sum('amount');

        return [
            'daily_transfer_used' => $used,
        ];
    }

    protected function nextTier(string $tier): ?string
    {
        return match ($tier) {
            'tier_1' => 'tier_2',
            'tier_2' => 'tier_3',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function nextTierLimits(string $tier): ?array
    {
        $next = $this->nextTier($tier);
        if (! $next) {
            return null;
        }

        return $this->limitsForTier($next);
    }

    /**
     * @param  array<string, mixed>  $limits
     * @return array<string, float>
     */
    protected function normalizeLimits(array $limits): array
    {
        return [
            'daily_transfer' => (float) ($limits['daily_transfer'] ?? 0),
            'single_transfer' => (float) ($limits['single_transfer'] ?? 0),
            'balance_limit' => (float) ($limits['balance_limit'] ?? 0),
        ];
    }
}
