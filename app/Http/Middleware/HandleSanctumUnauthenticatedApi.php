<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HandleSanctumUnauthenticatedApi
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (AuthenticationException $exception) {
            return response()->json(['status' => 400, 'error' => 'Unauthenticated.'], 401);
        }
    }
}
