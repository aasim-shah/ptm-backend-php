<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;

/**
 * Requires one of the given context permissions, e.g. ->middleware('ptm.can:schedule.use').
 * Hidden UI is not security: every endpoint re-checks (Doc 12 §6).
 */
class RequireContextPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.', 'code' => 401], 401);
        }
        foreach ($permissions as $permission) {
            if (Access::can($user, $permission)) {
                return $next($request);
            }
        }
        return Access::forbidden();
    }
}
