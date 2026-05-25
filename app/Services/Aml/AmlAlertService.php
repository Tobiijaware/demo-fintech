<?php

namespace App\Services\Aml;

use App\Enums\AmlAlertStatus;
use App\Enums\AmlCaseStatus;
use App\Models\AmlAlert;
use App\Models\AmlCase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class AmlAlertService
{
    public function __construct(
        private AmlCaseService $caseService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AmlAlert::query()
            ->with('assignee')
            ->orderByDesc('created_at');

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
                    ->orWhere('subject_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(string $reference): AmlAlert
    {
        return AmlAlert::query()
            ->with(['assignee', 'cases'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function assign(AmlAlert $alert, User $assignee): AmlAlert
    {
        if ($alert->status === AmlAlertStatus::Closed) {
            throw new InvalidArgumentException('Closed alerts cannot be assigned.');
        }

        $alert->update([
            'assignee_id' => $assignee->id,
            'status' => AmlAlertStatus::Assigned,
        ]);

        return $alert->fresh(['assignee']);
    }

    public function escalate(AmlAlert $alert, ?string $notes = null): AmlAlert
    {
        if ($alert->status === AmlAlertStatus::Closed) {
            throw new InvalidArgumentException('Closed alerts cannot be escalated.');
        }

        $metadata = $alert->metadata ?? [];
        if ($notes) {
            $metadata['escalation_notes'] = $notes;
        }

        $alert->update([
            'status' => AmlAlertStatus::Escalated,
            'metadata' => $metadata,
        ]);

        return $alert->fresh(['assignee']);
    }

    public function close(AmlAlert $alert, ?string $notes = null): AmlAlert
    {
        if ($alert->status === AmlAlertStatus::Closed) {
            throw new InvalidArgumentException('Alert is already closed.');
        }

        $metadata = $alert->metadata ?? [];
        if ($notes) {
            $metadata['close_notes'] = $notes;
        }

        $alert->update([
            'status' => AmlAlertStatus::Closed,
            'metadata' => $metadata,
        ]);

        return $alert->fresh(['assignee']);
    }

    public function convertToCase(AmlAlert $alert, User $actor, ?array $data = null): AmlCase
    {
        if ($alert->status === AmlAlertStatus::Closed) {
            throw new InvalidArgumentException('Closed alerts cannot be converted to cases.');
        }

        return $this->caseService->createFromAlert($alert, $actor, $data ?? []);
    }

    public function generateReference(): string
    {
        $year = now()->year;
        $latest = AmlAlert::query()
            ->where('reference', 'like', "ALT-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 8700;
        if ($latest && preg_match('/^ALT-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('ALT-%d-%04d', $year, $sequence);
            $sequence++;
        } while (AmlAlert::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
