<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AmlCaseStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\AmlCase;
use App\Services\Aml\AmlCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmlCaseController extends ApiController
{
    public function __construct(
        private AmlCaseService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (AmlCase $case) => $this->format($case)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(AmlCase $amlCase): JsonResponse
    {
        $case = $this->service->find($amlCase->reference);

        return $this->success($this->format($case, true));
    }

    public function addEvent(Request $request, AmlCase $amlCase): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);

        $event = $this->service->addEvent(
            $amlCase,
            auth('api')->user(),
            $validated['action'],
            $validated['notes'] ?? null,
        );

        return $this->success([
            'event' => [
                'action' => $event->action,
                'notes' => $event->notes,
                'actor' => $event->actor?->full_name,
                'created_at' => $event->created_at?->toIso8601String(),
            ],
        ], 'Case event recorded.', 201);
    }

    public function resolve(Request $request, AmlCase $amlCase): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->close($amlCase, auth('api')->user(), $validated['notes'] ?? null);

        return $this->success($this->format($updated, true), 'Case resolved and closed.');
    }

    public function escalate(Request $request, AmlCase $amlCase): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->escalate($amlCase, auth('api')->user(), $validated['notes'] ?? null);

        return $this->success($this->format($updated, true), 'Case escalated.');
    }

    public function updateStatus(Request $request, AmlCase $amlCase): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(AmlCaseStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->updateStatus(
            $amlCase,
            AmlCaseStatus::from($validated['status']),
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated, true), 'Case status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(AmlCase $case, bool $detailed = false): array
    {
        $metadata = $case->metadata ?? [];

        $data = [
            'id' => $case->reference,
            'reference' => $case->reference,
            'title' => $case->title,
            'status' => $case->status->label(),
            'priority' => $metadata['priority'] ?? null,
            'assignee' => $case->assignee?->full_name,
            'assignee_id' => $case->assignee_id,
            'alerts_linked' => $case->alert_id ? 1 : 0,
            'opened_at' => $case->opened_at?->toIso8601String(),
            'typology' => $metadata['typology'] ?? null,
            'subject' => $metadata['subject_label'] ?? "{$case->subject_type->label()} {$case->subject_id}",
            'subject_type' => $case->subject_type->label(),
            'subject_id' => $case->subject_id,
            'closed_at' => $case->closed_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['summary'] = $case->summary;
            $data['source_alert'] = $case->alert?->reference;
            $data['events'] = $case->events?->map(fn ($event) => [
                'action' => $event->action,
                'notes' => $event->notes,
                'actor' => $event->actor?->full_name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
