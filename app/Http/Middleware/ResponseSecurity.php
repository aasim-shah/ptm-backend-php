<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class ResponseSecurity
{
    public function handle(Request $request,Closure $next)
    {
        if ($request->is('security*','invitations/*') && app()->bound('debugbar')) { app('debugbar')->disable(); }
        $id=(string)Str::uuid(); Log::withContext(['request_id'=>$id]);
        $response=$next($request);
        $response->headers->set('X-Request-ID',$id);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','DENY');
        $response->headers->set('Referrer-Policy','same-origin');
        $response->headers->set('Permissions-Policy','camera=(self), microphone=(self), geolocation=()');
        if (app()->environment('production') && $request->isSecure()) { $response->headers->set('Strict-Transport-Security','max-age=31536000'); }
        return $response;
    }
}
