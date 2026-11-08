<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Regression tests for the 3 November 2026 admin panel QA report. */
class AdminPanelQaTest extends TestCase
{
    use RefreshDatabase;

    public function actingAs($user, $guard = null)
    {
        $this->flushSession();
        return parent::actingAs($user, $guard ?? 'web');
    }

    private function user(string $name = 'Admin', array $extra = []): User
    {
        return User::forceCreate($extra + ['first_name' => $name, 'last_name' => 'Person', 'type' => 'parent', 'email' => strtolower($name).uniqid().'@example.com',
            'password' => Hash::make('secret123'), 'status' => 1, 'email_verified_at' => now()]);
    }

    private function super(): User
    {
        $u = $this->user('Super'); $u->assignRole(Role::findOrCreate('Super Admin', 'web'));
        return $u;
    }

    private function school(string $name, array $extra = []): int
    {
        return DB::table('schools')->insertGetId($extra + ['user_id' => 1, 'country_id' => 1, 'school_name' => $name, 'address' => 'Road', 'image' => '', 'post_code' => '00000']);
    }

    private function member(User $user, int $school, string $role, array $permissions = []): string
    {
        return 'm'.DB::table('workspace_memberships')->insertGetId(['user_id' => $user->id, 'school_id' => $school, 'role' => $role,
            'status' => 'active', 'permissions' => json_encode($permissions), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_people_directories_are_separate_searchable_pages(): void
    {
        $super = $this->super(); $school = $this->school('Maple School');
        $this->member($this->user('Parenta'), $school, 'parent');
        $this->member($this->user('Teachera'), $school, 'teacher');
        $this->member($this->user('Staffa'), $school, 'staff', ['web.access']);
        $legacy = $this->user('Legacyparent'); $legacy->assignRole(Role::findOrCreate('Parent', 'web'));

        $this->actingAs($super)->get('/dashboard/parents?context=platform')->assertOk()->assertSee('Parents / Guardians')
            ->assertSee('Parenta')->assertSee('Legacyparent')->assertDontSee('Teachera')->assertSee('Add parent or guardian');
        $this->get('/dashboard/teachers?context=platform')->assertOk()->assertSee('Teachera')->assertDontSee('Parenta');
        $this->get('/dashboard/staff?context=platform')->assertOk()->assertSee('Staffa')->assertSee('Web panel access');
        $this->get('/dashboard/parents?context=platform&q=legacy')->assertOk()->assertSee('Legacyparent')->assertDontSee('Parenta');
        $this->get('/dashboard/parents?context=platform&status=unconnected')->assertOk()->assertSee('Legacyparent')->assertDontSee('Parenta');
        // KPI cards count the same people the directories list (QA D5).
        $this->getJson('/dashboard/analytics?context=platform')->assertOk()->assertJsonPath('kpis.0.value', 2)->assertJsonPath('kpis.1.value', 1);
    }

    public function test_person_detail_supports_restore_reset_and_suspension(): void
    {
        $super = $this->super(); $school = $this->school('Oak School');
        $parent = $this->user('Pat'); $membership = (int) substr($this->member($parent, $school, 'parent'), 1);
        $this->actingAs($super)->get('/dashboard/members/'.$parent->id.'?context=platform')->assertOk()->assertSee('Oak School')->assertSee('Suspend account');
        $this->post('/workspace/memberships/'.$membership.'/revoke', ['context' => 'platform'])->assertRedirect();
        $this->post('/workspace/memberships/'.$membership.'/restore', ['context' => 'platform'])->assertRedirect();
        $this->assertDatabaseHas('workspace_memberships', ['id' => $membership, 'status' => 'active']);
        $this->post('/workspace/members/'.$parent->id.'/account', ['context' => 'platform', 'status' => 'suspend', 'reason' => 'Review'])->assertRedirect();
        $this->assertSame(0, (int) $parent->fresh()->status);
        $this->postJson('/workspace/members/'.$super->id.'/account', ['context' => 'platform', 'status' => 'suspend', 'reason' => 'x'])->assertStatus(422);
        // Only the full-platform Super Admin may suspend accounts.
        $principal = $this->user('Principal'); $c = $this->member($principal, $school, 'school_admin');
        $this->actingAs($principal)->postJson('/workspace/members/'.$parent->id.'/account', ['context' => $c, 'status' => 'reactivate'])->assertForbidden();
    }

    public function test_super_admin_students_page_is_read_only(): void
    {
        $super = $this->super();
        $this->assertFalse(app(\App\Services\WorkspaceAccess::class)->can(['role' => 'super_admin', 'id' => 'platform', 'platform' => true, 'schools' => [], 'permissions' => [], 'membership_id' => null], 'updates.write'));
        $this->actingAs($super)->get('/dashboard/students?context=platform')->assertOk()->assertDontSee('Write update')->assertSee('read-only roster');
        $this->get('/dashboard?context=platform')->assertOk()->assertDontSee('<span>Student updates</span>', false);
    }

    public function test_super_admin_creates_edits_and_suspends_schools_and_districts(): void
    {
        $super = $this->super();
        $this->actingAs($super)->get('/dashboard/schools?context=platform')->assertOk()->assertSee('Add school');
        $this->post('/workspace/schools', ['context' => 'platform', 'school_name' => 'Lincoln Elementary', 'market' => 'US', 'address' => '1 Main St', 'post_code' => '10001'])->assertRedirect();
        $school = DB::table('schools')->where('school_name', 'Lincoln Elementary')->first();
        $this->assertSame('US', $school->market);
        $this->get('/dashboard/schools/'.$school->id.'?context=platform')->assertOk()->assertSee('Assign School Admin')->assertSee('Suspend school');
        $this->post('/workspace/districts', ['context' => 'platform', 'name' => 'Springfield District', 'market' => 'US'])->assertRedirect();
        $district = DB::table('workspace_districts')->where('name', 'Springfield District')->value('id');
        $this->post('/workspace/districts/'.$district.'/schools', ['context' => 'platform', 'school_id' => $school->id, 'action' => 'add'])->assertRedirect();
        $admin = $this->user('Dana');
        $this->post('/workspace/district-admin', ['context' => 'platform', 'district_id' => $district, 'email' => $admin->email])->assertRedirect();
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $admin->id, 'district_id' => $district, 'role' => 'district_admin', 'status' => 'active']);
        $this->get('/dashboard/districts/'.$district.'?context=platform')->assertOk()->assertSee('Lincoln Elementary')->assertSee('Dana');
        // Suspending a school removes it from members' workspaces.
        $principal = $this->user('Principal'); $c = $this->member($principal, $school->id, 'school_admin');
        $this->post('/workspace/schools/'.$school->id.'/status', ['context' => 'platform', 'status' => 'suspended', 'reason' => 'Contract ended'])->assertRedirect();
        $this->actingAs($principal)->get('/dashboard?context='.$c)->assertForbidden();
        // District and School Admins cannot create schools.
        $this->actingAs($admin)->postJson('/workspace/schools', ['context' => 'm'.DB::table('workspace_memberships')->where('user_id', $admin->id)->value('id'), 'school_name' => 'X', 'market' => 'US', 'address' => 'A', 'post_code' => '1'])->assertForbidden();
    }

    public function test_sub_super_admin_page_uses_plain_language_and_one_scope(): void
    {
        $super = $this->super(); $school = $this->school('Elm School'); $person = $this->user('Delegate');
        $this->actingAs($super)->get('/dashboard/sub-super-admins?context=platform')->assertOk()->assertSee('View students')->assertSee('Manage people &amp; access', false)->assertDontSee('>students.view<', false);
        $this->postJson('/workspace/delegations', ['context' => 'platform', 'email' => $person->email, 'scope' => 'school', 'school_id' => $school, 'permissions' => []])->assertStatus(422);
        $this->post('/workspace/delegations', ['context' => 'platform', 'email' => $person->email, 'scope' => 'school', 'school_id' => $school, 'permissions' => ['students.view']])->assertRedirect();
        $id = DB::table('workspace_memberships')->where('user_id', $person->id)->value('id');
        $this->post('/workspace/delegations/'.$id, ['context' => 'platform', 'permissions' => ['students.view', 'audit.view']])->assertRedirect();
        $this->assertSame(['students.view', 'audit.view'], json_decode(DB::table('workspace_memberships')->where('id', $id)->value('permissions'), true));
    }

    public function test_support_view_limits_super_admin_to_one_school(): void
    {
        $super = $this->super(); $a = $this->school('Alpha School'); $b = $this->school('Beta School');
        $this->actingAs($super)->post('/workspace/select', ['context' => 'platform:s'.$a])->assertRedirect();
        $this->get('/dashboard/schools?context=platform:s'.$a)->assertOk()->assertSee('/dashboard/schools/'.$a, false)->assertDontSee('/dashboard/schools/'.$b, false)->assertSee('Support view');
        $teacher = $this->user('Teacher'); $c = $this->member($teacher, $a, 'school_admin');
        $this->actingAs($teacher)->get('/dashboard?context=platform:s'.$a)->assertForbidden();
    }

    public function test_settings_markets_ads_and_trial_are_configurable(): void
    {
        $super = $this->super();
        $this->actingAs($super)->get('/dashboard/settings?context=platform')->assertOk()->assertSee('United States')->assertSee('United Kingdom')->assertSee('Headteacher');
        $this->post('/workspace/settings', ['context' => 'platform', 'section' => 'trial', 'trial__minutes' => 90, 'trial__days' => 7, 'trial__call_count' => 3])->assertRedirect();
        $this->assertSame(90, app(\App\Services\PlatformSettings::class)->get('trial.minutes'));
        $this->post('/workspace/markets/UK', ['context' => 'platform', 'enabled' => 1, 'currency' => 'gbp', 'currency_symbol' => '£', 'locale' => 'en_GB', 'timezone' => 'Europe/London',
            'date_format' => 'd/m/Y', 'phone_prefix' => '+44', 'postal_label' => 'Postcode', 'region_label' => 'County', 'minimum_age' => 13,
            'terminology' => ['grade' => 'Year', 'school_admin' => 'Head', 'district' => 'Trust', 'guardian' => 'Carer', 'term' => 'Term']])->assertRedirect();
        $this->assertSame('GBP', DB::table('workspace_markets')->where('code', 'UK')->value('currency'));
        $this->get('/dashboard/integrations?context=platform')->assertOk()->assertSee('Agora video')->assertSee('Show ads (kill switch)');
        $this->post('/workspace/integrations/ads', ['context' => 'platform', 'enabled' => 0, 'markets' => ['US' => 1, 'UK' => 1]])->assertRedirect();
        $this->assertFalse(app(\App\Services\PlatformSettings::class)->adsEnabled('US'));
        $this->withToken($super->createToken('t')->plainTextToken)->getJson('/api/auth/settings')->assertJsonPath('data.ads_enabled', false)->assertJsonPath('data.trial_period', 7);
        // Nobody else can reach platform settings.
        $school = $this->school('Pine'); $p = $this->user('Principal'); $c = $this->member($p, $school, 'school_admin');
        $this->actingAs($p)->get('/dashboard/settings?context='.$c)->assertForbidden();
    }

    public function test_bulk_import_previews_then_connects_and_reports_problems(): void
    {
        $super = $this->super(); $school = $this->school('Cedar School');
        $existing = $this->user('Known');
        $csv = "email,first_name,last_name,role,school\n{$existing->email},Known,Person,teacher,Cedar School\nbad-email,Bad,Row,parent,Cedar School\nnew@example.com,New,Person,wizard,Cedar School\n";
        $file = UploadedFile::fake()->createWithContent('people.csv', $csv);
        $this->actingAs($super)->get('/dashboard/imports?context=platform')->assertOk()->assertSee('People template');
        $this->post('/workspace/imports', ['context' => 'platform', 'kind' => 'people', 'file' => $file])->assertRedirect();
        $import = DB::table('workspace_imports')->first();
        $this->get('/dashboard/imports?context=platform&import='.$import->id)->assertOk()->assertSee('Check before importing')->assertSee('Email address is not valid.')->assertSee('Role must be parent, teacher or staff.');
        $this->assertDatabaseMissing('workspace_memberships', ['user_id' => $existing->id]);
        $this->post('/workspace/imports/'.$import->id.'/confirm', ['context' => 'platform'])->assertRedirect();
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $existing->id, 'school_id' => $school, 'role' => 'teacher', 'status' => 'active']);
        $this->assertSame('completed', DB::table('workspace_imports')->value('status'));
    }

    public function test_communications_target_many_schools_and_schedule(): void
    {
        $super = $this->super(); $a = $this->school('North', ['status' => 'active']); $b = $this->school('South', ['status' => 'active']);
        $parent = $this->user('Parent'); $this->member($parent, $a, 'parent');
        $this->actingAs($super)->post('/workspace/posts', ['context' => 'platform', 'kind' => 'notice', 'title' => 'Snow day', 'body' => 'Closed', 'audience' => 'parent', 'target' => 'all'])->assertRedirect();
        $this->assertSame(2, DB::table('workspace_posts')->where('title', 'Snow day')->count());
        $this->getJson('/workspace/posts/recipients?context=platform&audience=parent&target=all')->assertJsonPath('recipients', 1)->assertJsonPath('schools', 2);
        $this->post('/workspace/posts', ['context' => 'platform', 'kind' => 'notice', 'title' => 'Later', 'body' => 'Soon', 'audience' => 'all', 'target' => 'school', 'school_id' => $a,
            'publish_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertRedirect();
        $post = DB::table('workspace_posts')->where('title', 'Later')->first();
        $this->assertNull($post->notified_at);
        $this->actingAs($parent);
        $this->assertFalse(app(\App\Services\WorkspaceDashboard::class)->posts(['schools' => [$a => 'North'], 'role' => 'parent'], 'notice')->where('id', $post->id)->exists());
        $this->travel(2)->days();
        $this->artisan('workspace:publish-scheduled')->assertSuccessful();
        $this->assertNotNull(DB::table('workspace_posts')->where('id', $post->id)->value('notified_at'));
        $this->assertTrue(app(\App\Services\WorkspaceDashboard::class)->posts(['schools' => [$a => 'North'], 'role' => 'parent'], 'notice')->where('id', $post->id)->exists());
    }

    public function test_audit_log_is_readable_filterable_and_records_sign_ins(): void
    {
        $super = $this->super(); $school = $this->school('Birch'); $parent = $this->user('Riley');
        $this->member($parent, $school, 'parent');
        app(\App\Services\WorkspaceAccess::class)->audit($super, $school, 'membership.connected', $parent->id.':parent');
        $this->post('/login-web', ['email' => $super->email, 'password' => 'wrong-password']);
        $this->assertDatabaseHas('workspace_audit', ['actor_id' => $super->id, 'action' => 'auth.failed']);
        $this->actingAs($super)->get('/dashboard/audit?context=platform')->assertOk()->assertSee('Connected an account')->assertSee('Riley Person')->assertDontSee('Actor #');
        $this->get('/dashboard/audit?context=platform&category=signins')->assertOk()->assertSee('Failed sign-in attempt')->assertDontSee('Connected an account');
    }

    public function test_people_export_includes_district_admins(): void
    {
        $super = $this->super(); $school = $this->school('Willow');
        $district = DB::table('workspace_districts')->insertGetId(['name' => 'East', 'market' => 'UK']);
        DB::table('workspace_district_schools')->insert(['district_id' => $district, 'school_id' => $school]);
        $james = $this->user('James');
        DB::table('workspace_memberships')->insert(['user_id' => $james->id, 'district_id' => $district, 'role' => 'district_admin', 'status' => 'active']);
        $report = app(\App\Services\WorkspaceReports::class)->build('people', app(\App\Services\WorkspaceAccess::class)->resolve($super, 'platform'), now()->subDay()->toImmutable(), now()->toImmutable());
        $this->assertContains('District Admin', array_column($report['rows'], 2));
    }

    public function test_classic_panel_and_secrets_are_closed(): void
    {
        $super = $this->super();
        $html = $this->actingAs($super)->get('/dashboard?context=platform')->assertOk()->assertDontSee('Classic admin tools')
            ->assertHeader('Content-Security-Policy')->getContent();
        $this->assertStringNotContainsString('ws-mobile-nav', $html);
        $this->get('/email-settings')->assertRedirect(route('workspace.home'));
        config(['workspace.legacy_admin_enabled' => true, 'environment.MAIL_PASSWORD' => 'SG.super-secret-key']);
        $this->get('/email-settings')->assertDontSee('SG.super-secret-key');
    }

    public function test_student_import_places_students_and_links_verified_guardians(): void
    {
        $super = $this->super(); $school = $this->school('Aspen School');
        $teacher = $this->user('Teach'); $this->member($teacher, $school, 'teacher');
        $class = \App\Models\ClassSchool::create(['school_id' => $school, 'name' => 'Grade 4']);
        $section = \App\Models\Section::create(['school_id' => $school, 'name' => 'Maple']);
        \App\Models\ClassSection::create(['class_id' => $class->id, 'section_id' => $section->id, 'class_teacher_id' => $teacher->id]);
        $parent = $this->user('Guard'); $this->member($parent, $school, 'parent');
        $csv = "first_name,last_name,class,section,school,admission_no,guardian_email\nElla,Lee,Grade 4,Maple,Aspen School,A-100,{$parent->email}\nNoah,Lee,Grade 9,Oak,Aspen School,,\nIsla,Lee,Grade 4,Maple,Aspen School,A-100,\n";
        $this->actingAs($super)->post('/workspace/imports', ['context' => 'platform', 'kind' => 'students', 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)])->assertRedirect();
        $import = DB::table('workspace_imports')->first();
        $this->get('/dashboard/imports?context=platform&import='.$import->id)->assertOk()->assertSee('does not exist at this school')->assertSee('appears earlier in the file');
        $this->post('/workspace/imports/'.$import->id.'/confirm', ['context' => 'platform'])->assertRedirect();
        $student = DB::table('students')->where('admission_no', 'A-100')->first();
        $this->assertNotNull($student);
        $this->assertSame(1, DB::table('students')->where('admission_no', 'A-100')->count());
        $this->assertDatabaseHas('workspace_guardians', ['guardian_id' => $parent->id, 'student_id' => $student->id, 'status' => 'verified']);
        $this->get('/dashboard/students?context=platform')->assertOk()->assertSee('Ella');
    }

    public function test_web_and_app_districts_stay_in_sync(): void
    {
        $super = $this->super(); $school = $this->school('Sync School');
        $us = DB::table('countries')->where('name', 'United States')->value('id') ?? DB::table('countries')->insertGetId(['name' => 'United States', 'code' => 'US', 'phonecode' => 1]);
        $this->actingAs($super)->post('/workspace/districts', ['context' => 'platform', 'name' => 'Metro District', 'market' => 'US'])->assertRedirect();
        $web = DB::table('workspace_districts')->where('name', 'Metro District')->first();
        $this->assertNotNull($web->app_district_id);
        $this->assertDatabaseHas('districts', ['id' => $web->app_district_id, 'name' => 'Metro District', 'country_id' => $us, 'status' => 'active']);
        $this->post('/workspace/districts/'.$web->id.'/schools', ['context' => 'platform', 'school_id' => $school, 'action' => 'add'])->assertRedirect();
        $this->assertSame((int) $web->app_district_id, (int) DB::table('schools')->where('id', $school)->value('district_id'));
        $this->post('/workspace/districts/'.$web->id.'/status', ['context' => 'platform', 'status' => 'suspended', 'reason' => 'Pause'])->assertRedirect();
        $this->assertSame('inactive', DB::table('districts')->where('id', $web->app_district_id)->value('status'));
        // A district created through the app's admin API appears in the web panel.
        $app = \App\Models\District::create(['name' => 'App District', 'country_id' => $us, 'status' => 'active']);
        app(\App\Services\DistrictSync::class)->fromApp($app->id);
        $this->assertDatabaseHas('workspace_districts', ['name' => 'App District', 'market' => 'US', 'app_district_id' => $app->id]);
    }

    public function test_paypal_subscription_cancel_and_refund(): void
    {
        config(['environment.SANDBOX' => true, 'environment.PAYPAL_SANDBOX_ID' => 'id', 'environment.PAYPAL_SANDBOX_SECRET' => 'secret']);
        \Illuminate\Support\Facades\Http::fake([
            '*/v1/oauth2/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 't']),
            '*/v1/billing/subscriptions/I-SUB/transactions*' => \Illuminate\Support\Facades\Http::response(['transactions' => [
                ['id' => 'PAY-1', 'status' => 'COMPLETED', 'time' => now()->toIso8601String(), 'amount_with_breakdown' => ['gross_amount' => ['value' => '9.99', 'currency_code' => 'USD']]]]]),
            '*/v1/billing/subscriptions/I-SUB/cancel' => \Illuminate\Support\Facades\Http::response(null, 204),
            '*/v1/billing/subscriptions/I-SUB' => \Illuminate\Support\Facades\Http::response(['status' => 'ACTIVE']),
            '*/v1/payments/sale/PAY-1/refund' => \Illuminate\Support\Facades\Http::response(['id' => 'REF-1', 'state' => 'completed']),
        ]);
        $super = $this->super(); $payer = $this->user('Payer');
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('user_subscription')->insert(['id' => $id, 'user_id' => $payer->id, 'email' => $payer->email, 'subscription_id' => 'I-SUB', 'plan_id' => 'P-9',
            'type' => 'REGULAR-Month', 'status' => 'ACTIVE', 'expire_date' => now()->addMonth()->toDateString(), 'remaining_time' => 600, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($super)->get('/dashboard/billing/subscriptions/'.$id.'?context=platform')->assertOk()->assertSee('PAY-1')->assertSee('Refund');
        $this->postJson('/workspace/billing/subscriptions/'.$id.'/refund', ['context' => 'platform', 'transaction_id' => 'PAY-1', 'amount' => '20', 'currency' => 'USD', 'reason' => 'x'])->assertStatus(422);
        $this->postJson('/workspace/billing/subscriptions/'.$id.'/refund', ['context' => 'platform', 'transaction_id' => 'OTHER', 'currency' => 'USD', 'reason' => 'x'])->assertStatus(422);
        $this->post('/workspace/billing/subscriptions/'.$id.'/refund', ['context' => 'platform', 'transaction_id' => 'PAY-1', 'currency' => 'USD', 'reason' => 'Dropped calls'])->assertRedirect();
        $this->assertDatabaseHas('workspace_audit', ['action' => 'payment.refunded']);
        $this->post('/workspace/billing/subscriptions/'.$id.'/cancel', ['context' => 'platform', 'reason' => 'Customer request'])->assertRedirect();
        $this->assertSame('INACTIVE', DB::table('user_subscription')->where('id', $id)->value('status'));
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/billing/subscriptions/I-SUB/cancel'));
    }
}
