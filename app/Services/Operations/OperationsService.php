<?php

namespace App\Services\Operations;

use App\Enums\IncidentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\OperationsIncident;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OperationsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $since24h = now()->subHours(24);

        $last24h = Transaction::query()->where('created_at', '>=', $since24h);
        $totalTxn24h = (int) (clone $last24h)->count();
        $failedTxn24h = (int) (clone $last24h)->where('status', TransactionStatus::Failed)->count();
        $successfulTxn24h = (int) (clone $last24h)->where('status', TransactionStatus::Success)->count();
        $volume24h = (float) (clone $last24h)->sum('amount');

        $openIncidents = OperationsIncident::query()
            ->whereIn('status', [IncidentStatus::Active, IncidentStatus::Monitoring])
            ->count();

        $successRate24h = $totalTxn24h > 0
            ? round(($successfulTxn24h / $totalTxn24h) * 100, 1)
            : 100.0;

        return [
            'kpis' => [
                'success_rate_24h' => $successRate24h,
                'total_txn_24h' => $totalTxn24h,
                'failed_txn_24h' => $failedTxn24h,
                'open_incidents' => $openIncidents,
                'volume_24h' => $volume24h,
            ],
            'channel_performance' => $this->channelPerformance($since24h),
            'volume_series' => $this->volumeSeries(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function channels(): array
    {
        return $this->channelPerformance(now()->subHours(24));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function partners(): array
    {
        $since24h = now()->subHours(24);
        $partners = config('operations.partners', []);

        $failedByBank = Transaction::query()
            ->where('created_at', '>=', $since24h)
            ->where('status', TransactionStatus::Failed)
            ->whereNotNull('counterparty_bank')
            ->select('counterparty_bank', DB::raw('count(*) as failed_count'))
            ->groupBy('counterparty_bank')
            ->pluck('failed_count', 'counterparty_bank');

        $totalByBank = Transaction::query()
            ->where('created_at', '>=', $since24h)
            ->whereNotNull('counterparty_bank')
            ->select('counterparty_bank', DB::raw('count(*) as total_count'))
            ->groupBy('counterparty_bank')
            ->pluck('total_count', 'counterparty_bank');

        return collect($partners)->map(function (array $partner) use ($failedByBank, $totalByBank) {
            $name = $partner['name'];
            $failed = (int) ($failedByBank[$name] ?? 0);
            $total = (int) ($totalByBank[$name] ?? 0);
            $failureRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0.0;

            $status = $partner['status'];
            if ($failureRate >= 15) {
                $status = 'degraded';
            } elseif ($failureRate >= 5) {
                $status = 'warning';
            } elseif ($total > 0 && $failureRate < 2) {
                $status = 'healthy';
            }

            return [
                'code' => $partner['code'],
                'name' => $name,
                'type' => $partner['type'],
                'status' => $status,
                'failure_rate_24h' => $failureRate,
                'failed_txn_24h' => $failed,
                'total_txn_24h' => $total,
                'last_sync_at' => now()->subMinutes(crc32($partner['code']) % 45 + 2)->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function channelPerformance(Carbon $since): array
    {
        $transactions = Transaction::query()
            ->where('created_at', '>=', $since)
            ->get(['type', 'status', 'agent_id', 'meta', 'counterparty_bank']);

        $buckets = [
            'Mobile app' => ['success' => 0, 'total' => 0],
            'USSD' => ['success' => 0, 'total' => 0],
            'POS' => ['success' => 0, 'total' => 0],
            'NIP' => ['success' => 0, 'total' => 0],
            'Other' => ['success' => 0, 'total' => 0],
        ];

        foreach ($transactions as $tx) {
            $label = $this->channelLabelFor($tx);
            $buckets[$label]['total']++;
            if ($tx->status === TransactionStatus::Success) {
                $buckets[$label]['success']++;
            }
        }

        return collect($buckets)
            ->map(function (array $counts, string $label) {
                $rate = $counts['total'] > 0
                    ? round(($counts['success'] / $counts['total']) * 100, 1)
                    : 100.0;

                return [
                    'label' => $label,
                    'success_rate' => $rate,
                    'txn_count' => $counts['total'],
                    'tone' => $rate >= 97 ? 'green' : 'amber',
                ];
            })
            ->filter(fn (array $row) => $row['txn_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function volumeSeries(): array
    {
        $ranges = [
            '6H' => 6,
            '12H' => 12,
            '18H' => 18,
            '24H' => 24,
        ];

        $bucketExpr = $this->hourBucketExpression();
        $series = [];

        foreach ($ranges as $key => $hours) {
            $since = now()->subHours($hours)->startOfHour();

            $rows = Transaction::query()
                ->where('created_at', '>=', $since)
                ->select(
                    DB::raw("{$bucketExpr} as bucket"),
                    DB::raw('sum(amount) as volume'),
                )
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->map(function ($row) {
                    $bucket = Carbon::parse($row->bucket);

                    return [
                        'label' => $bucket->format('H:i'),
                        'volume' => (float) $row->volume,
                    ];
                });

            $series[$key] = [
                'labels' => $rows->pluck('label')->all(),
                'values' => $rows->pluck('volume')->all(),
            ];
        }

        return $series;
    }

    private function hourBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', created_at)",
            'pgsql' => "date_trunc('hour', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
        };
    }

    private function channelLabelFor(Transaction $tx): string
    {
        $meta = $tx->meta ?? [];

        if ($tx->agent_id || ($meta['channel'] ?? null) === 'pos') {
            return 'POS';
        }

        if ($tx->type === TransactionType::Airtime) {
            return 'USSD';
        }

        if (in_array($tx->type, [
            TransactionType::WalletTransferOut,
            TransactionType::WalletTransferIn,
            TransactionType::TransferFee,
        ], true)) {
            $bank = strtolower($tx->counterparty_bank ?? '');
            if ($bank && ! str_contains($bank, 'xpress') && ! str_contains($bank, 'wallet')) {
                return 'NIP';
            }

            return 'Mobile app';
        }

        if (in_array($tx->type, [TransactionType::CashIn, TransactionType::CashOut], true)) {
            return 'POS';
        }

        $bank = strtolower($tx->counterparty_bank ?? '');
        if ($bank && ! str_contains($bank, 'xpress') && ! str_contains($bank, 'wallet')) {
            return 'NIP';
        }

        return 'Other';
    }
}
