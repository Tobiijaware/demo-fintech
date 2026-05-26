<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditLogService
{
    /** @var array<int, string> */
    private const PRIVILEGED_ACTION_PREFIXES = [
        'role.',
        'user.',
        'maker_checker_policy.',
        'onboarding.',
        'agent.',
        'support.',
        'transaction.',
        'operations.',
        'settlement.',
        'treasury.',
        'compliance.',
        'aml.',
        'settings.',
        'provisioning.',
        'session.',
    ];

    public function record(
        ?User $actor,
        string $action,
        string $resourceType,
        ?string $resourceId,
        string $summary,
        array $metadata = [],
    ): AuditLog {
        if ($actor && ! $actor->relationLoaded('backofficeRole')) {
            $actor->load('backofficeRole');
        }

        $request = request();

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'actor_role_slug' => $actor?->backofficeRole?->slug,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'summary' => $summary,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['actor'])) {
            $actor = $filters['actor'];
            if (is_numeric($actor)) {
                $query->where('actor_id', (int) $actor);
            } else {
                $query->where(function ($q) use ($actor) {
                    $q->where('actor_email', 'like', "%{$actor}%")
                        ->orWhere('actor_role_slug', 'like', "%{$actor}%");
                });
            }
        }

        if (! empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('summary', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('resource_id', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $today = AuditLog::query()->whereDate('created_at', today())->count();

        $privilegedLast7Days = AuditLog::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($query) {
                foreach (self::PRIVILEGED_ACTION_PREFIXES as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'%');
                }
            })
            ->count();

        return [
            'today' => $today,
            'privileged_last_7_days' => $privilegedLast7Days,
        ];
    }
}
