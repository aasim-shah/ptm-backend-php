<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restricts a mobile API route group to the given user types.
 *
 * Usage: ->middleware('userType:teacher,principal')
 * A user passes when users.type matches (case-insensitive) or when they hold
 * the Spatie role with the same name.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, ...$types)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthenticated.',
                'code' => 401,
            ], 401);
        }

        $allowed = array_map('strtolower', $types);
        if (in_array(strtolower((string) $user->type), $allowed, true)) {
            return $next($request);
        }

        foreach ($allowed as $type) {
            if ($user->hasRole(ucfirst($type))) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => true,
            'message' => trans('no_permission_message'),
            'code' => 403,
        ], 403);
    }
}
