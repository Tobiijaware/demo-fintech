<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\OperationsIncident;
use App\Services\Operations\OperationsIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsIncidentController extends ApiController
{
    public function __construct(
        private OperationsIncidentService $service,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'severity', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (OperationsIncident $incident) => $this->format($incident)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'severity' => ['required', 'string', Rule::enum(IncidentSeverity::class)],
            'status' => ['nullable', 'string', Rule::enum(IncidentStatus::class)],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_role' => ['nullable', 'string', 'max:255'],
            'impact' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
        ]);

        if (isset($validated['severity'])) {
            $validated['severity'] = IncidentSeverity::from($validated['severity']);
        }
        if (isset($validated['status'])) {
            $validated['status'] = IncidentStatus::from($validated['status']);
        }

        $incident = $this->service->declare($validated, auth('api')->user());

        return $this->success($this->format($incident, true), 'Incident declared.', 201);
    }

    public function show(string $reference): JsonResponse
    {
        $incident = $this->service->find($reference);

        return $this->success($this->format($incident, true));
    }

    public function events(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:2000'],
            'actor_name' => ['nullable', 'string', 'max:255'],
        ]);

        $incident = $this->service->find($reference);
        $actor = auth('api')->user();
        $actorName = $validated['actor_name'] ?? trim("{$actor->firstname} {$actor->lastname}");

        $event = $this->service->addEvent($incident, $actorName, $validated['action']);
        $incident = $this->service->find($reference);

        return $this->success([
            'event' => [
                'time' => $event->created_at?->format('H:i T'),
                'action' => $event->action,
                'actor' => $event->actor_name,
            ],
            'incident' => $this->format($incident, true),
        ], 'Event recorded.');
    }

    public function resolve(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident = $this->service->find($reference);
        $incident = $this->service->resolve(
            $incident,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($incident, true), 'Incident resolved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(OperationsIncident $incident, bool $includeTimeline = false): array
    {
        $startedAgo = $incident->started_at?->diffForHumans(short: true);
        $statusLabel = match ($incident->status) {
            IncidentStatus::Active => 'Active · '.$startedAgo,
            IncidentStatus::Monitoring => 'Monitoring · '.$startedAgo,
            IncidentStatus::Resolved => 'Resolved · '.$incident->resolved_at?->diffForHumans(short: true),
        };

        $payload = [
            'id' => $incident->reference,
            'reference' => $incident->reference,
            'title' => $incident->title,
            'sub' => $incident->summary ? substr($incident->summary, 0, 80) : null,
            'severity' => $incident->severity->value,
            'started' => $incident->started_at?->format('H:i T'),
            'started_at' => $incident->started_at?->toIso8601String(),
            'owner' => $incident->owner_name,
            'owner_detail' => $incident->owner_role
                ? "{$incident->owner_name} · {$incident->owner_role}"
                : $incident->owner_name,
            'owner_name' => $incident->owner_name,
            'owner_role' => $incident->owner_role,
            'status' => $incident->status->value,
            'status_label' => $statusLabel,
            'summary' => $incident->summary,
            'impact' => $incident->impact,
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'declared_by' => $incident->declaredBy ? [
                'id' => $incident->declaredBy->id,
                'name' => trim("{$incident->declaredBy->firstname} {$incident->declaredBy->lastname}"),
                'email' => $incident->declaredBy->email,
            ] : null,
        ];

        if ($includeTimeline) {
            $payload['timeline'] = $incident->events
                ->map(fn ($event) => [
                    'time' => $event->created_at?->format('H:i T'),
                    'action' => $event->action,
                    'actor' => $event->actor_name,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }
}
