<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The "System update" page extracts an uploaded zip over the application code.
 * It is disabled unless SYSTEM_UPDATE_ENABLED=true and the user is a Super Admin.
 */
class EnsureSystemUpdateEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!config('app.system_update_enabled') || !$user || !$user->hasRole('Super Admin')) {
            abort(404);
        }

        return $next($request);
    }
}
