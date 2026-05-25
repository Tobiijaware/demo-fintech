<?php

namespace App\Http\Middleware;

use App\Enums\PermissionLevel;
use App\Services\Backoffice\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnyBackofficePermission
{
    public function __construct(private PermissionResolver $permissions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissionSpecs): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        foreach ($permissionSpecs as $spec) {
            [$permission, $level] = array_pad(explode(',', $spec, 2), 2, 'read');
            $required = PermissionLevel::tryFrom($level) ?? PermissionLevel::Read;

            if ($this->permissions->can($user, $permission, $required)) {
                return $next($request);
            }
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
    }
}
