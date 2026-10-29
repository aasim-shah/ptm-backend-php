<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\AccountSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnforceAccountSecurity
{
    public function handle(Request $request, Closure $next)
    {
        $api = $request->is('api/*');
        $user = $api ? $request->user('sanctum') : $request->user('web');
        if (!$user) { return $next($request); }
        if ($request->routeIs('workspace.logout', 'logout') || $request->is('api/auth/signout', 'api/logout')) { return $next($request); }
        $active = User::where('id', $user->id)->where('status', 1)->first();
        if ($active && !$active->email_verified_at && $request->is('api/send/pincode', 'api/verify/pincode')) { return $next($request); }
        if (!$active || !$active->email_verified_at) {
            abort(403, 'An active, verified account is required.');
        }
        $security = app(AccountSecurity::class); $state = $security->state($user);
        if (!$api && $request->hasSession()) {
            $hash = hash('sha256', $request->session()->getId());
            $session = DB::table('account_sessions')->where('session_hash', $hash)->first();
            $version = (int) ($state->session_version ?? 1);
            $presentedVersion = (int) $request->session()->get('account_session_version', $session->version ?? 1);
            if (($session && ($session->revoked_at || (int) $session->user_id !== (int) $user->id)) || $presentedVersion !== $version) {
                Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
                return $request->expectsJson() ? response()->json(['message' => 'Session revoked.'], 401) : redirect()->route('login');
            }
            $request->session()->put('account_session_version', $version);
            if (!$session) {
                DB::table('account_sessions')->insertOrIgnore(['user_id' => $user->id, 'session_hash' => $hash,
                    'version' => $version, 'device' => mb_substr($request->userAgent() ?? 'Unknown device', 0, 180), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            } else { DB::table('account_sessions')->where('id', $session->id)->update(['last_seen_at' => now()]); }
        }
        if ($request->routeIs('security.index', 'security.begin', 'security.confirm', 'security.challenge', 'security.skip') || $request->is('api/security/mfa/challenge')) { return $next($request); }
        $reviewSkip = !$api && !$state?->enabled_at && config('security.allow_review_skip') && $request->hasSession()
            && $request->session()->get('review_skip_user') === $user->id
            && $request->session()->get('review_skip_at', 0) > now()->subMinutes(config('security.mfa_lifetime_minutes'))->timestamp;
        if ($security->required($user) && !$reviewSkip) {
            $verified = false;
            if ($api) {
                $token = $user->currentAccessToken();
                if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                    $verified = $state && DB::table('account_token_mfa')->where('user_id', $user->id)->where('token_id', $token->id)
                        ->where('version', $state->version)->where('verified_at', '>', now()->subMinutes(config('security.mfa_lifetime_minutes')))->exists();
                }
            } elseif ($request->hasSession()) {
                $verified = $state && $request->session()->get('mfa_user') === $user->id
                    && (int) $request->session()->get('mfa_version') === (int) $state->version
                    && $request->session()->get('mfa_verified_at', 0) > now()->subMinutes(config('security.mfa_lifetime_minutes'))->timestamp;
            }
            if (!$verified) {
                return $api || $request->expectsJson() ? response()->json(['message' => 'Multi-factor authentication required.', 'code' => 'mfa_required'], 403)
                    : redirect()->route('security.index');
            }
        }
        $response = $next($request);
        if (!$api && $request->hasSession()) { $response->headers->set('Cache-Control', 'no-store, private'); }
        return $response;
    }
}
