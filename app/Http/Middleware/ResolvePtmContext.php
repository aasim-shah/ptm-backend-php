<?php

namespace App\Http\Middleware;

use App\Services\RoleContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the role context of every authenticated API request (X-PTM-Context, Part A5.6).
 *
 * - Header present: it must be one of the caller's own, active contexts, otherwise 403.
 *   There is no silent fallback to another role.
 * - Header absent (older app builds): the user's default active context, if any.
 *
 * The resolved context is stored on the request (Access::context()).
 * A few account-level endpoints ignore a bad header instead of rejecting it, so a user
 * whose context was suspended can still load /auth/details, switch, or sign out.
 */
class ResolvePtmContext
{
    private const LENIENT_PATHS = [
        'api/auth/details', 'api/auth/context/switch', 'api/auth/signout', 'api/logout',
        'api/auth/login', 'api/auth/register', 'api/auth/fcm',
    ];

    public function __construct(private RoleContextService $contexts)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return $next($request);
        }

        [$context, $error] = $this->contexts->resolve($request, $user);
        if ($error) {
            if (!in_array(trim($request->path(), '/'), self::LENIENT_PATHS, true)) {
                return response()->json(['error' => true, 'message' => $error, 'code' => 403], 403);
            }
            $context = $this->contexts->defaultContext($user);
        }

        $request->attributes->set(RoleContextService::REQUEST_ATTRIBUTE, $context);
        return $next($request);
    }
}
