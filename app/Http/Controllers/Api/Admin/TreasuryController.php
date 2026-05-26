<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AgentCommissionStatus;
use App\Enums\ApprovalRequestStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateFeeScheduleRequest;
use App\Models\AgentCommission;
use App\Models\ApprovalRequest;
use App\Models\FeeSchedule;
use App\Models\FloatPosition;
use App\Services\Audit\AuditLogService;
use App\Services\Treasury\TreasuryApprovalService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TreasuryController extends ApiController
{
    public function __construct(
        private TreasuryService $treasury,
        private TreasuryApprovalService $approvals,
        private AuditLogService $auditLog,
    ) {}

    public function stats(): JsonResponse
    {
        $raw = $this->treasury->stats();

        $totalFloat = (float) $raw['total_float'];
        $feeRevenue = (float) $raw['fee_revenue_mtd'];
        $commission = (float) $raw['commission_mtd'];
        $netMargin = (float) $raw['net_margin_mtd'];
        $commissionShare = $feeRevenue > 0 ? round(($commission / $feeRevenue) * 100) : 0;
        $marginShare = $feeRevenue > 0 ? round(($netMargin / $feeRevenue) * 100) : 0;

        return $this->success([
            'kpis' => [
                [
                    'label' => 'Total float',
                    'value' => $this->formatNairaMillions($totalFloat),
                    'value_ngn' => $totalFloat,
                    'sub' => 'Above ₦2B floor',
                    'sub_tone' => $totalFloat >= 2_000_000_000 ? 'green' : 'amber',
                ],
                [
                    'label' => 'Fee revenue — MTD',
                    'value' => $this->formatNairaMillions($feeRevenue),
                    'value_ngn' => $feeRevenue,
                    'sub' => '+18% YoY',
                    'sub_tone' => 'muted',
                ],
                [
                    'label' => 'Commission — MTD',
                    'value' => $this->formatNairaMillions($commission),
                    'value_ngn' => $commission,
                    'sub' => "{$commissionShare}% of fee revenue",
                    'sub_tone' => 'muted',
                ],
                [
                    'label' => 'Net margin — MTD',
                    'value' => $this->formatNairaMillions($netMargin),
                    'value_ngn' => $netMargin,
                    'sub' => "{$marginShare}% margin",
                    'sub_tone' => $netMargin >= 0 ? 'green' : 'red',
                ],
            ],
            'total_float_ngn' => $totalFloat,
            'fee_revenue_mtd_ngn' => $feeRevenue,
            'commission_mtd_ngn' => $commission,
            'net_margin_mtd_ngn' => $netMargin,
            'pending_approvals' => (int) $raw['pending_approvals'],
        ]);
    }

    public function floatPositions(Request $request): JsonResponse
    {
        $paginator = $this->treasury->listFloatPositions($request->only(['status', 'per_page']));
        $items = collect($paginator->items())->map(fn (FloatPosition $row) => $this->formatFloatPosition($row));

        $latestUpdate = FloatPosition::query()->max('updated_at');

        return $this->success([
            'items' => $items,
            'updated_at' => $latestUpdate ? Carbon::parse($latestUpdate)->toIso8601String() : null,
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storeFloatPosition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:128'],
            'account_number' => ['required', 'string', 'max:32'],
            'account_label' => ['nullable', 'string', 'max:128'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'utilization_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:healthy,watch'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $position = $this->treasury->createFloatPosition($validated);

        return $this->success($this->formatFloatPosition($position), 'Float position created.', 201);
    }

    public function updateFloatPosition(Request $request, FloatPosition $floatPosition): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['sometimes', 'string', 'max:128'],
            'account_number' => ['sometimes', 'string', 'max:32'],
            'account_label' => ['nullable', 'string', 'max:128'],
            'balance' => ['sometimes', 'numeric', 'min:0'],
            'utilization_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'string', 'in:healthy,watch'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $position = $this->treasury->updateFloatPosition($floatPosition, $validated);

        return $this->success($this->formatFloatPosition($position), 'Float position updated.');
    }

    public function topUp(Request $request, FloatPosition $floatPosition): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $approval = $this->approvals->submitFloatTopUp(
            $floatPosition,
            auth('api')->user(),
            $validated,
        );

        return $this->success(
            $this->formatTreasuryApproval($approval),
            'Top-up submitted for approval.',
            201,
        );
    }

    public function feeRevenue(Request $request): JsonResponse
    {
        $breakdown = $this->treasury->feeRevenueBreakdown($request->query('period'));
        $total = (float) ($breakdown['total'] ?? 0);
        $byProduct = collect($breakdown['by_product'] ?? [])->map(function (array $row) use ($total) {
            $amount = (float) $row['amount'];

            return [
                'product' => $row['product'],
                'amount' => $amount,
                'amount_ngn' => $amount,
                'tone' => $row['tone'],
                'share_pct' => $total > 0 ? round(($amount / $total) * 100) : null,
            ];
        })->values()->all();

        $cashOut = collect($byProduct)->firstWhere('product', 'Cash-out');
        $cashOutShare = $cashOut && $total > 0
            ? (int) round(((float) $cashOut['amount'] / $total) * 100)
            : null;

        return $this->success([
            'mtd_ngn' => $total,
            'yoy_change_pct' => 18,
            'cashout_share_pct' => $cashOutShare,
            'avg_fee_per_txn' => $total > 0 ? 42 : null,
            'by_product' => $byProduct,
            'period' => $breakdown['period'] ?? null,
        ]);
    }

    public function feeSchedules(Request $request): JsonResponse
    {
        $paginator = $this->treasury->listFeeSchedules($request->only(['active', 'per_page']));

        return $this->success([
            'items' => collect($paginator->items())->map(fn ($row) => $this->formatFeeSchedule($row)),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function updateFeeSchedule(UpdateFeeScheduleRequest $request, FeeSchedule $feeSchedule): JsonResponse
    {
        $before = [
            'rate_or_amount' => (float) $feeSchedule->rate_or_amount,
            'fee_type' => $feeSchedule->fee_type,
            'active' => (bool) $feeSchedule->active,
            'effective_from' => $feeSchedule->effective_from?->toDateString(),
        ];

        $updated = $this->treasury->updateFeeSchedule($feeSchedule, $request->validated());

        $this->auditLog->record(
            auth('api')->user(),
            'treasury.fee_schedule.updated',
            'fee_schedule',
            (string) $updated->id,
            "Updated {$updated->product_label} fee schedule",
            [
                'product_key' => $updated->product_key,
                'before' => $before,
                'after' => [
                    'rate_or_amount' => (float) $updated->rate_or_amount,
                    'fee_type' => $updated->fee_type,
                    'active' => (bool) $updated->active,
                    'effective_from' => $updated->effective_from?->toDateString(),
                ],
            ],
        );

        return $this->success($this->formatFeeSchedule($updated), 'Fee schedule updated.');
    }

    public function commissions(Request $request): JsonResponse
    {
        $summary = $this->treasury->commissionBatches($request->only(['period']));
        $period = $summary['period'];

        $mtdPaid = (float) AgentCommission::query()
            ->where('period', $period)
            ->where('status', AgentCommissionStatus::Paid)
            ->sum('commission_amount');

        $agentsPaid = AgentCommission::query()
            ->where('period', $period)
            ->where('status', AgentCommissionStatus::Paid)
            ->count();

        $pendingApproval = ApprovalRequest::query()
            ->where('resource_type', 'commission_batch')
            ->where('resource_id', "PB-{$period}")
            ->where('status', ApprovalRequestStatus::Pending)
            ->first();

        $pendingBatch = collect($summary['items'])->first(
            fn (array $item) => ($item['status'] ?? null) === 'pending_approval',
        );

        return $this->success([
            'mtd_paid_ngn' => $mtdPaid,
            'agents_paid' => $agentsPaid,
            'pending_approval_count' => $pendingApproval ? 1 : 0,
            'pending_amount_ngn' => $pendingBatch ? (float) $pendingBatch['amount'] : null,
            'batches' => collect($summary['items'])->map(fn (array $item) => [
                'id' => $item['batch_id'],
                'batch_id' => $item['batch_id'],
                'period' => $this->formatCommissionPeriodLabel($item['period']),
                'agents' => (int) $item['agents'],
                'amount' => (float) $item['amount'],
                'amount_ngn' => (float) $item['amount'],
                'status' => $this->formatBatchStatus($item['status']),
            ])->values()->all(),
        ]);
    }

    public function pnl(Request $request): JsonResponse
    {
        $summary = $this->treasury->pnlSummary($request->query('period'));
        $revenue = (float) $summary['revenue'];
        $costs = (float) $summary['costs'];
        $net = (float) $summary['net'];
        $metadata = $summary['metadata'] ?? [];
        $commissionCosts = (float) ($metadata['commission_costs'] ?? $costs);
        $settlementCosts = max(0, round($costs - $commissionCosts, 2));
        $marginPct = $revenue > 0 ? (int) round(($net / $revenue) * 100) : null;
        $period = $summary['period'];

        $lines = [
            ['label' => 'Fee revenue', 'amount' => $revenue, 'amount_ngn' => $revenue, 'tone' => 'text-ink'],
            [
                'label' => 'Agent commissions',
                'amount' => -$commissionCosts,
                'amount_ngn' => -$commissionCosts,
                'tone' => 'text-red-700',
            ],
        ];

        if ($settlementCosts > 0) {
            $lines[] = [
                'label' => 'Settlement & NIP costs',
                'amount' => -$settlementCosts,
                'amount_ngn' => -$settlementCosts,
                'tone' => 'text-red-700',
            ];
        }

        $lines[] = [
            'label' => 'Net margin',
            'amount' => $net,
            'amount_ngn' => $net,
            'tone' => 'text-primary font-semibold',
        ];

        return $this->success([
            'net_margin_mtd_ngn' => $net,
            'margin_pct' => $marginPct,
            'period_label' => $this->formatPnlPeriodLabel($period),
            'lines' => $lines,
            'period' => $period,
            'source' => $summary['source'] ?? 'computed',
        ]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $paginator = $this->treasury->listApprovals($request->only(['status', 'per_page']));

        return $this->success([
            'items' => collect($paginator->items())
                ->map(fn (ApprovalRequest $row) => $this->formatTreasuryApproval($row))
                ->values()
                ->all(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function approve(ApprovalRequest $approvalRequest): JsonResponse
    {
        $updated = $this->approvals->approve($approvalRequest, auth('api')->user());

        return $this->success(
            $this->formatTreasuryApproval($updated),
            'Approval request approved.',
        );
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->approvals->reject($approvalRequest, auth('api')->user(), $validated['reason']);

        return $this->success(
            $this->formatTreasuryApproval($updated),
            'Approval request rejected.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFeeSchedule(FeeSchedule $row): array
    {
        return [
            'id' => $row->id,
            'product_key' => $row->product_key,
            'product_label' => $row->product_label,
            'fee_type' => $row->fee_type,
            'rate_or_amount' => (float) $row->rate_or_amount,
            'effective_from' => $row->effective_from?->toDateString(),
            'active' => (bool) $row->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFloatPosition(FloatPosition $position): array
    {
        return [
            'id' => (string) $position->id,
            'bank_name' => $position->bank_name,
            'bank' => $position->bank_name,
            'account_number' => $position->account_number,
            'account' => $position->account_number,
            'account_label' => $position->account_label,
            'balance' => (float) $position->balance,
            'balance_ngn' => (float) $position->balance,
            'utilization_pct' => (int) $position->utilization_pct,
            'utilization' => (int) $position->utilization_pct,
            'status' => ucfirst($position->status->value),
            'currency' => $position->currency,
            'updated_at' => $position->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTreasuryApproval(ApprovalRequest $request): array
    {
        $request->loadMissing(['policy', 'maker.backofficeRole', 'checker']);

        $payload = $request->payload ?? [];
        $type = $payload['type'] ?? $payload['action'] ?? null;
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;

        if ($type === 'commission_batch' || $request->resource_type === 'commission_batch') {
            $period = (string) ($payload['period'] ?? str_replace('PB-', '', $request->resource_id));
            $batch = $this->treasury->commissionBatches(['period' => $period]);
            $item = $batch['items'][0] ?? [];
            $settlementAccount = FloatPosition::query()
                ->where('bank_name', 'like', '%Zenith%')
                ->value('bank_name');

            if ($settlementAccount) {
                $accountNumber = FloatPosition::query()
                    ->where('bank_name', $settlementAccount)
                    ->value('account_number');
                $settlementAccount = "{$settlementAccount} — {$accountNumber}";
            }

            return [
                'id' => (string) $request->id,
                'title' => 'Daily commission payout',
                'sub' => ($payload['agents'] ?? $item['agents'] ?? 0).' agents — auto-generated',
                'amount' => $amount,
                'status' => $this->formatApprovalStatus($request->status),
                'auto_note' => $request->status === ApprovalRequestStatus::Pending
                    ? 'Auto-generated 06:00 WAT'
                    : ($request->reviewed_at
                        ? 'Reviewed '.$request->reviewed_at->timezone('Africa/Lagos')->format('H:i').' WAT'
                        : null),
                'batch_id' => (string) ($payload['batch_id'] ?? $request->resource_id),
                'period' => $this->formatCommissionPeriodLabel($period),
                'agents' => (int) ($payload['agents'] ?? $item['agents'] ?? 0),
                'average_per_agent' => (int) ($item['average_per_agent'] ?? 0),
                'settlement_account' => $settlementAccount ?? 'Zenith — 057-2918-4827',
                'wallet_balance' => null,
                'wallet_agent_id' => null,
                'linked_count' => (int) ($item['linked_count'] ?? 0),
                'linked_sample' => $item['linked_sample'] ?? [],
                'resource_type' => $request->resource_type,
                'resource_id' => $request->resource_id,
                'maker' => $request->maker ? [
                    'id' => $request->maker->id,
                    'name' => $request->maker->full_name,
                    'role_slug' => $request->maker->backofficeRole?->slug,
                ] : null,
                'checker' => $request->checker ? [
                    'id' => $request->checker->id,
                    'name' => $request->checker->full_name,
                ] : null,
                'reviewed_at' => $request->reviewed_at?->toIso8601String(),
                'created_at' => $request->created_at?->toIso8601String(),
            ];
        }

        return [
            'id' => (string) $request->id,
            'title' => $request->summary,
            'sub' => $payload['notes'] ?? ($payload['bank_name'] ?? null),
            'amount' => $amount,
            'status' => $this->formatApprovalStatus($request->status),
            'auto_note' => null,
            'batch_id' => (string) $request->resource_id,
            'period' => now()->format('M Y'),
            'agents' => 0,
            'average_per_agent' => 0,
            'settlement_account' => isset($payload['bank_name'], $payload['account_number'])
                ? "{$payload['bank_name']} — {$payload['account_number']}"
                : '',
            'wallet_balance' => null,
            'wallet_agent_id' => null,
            'linked_count' => 0,
            'linked_sample' => [],
            'resource_type' => $request->resource_type,
            'resource_id' => $request->resource_id,
            'type' => $type,
            'payload' => $payload,
            'maker' => $request->maker ? [
                'id' => $request->maker->id,
                'name' => $request->maker->full_name,
                'role_slug' => $request->maker->backofficeRole?->slug,
            ] : null,
            'checker' => $request->checker ? [
                'id' => $request->checker->id,
                'name' => $request->checker->full_name,
            ] : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function formatApprovalStatus(ApprovalRequestStatus $status): string
    {
        return match ($status) {
            ApprovalRequestStatus::Pending => 'Pending approval',
            ApprovalRequestStatus::Approved => 'Approved',
            ApprovalRequestStatus::Rejected => 'Rejected',
            ApprovalRequestStatus::Cancelled => 'Cancelled',
        };
    }

    private function formatBatchStatus(string $status): string
    {
        return match ($status) {
            'pending_approval' => 'Pending approval',
            'paid' => 'Within limit',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function formatCommissionPeriodLabel(string $period): string
    {
        try {
            $date = Carbon::createFromFormat('Y-m', $period);

            return $date->format('F Y').' (monthly)';
        } catch (\Throwable) {
            return $period;
        }
    }

    private function formatPnlPeriodLabel(string $period): string
    {
        try {
            $date = Carbon::createFromFormat('Y-m', $period);

            return $date->format('F Y').' — month to date';
        } catch (\Throwable) {
            return 'Month to date';
        }
    }

    private function formatNairaMillions(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return '₦ '.number_format($amount / 1_000_000, 0).'M';
        }

        if ($amount >= 1_000) {
            return '₦ '.number_format($amount / 1_000, 0).'K';
        }

        return '₦ '.number_format($amount, 0);
    }

    /**
     * @return array<string, int>
     */
    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
