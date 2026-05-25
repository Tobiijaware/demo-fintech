<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AmlAlert;
use App\Models\User;
use App\Services\Aml\AmlAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmlAlertController extends ApiController
{
    public function __construct(
        private AmlAlertService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'severity', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (AmlAlert $alert) => $this->format($alert)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(AmlAlert $amlAlert): JsonResponse
    {
        $alert = $this->service->find($amlAlert->reference);

        return $this->success($this->format($alert, true));
    }

    public function assign(Request $request, AmlAlert $amlAlert): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::query()->findOrFail($validated['assignee_id']);
        $updated = $this->service->assign($amlAlert, $assignee);

        return $this->success($this->format($updated), 'Alert assigned.');
    }

    public function escalate(Request $request, AmlAlert $amlAlert): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->escalate($amlAlert, $validated['notes'] ?? null);

        return $this->success($this->format($updated), 'Alert escalated.');
    }

    public function close(Request $request, AmlAlert $amlAlert): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->close($amlAlert, $validated['notes'] ?? null);

        return $this->success($this->format($updated), 'Alert closed.');
    }

    public function convertToCase(Request $request, AmlAlert $amlAlert): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $case = $this->service->convertToCase($amlAlert, auth('api')->user(), $validated);

        return $this->success([
            'case' => $this->formatCase($case),
            'alert' => $this->format($amlAlert->fresh(['assignee'])),
        ], 'Alert converted to case.', 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(AmlAlert $alert, bool $detailed = false): array
    {
        $data = [
            'id' => $alert->reference,
            'reference' => $alert->reference,
            'severity' => $alert->severity->label(),
            'title' => $alert->title,
            'sub' => $alert->metadata['sub'] ?? null,
            'typology' => $alert->typology,
            'score' => $alert->score,
            'subject_type' => $alert->subject_type->label(),
            'subject_id' => $alert->subject_id,
            'status' => $alert->status->value,
            'assignee' => $alert->assignee?->full_name,
            'assignee_id' => $alert->assignee_id,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['narrative'] = $alert->narrative;
            $data['metadata'] = $alert->metadata;
            $data['linked_cases'] = $alert->cases?->map(fn ($case) => [
                'id' => $case->reference,
                'title' => $case->title,
                'status' => $case->status->label(),
            ])->values()->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCase(\App\Models\AmlCase $case): array
    {
        return [
            'id' => $case->reference,
            'reference' => $case->reference,
            'title' => $case->title,
            'status' => $case->status->label(),
            'assignee' => $case->assignee?->full_name,
            'opened_at' => $case->opened_at?->toIso8601String(),
        ];
    }
}
