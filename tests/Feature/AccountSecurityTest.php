<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;
    private function account(): User
    {
        return User::forceCreate(['first_name' => 'Security', 'last_name' => 'Test', 'type' => 'parent', 'status' => 1,
            'email' => uniqid().'@example.com', 'email_verified_at' => now(), 'password' => Hash::make('secret123')]);
    }
    private function enabled(User $user): array
    {
        $service = app(AccountSecurity::class); $service->begin($user, 'secret123');
        $secret = Crypt::decryptString($service->state($user)->secret);
        $codes = $service->confirm($user, app(Totp::class)->code($secret, now()->timestamp));
        return [$secret, $codes];
    }
    public function test_review_skip_is_disabled_by_default_and_cannot_skip_enabled_mfa(): void
    {
        $u = $this->account();
        $this->actingAs($u)->post('/security/skip')->assertForbidden();
        config(['security.allow_review_skip' => true]);
        $this->enabled($u);
        $this->post('/security/skip')->assertForbidden();
        $this->get('/security')->assertDontSee('Skip to dashboard');
    }

    public function test_review_skip_allows_web_dashboard_but_not_api_and_expires(): void
    {
        config(['security.allow_review_skip' => true, 'security.require_admin_mfa' => true]);
        $u = $this->account();
        $school = DB::table('schools')->insertGetId(['user_id' => $u->id, 'country_id' => 1, 'school_name' => 'Review School', 'address' => 'Road', 'image' => '', 'post_code' => '00000']);
        DB::table('workspace_memberships')->insert(['user_id' => $u->id, 'school_id' => $school, 'role' => 'school_admin', 'status' => 'active']);
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('security.index'));
        $this->get('/security')->assertSee('Skip to dashboard');
        $this->post('/security/skip')->assertRedirect(route('workspace.home'));
        $this->get('/dashboard')->assertOk();
        $this->withToken($u->createToken('review')->plainTextToken)->getJson('/api/me/workspaces')->assertForbidden()->assertJsonPath('code', 'mfa_required');
        $this->travel(721)->minutes();
        $this->get('/dashboard')->assertRedirect(route('security.index'));
    }
    public function test_setup_requires_password_and_encrypts_the_secret(): void
    {
        $u = $this->account(); $this->actingAs($u)->post('/security/setup', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('account_security', 0);
        $this->post('/security/setup', ['current_password' => 'secret123'])->assertRedirect(route('security.index'));
        $state = app(AccountSecurity::class)->state($u); $secret = Crypt::decryptString($state->secret);
        $this->assertNotEquals($secret, $state->secret);
        $this->get('/security')->assertOk()->assertSee($secret)->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/security/confirm', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertRedirect()->assertSessionHas('recovery_codes');
        $this->assertNotNull(app(AccountSecurity::class)->state($u)->enabled_at);
    }
    public function test_enabled_mfa_blocks_dashboard_until_challenge_and_rejects_replay(): void
    {
        $u = $this->account(); [$secret] = $this->enabled($u);
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('security.index'));
        $this->post('/security/challenge', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertSessionHasErrors('code');
        $this->travel(31)->seconds();
        $this->post('/security/challenge', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertRedirect(route('workspace.home'));
        $this->get('/security')->assertOk();
    }
    public function test_recovery_codes_are_hashed_and_single_use(): void
    {
        $u = $this->account(); [, $codes] = $this->enabled($u);
        $this->assertStringNotContainsString($codes[0], app(AccountSecurity::class)->state($u)->recovery_hashes);
        $this->actingAs($u)->post('/security/challenge', ['code' => $codes[0]])->assertRedirect(route('workspace.home'));
        $this->post('/security/challenge', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(7, json_decode(app(AccountSecurity::class)->state($u)->recovery_hashes, true));
    }
    public function test_bearer_tokens_need_their_own_mfa_verification(): void
    {
        $u = $this->account(); [, $codes] = $this->enabled($u); $token = $u->createToken('device')->plainTextToken;
        $this->withToken($token)->getJson('/api/me/workspaces')->assertForbidden()->assertJsonPath('code', 'mfa_required');
        $this->postJson('/api/security/mfa/challenge', ['code' => $codes[0]])->assertOk();
        $this->getJson('/api/me/workspaces')->assertOk();
        $this->assertDatabaseCount('account_token_mfa', 1);
    }
    public function test_admin_mfa_is_required_without_optional_global_role(): void
    {
        config(['security.require_admin_mfa' => true]); $u = $this->account();
        $school = DB::table('schools')->insertGetId(['user_id' => $u->id, 'country_id' => 1, 'school_name' => 'MFA School', 'address' => 'Road', 'image' => '', 'post_code' => '00000']);
        DB::table('workspace_memberships')->insert(['user_id' => $u->id, 'school_id' => $school, 'role' => 'school_admin', 'status' => 'active']);
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('security.index'));
    }
    public function test_revoked_browser_session_is_rejected_and_cannot_revoke_another_user(): void
    {
        $u = $this->account(); $other = $this->account(); $response = $this->actingAs($u)->get('/security')->assertOk();
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
        $session = DB::table('account_sessions')->where('user_id', $u->id)->first();
        $otherId = DB::table('account_sessions')->insertGetId(['user_id' => $other->id, 'session_hash' => hash('sha256','other'), 'device' => 'Other', 'last_seen_at' => now()]);
        $this->post('/security/sessions/'.$otherId.'/revoke', ['current_password' => 'secret123'])->assertNotFound();
        $this->post('/security/sessions/'.$session->id.'/revoke', ['current_password' => 'secret123'])->assertRedirect();
        $this->get('/security')->assertRedirect(route('login')); $this->assertGuest();
    }
    public function test_unverified_account_can_verify_but_not_read_private_data(): void
    {
        config(['ptm.require_email_verification' => true]);
        $u = $this->account(); $u->update(['email_verified_at' => null]); $this->actingAs($u, 'sanctum');
        $this->getJson('/api/me/workspaces')->assertForbidden();
        // The enrollment middleware must let verification reach its own validator.
        $this->postJson('/api/verify/pincode', ['pincode' => 'invalid'])->assertOk()->assertJsonPath('status', 400);
    }
    public function test_unverified_account_uses_the_api_when_email_verification_is_off(): void
    {
        config(['ptm.require_email_verification' => false]);
        $u = $this->account(); $u->update(['email_verified_at' => null]); $this->actingAs($u, 'sanctum');
        $this->getJson('/api/auth/details')->assertOk();
        $u->update(['status' => 0]);
        $this->getJson('/api/auth/details')->assertForbidden();
    }
}
