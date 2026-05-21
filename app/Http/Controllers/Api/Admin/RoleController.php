<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\BackofficeRole;
use App\Services\Backoffice\PermissionResolver;
use Illuminate\Http\JsonResponse;

class RoleController extends ApiController
{
    public function __construct(private PermissionResolver $permissions) {}

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

        return $this->success($this->formatRole($role->fresh('permissions'), true), 'Role created.', 201);
    }

    public function update(UpdateRoleRequest $request, BackofficeRole $role): JsonResponse
    {
        $role->update($request->safe()->only(['name', 'department', 'description']));

        if ($request->has('permissions')) {
            $this->permissions->syncRolePermissions($role, $request->validated('permissions', []));
        }

        return $this->success($this->formatRole($role->fresh('permissions'), true), 'Role updated.');
    }

    public function destroy(BackofficeRole $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->error('System roles cannot be deleted.', 422);
        }

        if ($role->users()->exists()) {
            return $this->error('Role is assigned to users. Reassign them first.', 422);
        }

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
