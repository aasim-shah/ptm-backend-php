<?php
namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\Security\AccountSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    public function __construct(private AccountSecurity $security) {}

    public function index(Request $request)
    {
        $state = $this->security->state($request->user()); $secret = null;
        if ($state && !$state->enabled_at && $state->pending_until && now()->lt($state->pending_until)
            && $request->session()->get('mfa_enrollment_confirmed_at', 0) > now()->subMinutes(config('security.enrollment_minutes'))->timestamp) {
            $secret = Crypt::decryptString($state->secret);
        }
        return response()->view('security.index', ['state' => $state, 'secret' => $secret,
            'required' => $this->security->required($request->user()), 'currentHash' => hash('sha256', $request->session()->getId()),
            'sessions' => DB::table('account_sessions')->where('user_id', $request->user()->id)->whereNull('revoked_at')->orderByDesc('last_seen_at')->limit(30)->get()])->header('Cache-Control', 'no-store, private');
    }

    public function begin(Request $request)
    {
        $request->validate(['current_password' => 'required|string|max:208']);
        $this->security->begin($request->user(), $request->current_password);
        $request->session()->put('mfa_enrollment_confirmed_at', now()->timestamp);
        return redirect()->route('security.index');
    }

    public function skip(Request $request)
    {
        abort_unless(config('security.allow_review_skip') && !$this->security->state($request->user())?->enabled_at, 403);
        $request->session()->regenerate();
        $request->session()->put(['review_skip_user' => $request->user()->id, 'review_skip_at' => now()->timestamp]);
        app(\App\Services\WorkspaceAccess::class)->audit($request->user(), null, 'security.review-enrollment.skipped', $request->user()->id);
        return redirect()->route('workspace.home');
    }

    private function mark(Request $request): void
    {
        $state = $this->security->state($request->user());
        $request->session()->regenerate();
        $request->session()->put(['mfa_user' => $request->user()->id, 'mfa_version' => $state->version, 'mfa_verified_at' => now()->timestamp]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        abort_unless($request->session()->get('mfa_enrollment_confirmed_at', 0) > now()->subMinutes(config('security.enrollment_minutes'))->timestamp, 403);
        $codes = $this->security->confirm($request->user(), $request->code); $this->mark($request);
        return redirect()->route('security.index')->with('recovery_codes', $codes)->with('security_success', 'Authenticator enabled. Save these recovery codes securely; they are shown once.');
    }

    public function challenge(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $this->security->verify($request->user(), $request->code); $this->mark($request);
        return redirect()->route('workspace.home');
    }

    public function tokenChallenge(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $token = $request->user()->currentAccessToken(); abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken, 403);
        $this->security->verify($request->user(), $request->code);
        DB::table('account_token_mfa')->updateOrInsert(['token_id' => $token->id], ['user_id' => $request->user()->id,
            'version' => $this->security->state($request->user())->version, 'verified_at' => now()]);
        return response()->json(['message' => 'Multi-factor authentication verified.']);
    }

    public function revoke(Request $request, int $id)
    {
        $request->validate(['current_password' => 'required|string|max:208']);
        $this->security->password($request->user(), $request->current_password);
        $session = DB::table('account_sessions')->where('user_id', $request->user()->id)->find($id); abort_unless($session, 404);
        DB::table('account_sessions')->where('id', $id)->update(['revoked_at' => now()]);
        app(\App\Services\WorkspaceAccess::class)->audit($request->user(), null, 'security.session.revoked', $id);
        return back()->with('security_success', 'Session revoked.');
    }

    public function revokeOthers(Request $request)
    {
        $request->validate(['current_password' => 'required|string|max:208']); $this->security->password($request->user(), $request->current_password);
        DB::transaction(function () use ($request) {
            \App\Models\User::where('id', $request->user()->id)->lockForUpdate()->first();
            $state = $this->security->state($request->user());
            $version = ($state->session_version ?? 1) + 1;
            DB::table('account_security')->updateOrInsert(['user_id' => $request->user()->id], ['session_version' => $version, 'updated_at' => now()]);
            $request->session()->put('account_session_version', $version);
            DB::table('account_sessions')->where('session_hash', hash('sha256', $request->session()->getId()))->update(['version' => $version]);
            DB::table('account_sessions')->where('user_id', $request->user()->id)->where('session_hash', '!=', hash('sha256', $request->session()->getId()))->update(['revoked_at' => now()]);
            $request->user()->tokens()->delete();
            app(\App\Services\WorkspaceAccess::class)->audit($request->user(), null, 'security.other-sessions.revoked', $request->user()->id);
        });
        return back()->with('security_success', 'Other tracked sessions and API tokens revoked.');
    }
}
