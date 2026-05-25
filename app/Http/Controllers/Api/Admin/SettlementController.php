<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SettlementCycleStatus;
use App\Enums\SettlementExceptionCategory;
use App\Enums\SettlementExceptionStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\SettlementCycle;
use App\Models\SettlementException;
use App\Services\Settlement\SettlementExceptionService;
use App\Services\Settlement\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends ApiController
{
    public function __construct(
        private SettlementService $settlementService,
        private SettlementExceptionService $exceptionService,
    ) {}

    public function stats(): JsonResponse
    {
        $raw = $this->settlementService->stats();

        return $this->success([
            'kpis' => [
                [
                    'label' => $raw['export_queue']['label'],
                    'value' => $this->formatNaira($raw['export_queue']['amount'], 'B'),
                    'sub' => $raw['export_queue']['sub'],
                    'sub_tone' => $raw['export_queue']['sub_tone'],
                ],
                [
                    'label' => $raw['match_rate']['label'],
                    'value' => $raw['match_rate']['value_label'],
                    'sub' => $raw['match_rate']['sub'],
                    'sub_tone' => $raw['match_rate']['sub_tone'],
                ],
                [
                    'label' => $raw['float_balance']['label'],
                    'value' => $this->formatNaira($raw['float_balance']['amount'], 'M'),
                    'sub' => $raw['float_balance']['sub'],
                    'sub_tone' => $raw['float_balance']['sub_tone'],
                ],
                [
                    'label' => $raw['pending_settlement']['label'],
                    'value' => $this->formatNaira($raw['pending_settlement']['amount'], 'M'),
                    'sub' => $raw['pending_settlement']['sub'],
                    'sub_tone' => $raw['pending_settlement']['sub_tone'],
                ],
            ],
            'exception_counts' => $this->settlementService->exceptionCounts(),
        ]);
    }

    public function cycles(): JsonResponse
    {
        $cycles = collect($this->settlementService->listCycles())
            ->map(fn (SettlementCycle $cycle) => $this->formatCycle($cycle));

        return $this->success([
            'items' => $cycles,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function runEod(): JsonResponse
    {
        try {
            $result = $this->settlementService->runEod(auth('api')->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'settled_cycle' => $this->formatCycle($result['settled_cycle']),
            'next_cycle' => $this->formatCycle($result['next_cycle']),
        ], 'EOD settlement run completed.');
    }

    public function exceptions(Request $request): JsonResponse
    {
        $paginator = $this->settlementService->listExceptions($request->only([
            'category', 'status', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(
                fn (SettlementException $exception) => $this->formatException($exception),
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $this->settlementService->exceptionCounts(),
        ]);
    }

    public function showException(string $reference): JsonResponse
    {
        $exception = $this->exceptionService->find($reference);

        return $this->success($this->formatException($exception, true));
    }

    public function resolveException(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exception = $this->exceptionService->find($reference);

        try {
            $updated = $this->exceptionService->resolve(
                $exception,
                auth('api')->user(),
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->formatException($updated, true), 'Exception resolved.');
    }

    public function creditException(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'wallet_account' => ['nullable', 'string', 'max:32'],
            'wallet_id' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($validated['wallet_account']) && ! empty($validated['wallet_id'])) {
            $validated['wallet_account'] = $validated['wallet_id'];
        }

        $exception = $this->exceptionService->find($reference);

        try {
            $result = $this->exceptionService->creditWallet(
                $exception,
                auth('api')->user(),
                $validated,
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        if ($result['mode'] === 'approval_required') {
            $approval = $result['approval_request'];

            return $this->success([
                'mode' => 'approval_required',
                'approval_request' => [
                    'id' => $approval->id,
                    'status' => $approval->status->value,
                    'summary' => $approval->summary,
                    'maker' => $approval->maker ? [
                        'id' => $approval->maker->id,
                        'name' => trim("{$approval->maker->firstname} {$approval->maker->lastname}"),
                    ] : null,
                ],
                'exception' => $this->formatException($result['exception'], true),
            ], 'Manual credit submitted for checker approval.', 202);
        }

        return $this->success([
            'mode' => 'direct',
            'exception' => $this->formatException($result['exception'], true),
        ], 'Manual credit posted.');
    }

    public function addExceptionEvent(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exception = $this->exceptionService->find($reference);
        $event = $this->exceptionService->addEvent(
            $exception,
            auth('api')->user(),
            $validated['action'],
            $validated['notes'] ?? null,
        );
        $exception = $this->exceptionService->find($reference);

        return $this->success([
            'event' => [
                'time' => $event->created_at?->format('H:i T'),
                'action' => $event->action,
                'actor' => $event->actor
                    ? trim("{$event->actor->firstname} {$event->actor->lastname}")
                    : null,
            ],
            'exception' => $this->formatException($exception, true),
        ], 'Event recorded.');
    }

    public function export(Request $request): StreamedResponse
    {
        $category = $request->string('category')->toString() ?: null;
        $filename = 'settlement-exceptions-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($category) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'reference',
                'transaction_reference',
                'title',
                'category',
                'status',
                'amount',
                'cycle_reference',
                'summary',
                'recommended_action',
                'resolved_at',
                'created_at',
            ]);

            $this->settlementService->exportQuery($category)
                ->chunk(200, function ($exceptions) use ($handle) {
                    foreach ($exceptions as $exception) {
                        fputcsv($handle, [
                            $exception->reference,
                            $exception->transaction_reference,
                            $exception->title,
                            $exception->category->value,
                            $exception->status->value,
                            (float) $exception->amount,
                            $exception->cycle?->reference,
                            $exception->summary,
                            $exception->recommended_action,
                            $exception->resolved_at?->toIso8601String(),
                            $exception->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCycle(SettlementCycle $cycle): array
    {
        $metadata = $cycle->metadata ?? [];
        $detail = $metadata['detail'] ?? number_format($cycle->txn_count).' txns · '.$cycle->channel;

        return [
            'id' => $cycle->reference,
            'reference' => $cycle->reference,
            'label' => $cycle->label,
            'amount' => $this->formatNaira((float) $cycle->amount, 'B'),
            'amount_ngn' => (float) $cycle->amount,
            'txn_count' => $cycle->txn_count,
            'detail' => $detail,
            'partner' => $cycle->channel,
            'channel' => $cycle->channel,
            'status' => match ($cycle->status) {
                SettlementCycleStatus::Settled => 'Settled',
                SettlementCycleStatus::InProgress => 'In progress',
                SettlementCycleStatus::Scheduled => 'Scheduled',
            },
            'status_raw' => $cycle->status->value,
            'scheduled_at' => $cycle->scheduled_at?->toIso8601String(),
            'settled_at' => $cycle->settled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatException(SettlementException $exception, bool $detailed = false): array
    {
        $displayStatus = match ($exception->category) {
            SettlementExceptionCategory::FailedCredit => 'Failed',
            SettlementExceptionCategory::Duplicate => 'Duplicate',
            SettlementExceptionCategory::Unmatched => 'Unmatched',
            SettlementExceptionCategory::Pending => 'Pending',
        };

        $statusLabel = match ($exception->status) {
            SettlementExceptionStatus::Resolved => 'Resolved',
            SettlementExceptionStatus::InInvestigation => 'In investigation',
            SettlementExceptionStatus::PendingPartner => 'Pending partner',
            SettlementExceptionStatus::Open => match ($exception->category) {
                SettlementExceptionCategory::FailedCredit => 'Failed',
                SettlementExceptionCategory::Duplicate => 'Awaiting review',
                SettlementExceptionCategory::Unmatched => 'Open',
                SettlementExceptionCategory::Pending => 'Pending partner',
            },
        };

        $payload = [
            'id' => $exception->reference,
            'reference' => $exception->reference,
            'transaction_reference' => $exception->transaction_reference,
            'title' => $exception->title,
            'sub' => $this->exceptionSub($exception),
            'amount' => (float) $exception->amount,
            'amount_ngn' => (float) $exception->amount,
            'status' => $displayStatus,
            'status_raw' => $exception->status->value,
            'category' => $exception->category->value,
            'drawer_title' => $exception->title,
            'status_label' => $statusLabel,
            'age_label' => $exception->created_at?->diffForHumans(short: true).' old',
            'summary' => $exception->summary,
            'recommended_action' => $exception->recommended_action,
            'trace' => $this->formatTrace($exception->trace ?? []),
            'cycle' => $exception->cycle ? [
                'reference' => $exception->cycle->reference,
                'label' => $exception->cycle->label,
            ] : null,
            'resolved_at' => $exception->resolved_at?->toIso8601String(),
            'created_at' => $exception->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['timeline'] = $exception->events
                ->map(fn ($event) => [
                    'time' => $event->created_at?->format('H:i T'),
                    'action' => $event->action,
                    'actor' => $event->actor
                        ? trim("{$event->actor->firstname} {$event->actor->lastname}")
                        : 'System',
                    'notes' => $event->notes,
                ])
                ->values()
                ->all();

            $payload['resolved_by'] = $exception->resolvedBy ? [
                'id' => $exception->resolvedBy->id,
                'name' => trim("{$exception->resolvedBy->firstname} {$exception->resolvedBy->lastname}"),
            ] : null;

            $payload['maker'] = $exception->maker ? [
                'id' => $exception->maker->id,
                'name' => trim("{$exception->maker->firstname} {$exception->maker->lastname}"),
            ] : null;

            $payload['checker'] = $exception->checker ? [
                'id' => $exception->checker->id,
                'name' => trim("{$exception->checker->firstname} {$exception->checker->lastname}"),
            ] : null;
        }

        return $payload;
    }

    private function exceptionSub(SettlementException $exception): string
    {
        return match ($exception->category) {
            SettlementExceptionCategory::FailedCredit => 'Debited at switch, not credited',
            SettlementExceptionCategory::Duplicate => 'Same reference posted twice',
            SettlementExceptionCategory::Unmatched => 'Switch posted, GL not posted',
            SettlementExceptionCategory::Pending => 'Customer-initiated chargeback',
        };
    }

    /**
     * @param  array<string, mixed>  $trace
     * @return array<string, string|null>
     */
    private function formatTrace(array $trace): array
    {
        return [
            'nip_reference' => (string) ($trace['nip_reference'] ?? $trace['nipReference'] ?? ''),
            'amount' => (string) ($trace['amount'] ?? ''),
            'sender' => (string) ($trace['sender'] ?? ''),
            'beneficiary' => (string) ($trace['beneficiary'] ?? ''),
            'switch_time' => (string) ($trace['switch_time'] ?? $trace['switchTime'] ?? ''),
            'switch_status' => (string) ($trace['switch_status'] ?? $trace['switchStatus'] ?? ''),
            'wallet_posting' => (string) ($trace['wallet_posting'] ?? $trace['walletPosting'] ?? ''),
            'nibss_confirmation' => (string) ($trace['nibss_confirmation'] ?? $trace['nibssConfirmation'] ?? ''),
        ];
    }

    private function formatNaira(float $amount, string $unit = 'M'): string
    {
        $divisor = match ($unit) {
            'B' => 1_000_000_000,
            'M' => 1_000_000,
            default => 1,
        };

        $value = $amount / $divisor;

        return '₦ '.number_format($value, $unit === 'B' ? 2 : 0).$unit;
    }
}
