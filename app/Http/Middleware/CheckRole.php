<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Legacy global management gate. School and district administrators use scoped workspace routes.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!(int) $user->status || !$user->email_verified_at || !$user->hasRole('Super Admin')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['message' => trans('no_permission_message')]);
        }

        return $next($request);
    }
}
