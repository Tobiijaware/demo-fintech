<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\StaffSession;
use App\Services\Governance\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SessionController extends ApiController
{
    public function __construct(private SessionService $sessions) {}

    public function stats(): JsonResponse
    {
        $rows = $this->sessions->activeStatsByRole();

        return $this->success([
            'active_window_minutes' => 15,
            'total' => collect($rows)->sum('active'),
            'by_role' => $rows,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->sessions->list($request->only(['active_only', 'per_page']));

        $paginator->getCollection()->transform(fn (StaffSession $session) => $this->format($session));

        return $this->success($paginator);
    }

    public function destroy(int $tokenId): JsonResponse
    {
        try {
            $session = StaffSession::query()->findOrFail($tokenId);
            $this->sessions->revoke($session, auth('api')->user());

            return $this->success(null, 'Session revoked.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function format(StaffSession $session): array
    {
        $session->loadMissing('user.backofficeRole');
        $user = $session->user;

        return [
            'id' => $session->id,
            'user_id' => $user?->id,
            'user_name' => $user?->full_name ?? '—',
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'role' => $user?->backofficeRole?->name ?? '—',
            'role_slug' => $user?->backofficeRole?->slug,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'last_active_at' => $session->last_active_at?->toIso8601String(),
            'created_at' => $session->created_at?->toIso8601String(),
            'is_active' => $session->revoked_at === null
                && $session->last_active_at?->gte($this->sessions->activeCutoff()),
        ];
    }
}
