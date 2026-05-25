<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\SettlementCycle;
use App\Services\Settlement\PartnerBankService;
use Illuminate\Http\JsonResponse;

class PartnerBankController extends ApiController
{
    public function __construct(
        private PartnerBankService $service,
    ) {}

    public function index(): JsonResponse
    {
        $lastCycle = SettlementCycle::query()
            ->whereNotNull('settled_at')
            ->orderByDesc('settled_at')
            ->value('label')
            ?? SettlementCycle::query()->orderByDesc('scheduled_at')->value('label');

        $items = collect($this->service->list())
            ->map(fn (array $bank) => $this->formatPartner($bank, $lastCycle));

        return $this->success([
            'items' => $items,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $bank
     * @return array<string, mixed>
     */
    private function formatPartner(array $bank, ?string $lastCycle): array
    {
        $failureRate = (float) ($bank['failure_rate_24h'] ?? 0);
        $slaPct = max(0, min(100, round(100 - $failureRate, 1)));

        $status = match ($bank['sla_status'] ?? 'healthy') {
            'degraded' => 'degraded',
            'warning' => 'watch',
            default => 'operational',
        };

        $statusLabel = match ($status) {
            'degraded' => 'Delayed confirmations',
            'watch' => 'Elevated failure rate',
            default => 'Operational',
        };

        $metadata = $bank['metadata'] ?? [];

        return [
            'id' => strtolower(str_replace(' ', '-', (string) $bank['name'])),
            'name' => $bank['name'],
            'partner_type' => $metadata['partner_type'] ?? 'Bank',
            'settlement_account' => $bank['account_number'],
            'status' => $status,
            'status_label' => $statusLabel,
            'sla_pct' => $slaPct,
            'last_cycle' => $lastCycle,
            'contact' => $metadata['contact'] ?? null,
            'notes' => $metadata['notes'] ?? null,
            'failure_rate_24h' => $failureRate,
            'settlement_window' => $bank['settlement_window'] ?? null,
        ];
    }
}
