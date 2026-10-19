<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;

/**
 * Restricts a route to the roles the request is acting as.
 *
 * Usage: ->middleware('ptm.role:teacher,principal')
 * With a role context the context's role must be listed ('principal' also accepts the
 * school_admin context of a legacy principal). Without one, users.type / the Spatie role
 * decides, exactly as the old userType middleware did.
 */
class RequireContextRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.', 'code' => 401], 401);
        }

        $allowed = array_map('strtolower', $roles);
        $context = Access::context();
        if ($context) {
            $role = $context->role;
            if (in_array($role, $allowed, true)
                || ($role === 'school_admin' && in_array('principal', $allowed, true) && Access::isPrincipal($user))) {
                return $next($request);
            }
            return Access::forbidden();
        }

        // Legacy mode only covers account types that existed before role contexts. A staff or
        // admin account without an active context (e.g. still pending) gets nothing.
        $type = strtolower((string) $user->type);
        if (in_array($type, $allowed, true) && in_array($type, ['parent', 'teacher', 'principal', 'student'], true)) {
            return $next($request);
        }
        foreach ($allowed as $role) {
            if (in_array($role, ['parent', 'teacher', 'principal', 'student'], true) && $user->hasRole(ucfirst($role))) {
                return $next($request);
            }
        }
        return Access::forbidden();
    }
}
