<?php

namespace App\Services\Settlement;

use App\Enums\SettlementCycleStatus;
use App\Enums\SettlementExceptionStatus;
use App\Models\FloatPosition;
use App\Models\SettlementCycle;
use App\Models\SettlementException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SettlementService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $openExceptions = SettlementException::query()
            ->where('status', '!=', SettlementExceptionStatus::Resolved)
            ->count();

        $totalTxns = max((int) Transaction::query()->count(), (int) config('settlement.export_queue_txn_count', 0));
        $exceptionBacklog = (int) config('settlement.exception_backlog_total', $openExceptions);
        $matchRate = $totalTxns > 0
            ? round((1 - ($exceptionBacklog / $totalTxns)) * 100, 1)
            : 99.6;

        $pendingAmount = (float) SettlementCycle::query()
            ->whereIn('status', [SettlementCycleStatus::Scheduled, SettlementCycleStatus::InProgress])
            ->sum('amount');

        $treasuryFloat = (float) FloatPosition::query()->sum('balance');
        $floatBalance = $treasuryFloat > 0
            ? $treasuryFloat
            : (float) config('settlement.float_balance', 0);
        $floatFloor = (float) config('settlement.float_floor', 0);

        return [
            'export_queue' => [
                'label' => 'Export queue',
                'amount' => (float) config('settlement.export_queue_amount', 0),
                'txn_count' => (int) config('settlement.export_queue_txn_count', 0),
                'sub' => number_format(config('settlement.export_queue_txn_count', 0)).' txns',
                'sub_tone' => 'muted',
            ],
            'match_rate' => [
                'label' => 'Match rate',
                'value' => $matchRate,
                'value_label' => number_format($matchRate, 1).'%',
                'exception_count' => (int) config('settlement.exception_backlog_total', $openExceptions),
                'sub' => number_format(config('settlement.exception_backlog_total', $openExceptions)).' exceptions',
                'sub_tone' => 'red',
            ],
            'float_balance' => [
                'label' => 'Float balance',
                'amount' => $floatBalance,
                'floor' => $floatFloor,
                'sub' => $floatBalance >= $floatFloor
                    ? 'Above ₦'.number_format($floatFloor / 1_000_000).'M floor'
                    : 'Below floor',
                'sub_tone' => $floatBalance >= $floatFloor ? 'green' : 'red',
            ],
            'pending_settlement' => [
                'label' => 'Pending settlement',
                'amount' => $pendingAmount > 0 ? $pendingAmount : 87_000_000,
                'sub' => 'T+0 window',
                'sub_tone' => 'muted',
            ],
            'category_counts' => config('settlement.category_counts', []),
            'open_exceptions' => $openExceptions,
        ];
    }

    /**
     * @return array<int, SettlementCycle>
     */
    public function listCycles(): array
    {
        return SettlementCycle::query()
            ->orderBy('scheduled_at')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listExceptions(array $filters = []): LengthAwarePaginator
    {
        $query = SettlementException::query()
            ->with(['cycle', 'maker', 'checker', 'resolvedBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array{all: int, failed_credit: int, duplicate: int, unmatched: int}
     */
    public function exceptionCounts(): array
    {
        $configured = config('settlement.category_counts', []);
        $openByCategory = SettlementException::query()
            ->where('status', '!=', SettlementExceptionStatus::Resolved)
            ->selectRaw('category, count(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $failedCredit = max((int) ($openByCategory['failed_credit'] ?? 0), (int) ($configured['failed_credit'] ?? 0));
        $duplicate = max((int) ($openByCategory['duplicate'] ?? 0), (int) ($configured['duplicate'] ?? 0));
        $unmatched = max((int) ($openByCategory['unmatched'] ?? 0), (int) ($configured['unmatched'] ?? 0));
        $all = max(
            (int) config('settlement.exception_backlog_total', $failedCredit + $duplicate + $unmatched),
            $failedCredit + $duplicate + $unmatched,
        );

        return [
            'all' => $all,
            'failed_credit' => $failedCredit,
            'duplicate' => $duplicate,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SettlementException>
     */
    public function exportQuery(?string $category = null)
    {
        $query = SettlementException::query()
            ->with(['cycle'])
            ->orderByDesc('created_at');

        if ($category) {
            $query->where('category', $category);
        }

        return $query;
    }

    public function runEod(User $actor): array
    {
        $inProgress = SettlementCycle::query()
            ->where('status', SettlementCycleStatus::InProgress)
            ->orderBy('scheduled_at')
            ->first();

        if (! $inProgress) {
            throw new InvalidArgumentException('No settlement cycle is currently in progress.');
        }

        $inProgress->update([
            'status' => SettlementCycleStatus::Settled,
            'settled_at' => now(),
        ]);

        $nextScheduled = $this->createNextCycle($inProgress);

        $this->auditLog->record(
            $actor,
            'settlement.eod.run',
            'SettlementCycle',
            $inProgress->reference,
            "EOD run completed for {$inProgress->reference}",
            [
                'settled_cycle' => $inProgress->reference,
                'settled_amount' => (float) $inProgress->amount,
                'next_cycle' => $nextScheduled->reference,
            ],
        );

        return [
            'settled_cycle' => $inProgress->fresh(),
            'next_cycle' => $nextScheduled,
        ];
    }

    private function createNextCycle(SettlementCycle $previous): SettlementCycle
    {
        $scheduledAt = Carbon::parse($previous->scheduled_at)->addDay();
        $sequence = SettlementCycle::query()->count() + 1;
        $hour = $scheduledAt->format('H:i');

        return SettlementCycle::query()->create([
            'reference' => sprintf('CYC-%d-%03d', now()->year, $sequence),
            'label' => "Cycle {$sequence} · {$hour}",
            'scheduled_at' => $scheduledAt,
            'amount' => (float) ($previous->metadata['estimated_amount'] ?? $previous->amount * 0.4),
            'txn_count' => (int) ($previous->metadata['estimated_txn_count'] ?? (int) round($previous->txn_count * 0.35)),
            'channel' => config('settlement.channel', 'NIBSS'),
            'status' => SettlementCycleStatus::Scheduled,
            'metadata' => [
                'detail' => number_format($previous->metadata['estimated_txn_count'] ?? 0).' txns (est.)',
                'estimated' => true,
            ],
        ]);
    }
}
