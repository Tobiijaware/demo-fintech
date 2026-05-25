<?php

namespace App\Services\Settlement;

use App\Enums\TransactionStatus;
use App\Models\PartnerBank;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PartnerBankService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $since24h = now()->subHours(24);

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

        return PartnerBank::query()
            ->orderBy('name')
            ->get()
            ->map(function (PartnerBank $bank) use ($failedByBank, $totalByBank) {
                $name = $bank->name;
                $aliases = array_merge([$name], $bank->metadata['aliases'] ?? []);
                $failed = 0;
                $total = 0;

                foreach ($aliases as $alias) {
                    $failed += (int) ($failedByBank[$alias] ?? 0);
                    $total += (int) ($totalByBank[$alias] ?? 0);
                }

                $failureRate = $total > 0
                    ? round(($failed / $total) * 100, 1)
                    : (float) $bank->failure_rate_24h;

                $slaStatus = $bank->sla_status;
                if ($failureRate >= 15) {
                    $slaStatus = 'degraded';
                } elseif ($failureRate >= 5) {
                    $slaStatus = 'warning';
                } elseif ($total > 0 && $failureRate < 2) {
                    $slaStatus = 'healthy';
                }

                return [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'account_number' => $bank->account_number,
                    'settlement_window' => $bank->settlement_window,
                    'sla_status' => $slaStatus,
                    'failure_rate_24h' => $failureRate,
                    'failed_txn_24h' => $failed,
                    'total_txn_24h' => $total,
                    'metadata' => $bank->metadata,
                ];
            })
            ->values()
            ->all();
    }
}
