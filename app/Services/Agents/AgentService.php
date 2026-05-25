<?php

namespace App\Services\Agents;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AgentService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Agent::query()
            ->withCount('terminals')
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['region'])) {
            $query->where('region', $filters['region']);
        }

        if (! empty($filters['hub'])) {
            $query->where('hub', $filters['hub']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('proprietor_name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(int $id): Agent
    {
        return Agent::query()
            ->with(['onboardingApplication', 'user', 'terminals'])
            ->withCount('terminals')
            ->findOrFail($id);
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $base = Agent::query();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', AgentStatus::Active)->count(),
            'suspended' => (clone $base)->where('status', AgentStatus::Suspended)->count(),
            'pending' => (clone $base)->where('status', AgentStatus::Pending)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Agent $agent, array $data, User $actor): Agent
    {
        $before = [
            'status' => $agent->status->value,
            'hub' => $agent->hub,
            'region' => $agent->region,
            'tier' => $agent->tier->value,
        ];

        $agent->update($data);

        $updated = $agent->fresh(['onboardingApplication', 'user'])->loadCount('terminals');

        $this->auditLog->record(
            $actor,
            'agent.updated',
            'Agent',
            (string) $updated->id,
            "Updated agent {$updated->code}",
            [
                'code' => $updated->code,
                'before' => $before,
                'after' => [
                    'status' => $updated->status->value,
                    'hub' => $updated->hub,
                    'region' => $updated->region,
                    'tier' => $updated->tier->value,
                ],
            ],
        );

        return $updated;
    }
}
