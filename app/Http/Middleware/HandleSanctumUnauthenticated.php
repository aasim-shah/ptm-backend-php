<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HandleSanctumUnauthenticated
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (AuthenticationException $exception) {
            if ($request->expectsJson() && $this->isSanctumRoute($request)) {
                return response()->json(['status' => 400, 'error' => 'Unauthenticated.'], 401);
            }

            return $this->redirectToLogin($request);
        }
    }

    protected function isSanctumRoute($request)
    {
        $routeAction = Route::getRoutes()->match($request)->getAction();

        return isset($routeAction['uses']) && strpos($routeAction['uses'], 'Laravel\Sanctum\Http\Controllers') !== false;
    }

    protected function redirectToLogin($request)
    {
        if ($request->is('api/*')) {
            return response()->json(['status' => 400, 'error' => 'Unauthenticated.'], 401);
        } else {
            return redirect()->guest(route('login'));
        }
    }
}
