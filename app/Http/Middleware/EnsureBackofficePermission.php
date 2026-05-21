<?php

namespace App\Http\Middleware;

use App\Enums\PermissionLevel;
use App\Services\Backoffice\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficePermission
{
    public function __construct(private PermissionResolver $permissions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $level = 'read'): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $required = PermissionLevel::tryFrom($level) ?? PermissionLevel::Read;

        if (! $this->permissions->can($user, $permission, $required)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
