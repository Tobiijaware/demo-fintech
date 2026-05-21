<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Services\Backoffice\PermissionResolver;

trait SerializesBackofficeUser
{
    /**
     * @return array<string, mixed>|null
     */
    protected function backofficeProfile(User $user): ?array
    {
        if (! $user->isBackofficeStaff()) {
            return null;
        }

        $user->loadMissing('backofficeRole');
        $slug = $user->backofficeRole?->slug
            ?? ($user->role?->value === 'admin' ? 'super_admin' : null);

        if (! $slug) {
            return null;
        }

        $permissions = app(PermissionResolver::class)->resolveForUser($user);

        return [
            'role_slug' => $slug,
            'role_name' => $user->backofficeRole?->name ?? 'Administrator',
            'department' => $user->backofficeRole?->department,
            'job_title' => $user->job_title,
            'hub' => $user->hub,
            'permissions' => $permissions,
            'home_path' => config("backoffice.home_paths.{$slug}", 'admin'),
        ];
    }
}
