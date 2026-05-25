<?php

namespace Database\Seeders;

use App\Enums\FloatPositionStatus;
use App\Models\ApprovalRequest;
use App\Models\FeeSchedule;
use App\Models\FloatPosition;
use App\Models\TreasuryPnlSnapshot;
use App\Models\User;
use App\Services\Treasury\TreasuryApprovalService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TreasurySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFloatPositions();
        $this->seedFeeSchedules();
        $this->seedPnlSnapshots();
        $this->seedSampleCommissionApproval();
    }

    private function seedFloatPositions(): void
    {
        $banks = [
            [
                'bank_name' => 'GTBank — settlement account',
                'account_number' => '012-3847-2918',
                'account_label' => 'Primary settlement',
                'balance' => 820_000_000,
                'utilization_pct' => 42,
                'status' => FloatPositionStatus::Healthy,
            ],
            [
                'bank_name' => 'Zenith — nostro float',
                'account_number' => '057-2918-4827',
                'account_label' => 'Nostro float',
                'balance' => 640_000_000,
                'utilization_pct' => 55,
                'status' => FloatPositionStatus::Healthy,
            ],
            [
                'bank_name' => 'Access — liquidity pool',
                'account_number' => '001-8821-4402',
                'account_label' => 'Liquidity pool',
                'balance' => 510_000_000,
                'utilization_pct' => 68,
                'status' => FloatPositionStatus::Watch,
            ],
            [
                'bank_name' => 'UBA — commission settlement',
                'account_number' => '209-1102-8831',
                'account_label' => 'Commission settlement',
                'balance' => 430_000_000,
                'utilization_pct' => 38,
                'status' => FloatPositionStatus::Healthy,
            ],
            [
                'bank_name' => 'First Bank — agent float',
                'account_number' => '304-9921-1180',
                'account_label' => 'Agent float',
                'balance' => 400_000_000,
                'utilization_pct' => 61,
                'status' => FloatPositionStatus::Watch,
            ],
        ];

        foreach ($banks as $bank) {
            FloatPosition::query()->updateOrCreate(
                ['account_number' => $bank['account_number']],
                array_merge($bank, [
                    'currency' => 'NGN',
                    'updated_at' => now(),
                ]),
            );
        }
    }

    private function seedFeeSchedules(): void
    {
        $schedules = [
            ['product_key' => 'cash_out', 'product_label' => 'Cash-out', 'fee_type' => 'flat', 'rate_or_amount' => 100],
            ['product_key' => 'wallet_transfer', 'product_label' => 'Transfers (NIP)', 'fee_type' => 'flat', 'rate_or_amount' => 50],
            ['product_key' => 'bills_airtime', 'product_label' => 'Bills & airtime', 'fee_type' => 'percent', 'rate_or_amount' => 1.5],
            ['product_key' => 'pos', 'product_label' => 'POS purchases', 'fee_type' => 'percent', 'rate_or_amount' => 0.75],
        ];

        $effectiveFrom = Carbon::parse('2026-01-01');

        foreach ($schedules as $schedule) {
            FeeSchedule::query()->updateOrCreate(
                ['product_key' => $schedule['product_key'], 'effective_from' => $effectiveFrom->toDateString()],
                array_merge($schedule, [
                    'effective_from' => $effectiveFrom,
                    'active' => true,
                ]),
            );
        }
    }

    private function seedPnlSnapshots(): void
    {
        $period = now()->format('Y-m');

        TreasuryPnlSnapshot::query()->updateOrCreate(
            ['period' => $period],
            [
                'revenue' => 184_000_000,
                'costs' => 94_000_000,
                'net' => 90_000_000,
                'metadata' => [
                    'fee_revenue' => 184_000_000,
                    'commission_costs' => 94_000_000,
                    'note' => 'Demo P&L snapshot aligned with treasury KPIs',
                ],
            ],
        );
    }

    private function seedSampleCommissionApproval(): void
    {
        $maker = User::query()->where('email', 'finance@iwallet.demo')->first();
        if (! $maker) {
            return;
        }

        $period = now()->format('Y-m');
        $batchId = "PB-{$period}";

        $pendingExists = ApprovalRequest::query()
            ->where('resource_type', 'commission_batch')
            ->where('resource_id', $batchId)
            ->where('status', \App\Enums\ApprovalRequestStatus::Pending)
            ->exists();

        if ($pendingExists) {
            return;
        }

        \App\Models\AgentCommission::query()
            ->where('period', $period)
            ->update([
                'status' => \App\Enums\AgentCommissionStatus::Accrued,
                'paid_at' => null,
            ]);

        /** @var TreasuryApprovalService $approvalService */
        $approvalService = app(TreasuryApprovalService::class);
        /** @var TreasuryService $treasury */
        $treasury = app(TreasuryService::class);

        $batch = $treasury->commissionBatches(['period' => $period]);
        $item = $batch['items'][0] ?? null;

        if (! $item || $item['amount'] <= 0) {
            return;
        }

        $approvalService->submitCommissionBatch($maker, ['period' => $period]);
    }
}
