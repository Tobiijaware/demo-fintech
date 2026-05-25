<?php

namespace App\Services\Aml;

use App\Enums\AmlAlertStatus;
use App\Enums\AmlCaseStatus;
use App\Models\AmlAlert;
use App\Models\AmlCase;
use App\Models\AmlCaseEvent;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class AmlCaseService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AmlCase::query()
            ->with(['assignee', 'alert'])
            ->withCount('events')
            ->orderByDesc('opened_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('subject_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(string $reference): AmlCase
    {
        return AmlCase::query()
            ->with(['assignee', 'alert', 'events.actor'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromAlert(AmlAlert $alert, User $actor, array $data = []): AmlCase
    {
        $case = AmlCase::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'alert_id' => $alert->id,
            'title' => $data['title'] ?? $alert->title,
            'summary' => $data['summary'] ?? $alert->narrative,
            'status' => AmlCaseStatus::Open,
            'assignee_id' => $data['assignee_id'] ?? $alert->assignee_id ?? $actor->id,
            'subject_type' => $alert->subject_type,
            'subject_id' => $alert->subject_id,
            'opened_at' => now(),
            'metadata' => array_merge($alert->metadata ?? [], [
                'typology' => $alert->typology,
                'source_alert' => $alert->reference,
            ]),
        ]);

        $this->addEvent($case, $actor, 'case_created', "Converted from alert {$alert->reference}");

        $alert->update(['status' => AmlAlertStatus::Assigned]);

        return $case->fresh(['assignee', 'alert', 'events.actor']);
    }

    public function updateStatus(AmlCase $case, AmlCaseStatus $status, User $actor, ?string $notes = null): AmlCase
    {
        if ($case->status === AmlCaseStatus::Closed) {
            throw new InvalidArgumentException('Closed cases cannot be updated.');
        }

        $case->update(['status' => $status]);
        $this->addEvent($case, $actor, 'status_updated', $notes ?? "Status changed to {$status->label()}");

        return $case->fresh(['assignee', 'alert', 'events.actor']);
    }

    public function addEvent(AmlCase $case, User $actor, string $action, ?string $notes = null): AmlCaseEvent
    {
        return AmlCaseEvent::query()->create([
            'case_id' => $case->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function escalate(AmlCase $case, User $actor, ?string $notes = null): AmlCase
    {
        if ($case->status === AmlCaseStatus::Closed) {
            throw new InvalidArgumentException('Closed cases cannot be escalated.');
        }

        $case->update(['status' => AmlCaseStatus::Escalated]);
        $this->addEvent($case, $actor, 'escalated', $notes ?? 'Case escalated to compliance');

        return $case->fresh(['assignee', 'alert', 'events.actor']);
    }

    public function close(AmlCase $case, User $actor, ?string $notes = null): AmlCase
    {
        if ($case->status === AmlCaseStatus::Closed) {
            throw new InvalidArgumentException('Case is already closed.');
        }

        $case->update([
            'status' => AmlCaseStatus::Closed,
            'closed_at' => now(),
        ]);

        $this->addEvent($case, $actor, 'closed', $notes ?? 'Case closed');

        $this->auditLog->record(
            $actor,
            'aml.case.closed',
            'AmlCase',
            $case->reference,
            "Closed AML case {$case->reference}",
            [
                'reference' => $case->reference,
                'subject_type' => $case->subject_type?->value,
                'subject_id' => $case->subject_id,
                'notes' => $notes,
            ],
        );

        return $case->fresh(['assignee', 'alert', 'events.actor']);
    }

    public function generateReference(): string
    {
        $year = now()->year;
        $latest = AmlCase::query()
            ->where('reference', 'like', "CASE-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1100;
        if ($latest && preg_match('/^CASE-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('CASE-%d-%04d', $year, $sequence);
            $sequence++;
        } while (AmlCase::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
