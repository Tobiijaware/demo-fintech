<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\BackofficeRole;
use App\Services\Audit\AuditLogService;
use App\Services\Backoffice\PermissionResolver;
use App\Services\Governance\ApprovalRequestService;
use App\Services\Governance\MakerCheckerService;
use Illuminate\Http\JsonResponse;

class RoleController extends ApiController
{
    public function __construct(
        private PermissionResolver $permissions,
        private AuditLogService $auditLog,
        private MakerCheckerService $makerChecker,
        private ApprovalRequestService $approvalRequests,
    ) {}

    public function index(): JsonResponse
    {
        $roles = BackofficeRole::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (BackofficeRole $role) => $this->formatRole($role));

        return $this->success($roles);
    }

    public function show(BackofficeRole $role): JsonResponse
    {
        $role->load('permissions');

        return $this->success($this->formatRole($role, true));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = BackofficeRole::query()->create([
            'slug' => $request->validated('slug'),
            'name' => $request->validated('name'),
            'department' => $request->validated('department'),
            'description' => $request->validated('description'),
            'is_system' => false,
        ]);

        $this->permissions->syncRolePermissions($role, $request->validated('permissions', []));

        $formatted = $this->formatRole($role->fresh('permissions'), true);

        $this->auditLog->record(
            auth('api')->user(),
            'role.created',
            'BackofficeRole',
            (string) $role->id,
            "Created role {$role->name} ({$role->slug})",
            ['after' => $formatted],
        );

        return $this->success($formatted, 'Role created.', 201);
    }

    public function update(UpdateRoleRequest $request, BackofficeRole $role): JsonResponse
    {
        $before = $this->formatRole($role->load('permissions'), true);

        $role->update($request->safe()->only(['name', 'department', 'description']));

        if ($request->has('permissions')) {
            $policy = $this->makerChecker->findPolicyForResource('user_management');

            if ($policy) {
                $permissions = $request->validated('permissions', []);
                $approvalRequest = $this->approvalRequests->submit(
                    auth('api')->user(),
                    'user_management',
                    'backoffice_role',
                    (string) $role->id,
                    [
                        'action' => 'sync_role_permissions',
                        'role_id' => $role->id,
                        'permissions' => $permissions,
                    ],
                    "Permission update for role {$role->name}",
                );

                $after = $this->formatRole($role->fresh('permissions'), true);

                $this->auditLog->record(
                    auth('api')->user(),
                    'role.permissions_submitted',
                    'BackofficeRole',
                    (string) $role->id,
                    "Submitted permission update for role {$role->name} ({$role->slug})",
                    [
                        'before' => $before,
                        'after' => $after,
                        'approval_request_id' => $approvalRequest->id,
                    ],
                );

                return $this->success([
                    'role' => $after,
                    'approval_request' => [
                        'id' => $approvalRequest->id,
                        'status' => $approvalRequest->status->value,
                        'summary' => $approvalRequest->summary,
                    ],
                ], 'Role permission changes submitted for checker approval.');
            }

            $this->permissions->syncRolePermissions($role, $request->validated('permissions', []));
        }

        $after = $this->formatRole($role->fresh('permissions'), true);

        $this->auditLog->record(
            auth('api')->user(),
            'role.updated',
            'BackofficeRole',
            (string) $role->id,
            "Updated role {$role->name} ({$role->slug})",
            ['before' => $before, 'after' => $after],
        );

        return $this->success($after, 'Role updated.');
    }

    public function destroy(BackofficeRole $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->error('System roles cannot be deleted.', 422);
        }

        if ($role->users()->exists()) {
            return $this->error('Role is assigned to users. Reassign them first.', 422);
        }

        $before = $this->formatRole($role->load('permissions'), true);

        $this->auditLog->record(
            auth('api')->user(),
            'role.deleted',
            'BackofficeRole',
            (string) $role->id,
            "Deleted role {$role->name} ({$role->slug})",
            ['before' => $before],
        );

        $role->delete();

        return $this->success(null, 'Role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRole(BackofficeRole $role, bool $withPermissions = false): array
    {
        $data = [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'department' => $role->department,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'users_count' => $role->users_count ?? $role->users()->count(),
        ];

        if ($withPermissions) {
            $matrix = [];
            foreach ($role->permissions as $permission) {
                $matrix[$permission->key] = $permission->pivot->level;
            }
            $data['permissions'] = $matrix;
        }

        return $data;
    }
}
