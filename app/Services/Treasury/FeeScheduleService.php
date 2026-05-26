<?php

namespace App\Services\Treasury;

use App\Models\FeeSchedule;

class FeeScheduleService
{
    public const DEFAULT_TRANSFER_FEE = 50.0;

    public function walletTransferFee(): float
    {
        return $this->flatFee('wallet_transfer', self::DEFAULT_TRANSFER_FEE);
    }

    public function flatFee(string $productKey, float $default): float
    {
        $schedule = FeeSchedule::query()
            ->where('product_key', $productKey)
            ->where('active', true)
            ->whereDate('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first();

        if (! $schedule || $schedule->fee_type !== 'flat') {
            return $default;
        }

        return max(0, (float) $schedule->rate_or_amount);
    }
}
