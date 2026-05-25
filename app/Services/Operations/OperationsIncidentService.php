<?php

namespace App\Services\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\OperationsIncident;
use App\Models\OperationsIncidentEvent;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class OperationsIncidentService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = OperationsIncident::query()
            ->with(['declaredBy', 'events'])
            ->orderByDesc('started_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $open = OperationsIncident::query()
            ->whereIn('status', [IncidentStatus::Active, IncidentStatus::Monitoring]);

        return [
            'open' => (clone $open)->count(),
            'active' => OperationsIncident::query()->where('status', IncidentStatus::Active)->count(),
            'monitoring' => OperationsIncident::query()->where('status', IncidentStatus::Monitoring)->count(),
            'resolved_24h' => OperationsIncident::query()
                ->where('status', IncidentStatus::Resolved)
                ->where('resolved_at', '>=', now()->subDay())
                ->count(),
            'p1_open' => (clone $open)->where('severity', IncidentSeverity::P1)->count(),
            'p2_open' => (clone $open)->where('severity', IncidentSeverity::P2)->count(),
            'p3_open' => (clone $open)->where('severity', IncidentSeverity::P3)->count(),
        ];
    }

    public function find(string $reference): OperationsIncident
    {
        return OperationsIncident::query()
            ->with(['declaredBy', 'events'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function declare(array $data, User $actor): OperationsIncident
    {
        $incident = OperationsIncident::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'severity' => $data['severity'],
            'status' => $data['status'] ?? IncidentStatus::Active,
            'owner_name' => $data['owner_name'],
            'owner_role' => $data['owner_role'] ?? null,
            'impact' => $data['impact'] ?? null,
            'started_at' => isset($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
            'declared_by_id' => $actor->id,
        ]);

        $actorName = trim("{$actor->firstname} {$actor->lastname}");
        $this->recordEvent($incident, $actorName, 'Incident declared');

        $this->auditLog->record(
            $actor,
            'operations.incident.declared',
            'OperationsIncident',
            $incident->reference,
            "Declared incident {$incident->reference}: {$incident->title}",
            [
                'reference' => $incident->reference,
                'severity' => $incident->severity->value,
            ],
        );

        return $incident->fresh(['declaredBy', 'events']);
    }

    public function updateStatus(OperationsIncident $incident, IncidentStatus $status, User $actor): OperationsIncident
    {
        $incident->update(['status' => $status]);

        $actorName = trim("{$actor->firstname} {$actor->lastname}");
        $this->recordEvent($incident, $actorName, "Status updated to {$status->value}");

        return $incident->fresh(['declaredBy', 'events']);
    }

    public function addEvent(OperationsIncident $incident, string $actorName, string $action): OperationsIncidentEvent
    {
        return $this->recordEvent($incident, $actorName, $action);
    }

    public function resolve(OperationsIncident $incident, User $actor, ?string $notes = null): OperationsIncident
    {
        if ($incident->status === IncidentStatus::Resolved) {
            throw new InvalidArgumentException('Incident is already resolved.');
        }

        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $actorName = trim("{$actor->firstname} {$actor->lastname}");
        $this->recordEvent($incident, $actorName, $notes ?? 'Incident resolved');

        $this->auditLog->record(
            $actor,
            'operations.incident.resolved',
            'OperationsIncident',
            $incident->reference,
            "Resolved incident {$incident->reference}",
            [
                'reference' => $incident->reference,
                'notes' => $notes,
            ],
        );

        return $incident->fresh(['declaredBy', 'events']);
    }

    public function generateReference(): string
    {
        $year = now()->format('Y');
        $latest = OperationsIncident::query()
            ->where('reference', 'like', "INC-{$year}-%")
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 4881;

        if ($latest && preg_match('/^INC-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('INC-%s-%05d', $year, $sequence);
            $sequence++;
        } while (OperationsIncident::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function recordEvent(OperationsIncident $incident, string $actorName, string $action): OperationsIncidentEvent
    {
        return OperationsIncidentEvent::query()->create([
            'incident_id' => $incident->id,
            'actor_name' => $actorName,
            'action' => $action,
            'created_at' => now(),
        ]);
    }
}
