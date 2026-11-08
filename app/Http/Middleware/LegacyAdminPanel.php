<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The legacy "Classic admin tools" panel (fixed roles, India/INR settings, Razorpay,
 * secrets in forms) is closed by default under Document 13. It can be reopened for a
 * short migration window with LEGACY_ADMIN_ENABLED=true; every page then stays behind
 * the existing role checks.
 */
class LegacyAdminPanel
{
    public function handle(Request $request, Closure $next)
    {
        if (config('workspace.legacy_admin_enabled')) { return $next($request); }
        if ($request->expectsJson()) { return response()->json(['message' => 'The classic admin panel is retired. Use the web dashboard.'], 410); }
        return redirect()->route('workspace.home')->with('workspace_success', 'The classic admin tools are retired. Everything is managed from this dashboard.');
    }
}
