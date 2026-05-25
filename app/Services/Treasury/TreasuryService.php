<?php

namespace App\Services\Treasury;

use App\Enums\AgentCommissionStatus;
use App\Enums\ApprovalRequestStatus;
use App\Enums\FloatPositionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AgentCommission;
use App\Models\ApprovalRequest;
use App\Models\FeeSchedule;
use App\Models\FloatPosition;
use App\Models\TreasuryPnlSnapshot;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TreasuryService
{
    /** @var list<string> */
    private const TREASURY_RESOURCES = [
        'float_positions',
        'commission_payout',
        'settlement_recon',
    ];

    /** @var array<string, array{label: string, tone: string, types: list<TransactionType>}> */
    private const FEE_PRODUCT_MAP = [
        'cash_out' => [
            'label' => 'Cash-out',
            'tone' => 'primary',
            'types' => [TransactionType::CashOut],
        ],
        'wallet_transfer' => [
            'label' => 'Transfers (NIP)',
            'tone' => 'amber',
            'types' => [TransactionType::TransferFee, TransactionType::WalletTransferOut],
        ],
        'bills_airtime' => [
            'label' => 'Bills & airtime',
            'tone' => 'purple',
            'types' => [TransactionType::Airtime, TransactionType::Bills],
        ],
        'pos' => [
            'label' => 'POS purchases',
            'tone' => 'teal',
            'types' => [TransactionType::CashIn],
        ],
    ];

    public function stats(): array
    {
        $period = now()->format('Y-m');
        $feeRevenueMtd = $this->feeRevenueTotal($period);
        $commissionMtd = $this->commissionTotal($period);

        return [
            'total_float' => (float) FloatPosition::query()->sum('balance'),
            'fee_revenue_mtd' => $feeRevenueMtd,
            'commission_mtd' => $commissionMtd,
            'net_margin_mtd' => round($feeRevenueMtd - $commissionMtd, 2),
            'pending_approvals' => $this->pendingApprovalsCount(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listFloatPositions(array $filters = []): LengthAwarePaginator
    {
        $query = FloatPosition::query()->orderBy('id');

        if (! empty($filters['status'])) {
            $status = FloatPositionStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findFloatPosition(int $id): FloatPosition
    {
        return FloatPosition::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFloatPosition(array $data): FloatPosition
    {
        return FloatPosition::query()->create([
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_label' => $data['account_label'] ?? null,
            'balance' => $data['balance'] ?? 0,
            'utilization_pct' => $data['utilization_pct'] ?? 0,
            'status' => FloatPositionStatus::tryFrom($data['status'] ?? '') ?? FloatPositionStatus::Healthy,
            'currency' => $data['currency'] ?? 'NGN',
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFloatPosition(FloatPosition $position, array $data): FloatPosition
    {
        $updates = [];

        foreach (['bank_name', 'account_number', 'account_label', 'currency'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        foreach (['balance', 'utilization_pct'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('status', $data)) {
            $status = FloatPositionStatus::tryFrom((string) $data['status']);
            if ($status) {
                $updates['status'] = $status;
            }
        }

        $updates['updated_at'] = now();
        $position->update($updates);

        return $position->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function feeRevenueBreakdown(?string $period = null): array
    {
        $period = $this->normalizePeriod($period);
        [$start, $end] = $this->periodBounds($period);

        $byType = Transaction::query()
            ->where('status', TransactionStatus::Success)
            ->whereBetween('created_at', [$start, $end])
            ->get(['type', 'amount', 'fee'])
            ->groupBy(fn (Transaction $txn) => $txn->type->value);

        $byProduct = [];
        $total = 0.0;

        foreach (self::FEE_PRODUCT_MAP as $productKey => $config) {
            $amount = 0.0;

            foreach ($config['types'] as $type) {
                $rows = $byType->get($type->value, collect());

                foreach ($rows as $txn) {
                    $amount += $type === TransactionType::TransferFee
                        ? (float) $txn->amount
                        : (float) $txn->fee;
                }
            }

            $schedule = FeeSchedule::query()
                ->where('product_key', $productKey)
                ->where('active', true)
                ->orderByDesc('effective_from')
                ->first();

            $byProduct[] = [
                'product_key' => $productKey,
                'product' => $schedule?->product_label ?? $config['label'],
                'amount' => round($amount, 2),
                'tone' => $config['tone'],
            ];

            $total += $amount;
        }

        return [
            'period' => $period,
            'total' => round($total, 2),
            'by_product' => $byProduct,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function commissionBatches(array $filters = []): array
    {
        $period = $this->normalizePeriod($filters['period'] ?? null);

        $rows = AgentCommission::query()
            ->with(['agent:id,code,business_name'])
            ->where('period', $period)
            ->orderByDesc('commission_amount')
            ->get();

        $totalAmount = (float) $rows->sum('commission_amount');
        $agentCount = $rows->count();
        $averagePerAgent = $agentCount > 0 ? round($totalAmount / $agentCount, 2) : 0.0;

        $linkedSample = $rows->take(3)->map(fn (AgentCommission $row) => [
            'agent' => "{$row->agent?->code} — {$row->agent?->business_name}",
            'commission' => (float) $row->commission_amount,
            'txns' => (int) round((float) $row->gross_volume / max((float) $row->commission_amount, 1)),
        ])->values()->all();

        $batchId = "PB-{$period}";
        $pendingApproval = ApprovalRequest::query()
            ->where('resource_type', 'commission_batch')
            ->where('resource_id', $batchId)
            ->where('status', ApprovalRequestStatus::Pending)
            ->exists();

        $items = [[
            'batch_id' => $batchId,
            'title' => 'Monthly commission payout',
            'sub' => "{$agentCount} agents — aggregated",
            'period' => $period,
            'agents' => $agentCount,
            'amount' => round($totalAmount, 2),
            'average_per_agent' => $averagePerAgent,
            'status' => $pendingApproval ? 'pending_approval' : ($rows->every(
                fn (AgentCommission $row) => $row->status === AgentCommissionStatus::Paid,
            ) ? 'paid' : 'accrued'),
            'linked_count' => $agentCount,
            'linked_sample' => $linkedSample,
        ]];

        return [
            'period' => $period,
            'items' => $items,
            'total_amount' => round($totalAmount, 2),
            'total_agents' => $agentCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pnlSummary(?string $period = null): array
    {
        $period = $this->normalizePeriod($period);

        $snapshot = TreasuryPnlSnapshot::query()->where('period', $period)->first();

        if ($snapshot) {
            return [
                'period' => $snapshot->period,
                'revenue' => (float) $snapshot->revenue,
                'costs' => (float) $snapshot->costs,
                'net' => (float) $snapshot->net,
                'metadata' => $snapshot->metadata ?? [],
                'source' => 'snapshot',
            ];
        }

        $revenue = $this->feeRevenueTotal($period);
        $costs = $this->commissionTotal($period);

        return [
            'period' => $period,
            'revenue' => $revenue,
            'costs' => $costs,
            'net' => round($revenue - $costs, 2),
            'metadata' => [
                'revenue_source' => 'transaction_fees',
                'costs_source' => 'agent_commissions',
            ],
            'source' => 'computed',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listFeeSchedules(array $filters = []): LengthAwarePaginator
    {
        $query = FeeSchedule::query()->orderBy('product_key');

        if (array_key_exists('active', $filters)) {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listApprovals(array $filters = []): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->with(['policy', 'maker.backofficeRole', 'checker'])
            ->whereHas('policy', fn ($q) => $q->whereIn('resource', self::TREASURY_RESOURCES))
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $status = ApprovalRequestStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function applyFloatTopUp(int $floatPositionId, float $amount): FloatPosition
    {
        $position = $this->findFloatPosition($floatPositionId);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        $position->update([
            'balance' => (float) $position->balance + $amount,
            'updated_at' => now(),
        ]);

        return $position->fresh();
    }

    public function applyFloatPayout(int $floatPositionId, float $amount): FloatPosition
    {
        $position = $this->findFloatPosition($floatPositionId);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }

        if ((float) $position->balance < $amount) {
            throw new InvalidArgumentException('Insufficient float balance for payout.');
        }

        $position->update([
            'balance' => (float) $position->balance - $amount,
            'updated_at' => now(),
        ]);

        return $position->fresh();
    }

    public function applyCommissionBatchPayout(string $period): int
    {
        $period = $this->normalizePeriod($period);

        return AgentCommission::query()
            ->where('period', $period)
            ->where('status', AgentCommissionStatus::Accrued)
            ->update([
                'status' => AgentCommissionStatus::Paid,
                'paid_at' => now(),
            ]);
    }

    private function pendingApprovalsCount(): int
    {
        return ApprovalRequest::query()
            ->where('status', ApprovalRequestStatus::Pending)
            ->whereHas('policy', fn ($q) => $q->whereIn('resource', self::TREASURY_RESOURCES))
            ->count();
    }

    private function feeRevenueTotal(string $period): float
    {
        return (float) ($this->feeRevenueBreakdown($period)['total'] ?? 0);
    }

    private function commissionTotal(string $period): float
    {
        return (float) AgentCommission::query()
            ->where('period', $period)
            ->sum('commission_amount');
    }

    private function normalizePeriod(?string $period): string
    {
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $period;
        }

        return now()->format('Y-m');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }
}
