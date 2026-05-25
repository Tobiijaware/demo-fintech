<?php

namespace App\Services\Governance;

use App\Enums\UserType;
use App\Models\BackofficeRole;
use App\Models\StaffSession;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SessionService
{
    private const ACTIVE_WINDOW_MINUTES = 15;

    public function __construct(private AuditLogService $auditLog) {}

    public function registerSession(User $user, string $token, ?string $ip = null, ?string $userAgent = null): StaffSession
    {
        return StaffSession::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'user_id' => $user->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? substr($userAgent, 0, 512) : null,
                'last_active_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    public function touchSession(string $token): void
    {
        StaffSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->update(['last_active_at' => now()]);
    }

    public function revokeByToken(string $token): void
    {
        StaffSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return list<array{role: string, role_slug: string, active: int}>
     */
    public function activeStatsByRole(): array
    {
        $cutoff = now()->subMinutes(self::ACTIVE_WINDOW_MINUTES);

        $sessions = StaffSession::query()
            ->whereNull('revoked_at')
            ->where('last_active_at', '>=', $cutoff)
            ->with('user.backofficeRole')
            ->get();

        $counts = [];
        foreach ($sessions as $session) {
            $slug = $session->user?->backofficeRole?->slug ?? 'unknown';
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }

        if ($counts === []) {
            return [];
        }

        $roles = BackofficeRole::query()
            ->whereIn('slug', array_keys($counts))
            ->get()
            ->keyBy('slug');

        $rows = [];
        foreach ($counts as $slug => $active) {
            $rows[] = [
                'role' => $roles[$slug]->name ?? ucwords(str_replace('_', ' ', $slug)),
                'role_slug' => $slug,
                'active' => $active,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['active'] <=> $a['active']);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = StaffSession::query()
            ->with(['user.backofficeRole'])
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at');

        if (! empty($filters['active_only'])) {
            $query->where('last_active_at', '>=', now()->subMinutes(self::ACTIVE_WINDOW_MINUTES));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function revoke(StaffSession $session, User $actor): StaffSession
    {
        if ($session->revoked_at !== null) {
            throw new InvalidArgumentException('Session is already revoked.');
        }

        $session->update(['revoked_at' => now()]);
        $session->load('user.backofficeRole');

        $this->auditLog->record(
            $actor,
            'session.revoked',
            'StaffSession',
            (string) $session->id,
            "Revoked staff session for {$session->user?->full_name}",
            [
                'user_id' => $session->user_id,
                'ip_address' => $session->ip_address,
            ],
        );

        return $session->fresh(['user.backofficeRole']);
    }

    public function isStaffUser(User $user): bool
    {
        return $user->user_type === UserType::Staff || $user->isBackofficeStaff();
    }

    public function activeCutoff(): Carbon
    {
        return now()->subMinutes(self::ACTIVE_WINDOW_MINUTES);
    }
}
