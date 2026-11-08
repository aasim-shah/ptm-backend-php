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
        // HSTS whenever the request arrived over HTTPS (directly or through the proxy).
        if ($request->isSecure()) { $response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains'); }
        $response->headers->remove('X-Powered-By');
        // The administrative panel, sign-in and security pages load only their own assets (QA D4).
        if ($request->is('dashboard', 'dashboard/*', 'workspace/*', 'security', 'security/*', 'login', 'invitations/*', 'password/*')
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
        }
        return $response;
    }
}
