<?php

namespace App\Services\Backoffice;

use App\Enums\PermissionLevel;
use App\Enums\UserType;
use App\Models\BackofficeRole;
use App\Models\User;

class PermissionResolver
{
    /**
     * @return array<string, string> permission key => level
     */
    public function resolveForUser(User $user): array
    {
        if ($user->user_type !== UserType::Staff || ! $user->backoffice_role_id) {
            if ($user->role?->value === 'admin') {
                return $this->allWrite();
            }

            return [];
        }

        $user->loadMissing('backofficeRole.permissions');

        $matrix = [];
        foreach ($user->backofficeRole?->permissions ?? [] as $permission) {
            $matrix[$permission->key] = $permission->pivot->level;
        }

        return $matrix;
    }

    public function can(User $user, string $permissionKey, PermissionLevel $level = PermissionLevel::Read): bool
    {
        $matrix = $this->resolveForUser($user);
        if (! isset($matrix[$permissionKey])) {
            return false;
        }

        $granted = PermissionLevel::tryFrom($matrix[$permissionKey]);

        return $granted?->satisfies($level) ?? false;
    }

    /**
     * @return array<string, string>
     */
    private function allWrite(): array
    {
        return collect(config('backoffice.permissions', []))
            ->mapWithKeys(fn (array $p) => [$p['key'] => PermissionLevel::Write->value])
            ->all();
    }

    /**
     * @param  array<string, string>  $permissions
     */
    public function syncRolePermissions(BackofficeRole $role, array $permissions): void
    {
        $ids = [];
        foreach ($permissions as $key => $level) {
            if (! in_array($level, ['read', 'write', 'append'], true)) {
                continue;
            }
            $permission = \App\Models\Permission::query()->where('key', $key)->first();
            if ($permission) {
                $ids[$permission->id] = ['level' => $level];
            }
        }

        $role->permissions()->sync($ids);
    }
}
