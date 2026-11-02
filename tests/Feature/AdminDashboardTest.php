<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function actingAs($user, $guard = null)
    {
        $this->flushSession();
        return parent::actingAs($user, $guard ?? 'web');
    }

    private function user(string $name = 'Admin'): User
    {
        return User::forceCreate(['first_name' => $name, 'last_name' => 'Person', 'type' => 'parent', 'email' => strtolower($name).uniqid().'@example.com',
            'password' => Hash::make('secret123'), 'status' => 1, 'email_verified_at' => now()]);
    }

    private function school(string $name): int
    {
        return DB::table('schools')->insertGetId(['user_id' => 1, 'country_id' => 1, 'school_name' => $name, 'address' => 'Road', 'image' => '', 'post_code' => '00000']);
    }

    private function membership(User $user, int $school, string $role, array $permissions = []): string
    {
        return 'm'.DB::table('workspace_memberships')->insertGetId(['user_id' => $user->id, 'school_id' => $school, 'role' => $role,
            'status' => 'active', 'permissions' => json_encode($permissions), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** One context per administrative panel, all covering $school. */
    private function panels(int $school): array
    {
        $super = $this->user('Super'); $super->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $district = DB::table('workspace_districts')->insertGetId(['name' => 'North District', 'market' => 'US']);
        DB::table('workspace_district_schools')->insert(['district_id' => $district, 'school_id' => $school]);
        $districtAdmin = $this->user('District');
        $districtContext = 'm'.DB::table('workspace_memberships')->insertGetId(['user_id' => $districtAdmin->id, 'district_id' => $district, 'role' => 'district_admin', 'status' => 'active']);
        $schoolAdmin = $this->user('Principal'); $sub = $this->user('Delegate');
        return [
            'super_admin' => [$super, 'platform'],
            'sub_super_admin' => [$sub, $this->membership($sub, $school, 'sub_super_admin', ['analytics.view', 'reports.export', 'audit.view', 'memberships.manage', 'students.view'])],
            'district_admin' => [$districtAdmin, $districtContext],
            'school_admin' => [$schoolAdmin, $this->membership($schoolAdmin, $school, 'school_admin')],
        ];
    }

    public function test_all_four_panels_share_the_admin_menu_and_dashboard_sections(): void
    {
        $school = $this->school('Lincoln Elementary');
        $parent = $this->user('Jessica'); $this->membership($parent, $school, 'parent');
        foreach ($this->panels($school) as $role => [$user, $context]) {
            $this->actingAs($user)->get('/dashboard?context='.$context)->assertOk()
                ->assertSeeInOrder(['Dashboard', 'Video Calls', 'Parents / Guardians', 'Teachers', 'Staff', 'Students', 'Communications', 'Analytics &amp; Insights', 'Reports &amp; Exports', 'Settings'], false)
                ->assertSee('Video Communication Center')->assertSee('Attention needed')->assertSee('Recent sign ups')->assertSee('Jessica')
                ->assertSee('chart-growth')->assertSee('Parents / Guardians')->assertSee('Districts');
        }
    }

    public function test_menu_items_follow_role_and_delegated_permissions(): void
    {
        $school = $this->school('Scoped School');
        $panels = $this->panels($school);
        [$super, $platform] = $panels['super_admin'];
        $this->actingAs($super)->get('/dashboard?context='.$platform)->assertSee('Subscriptions &amp; Billing', false)->assertSee('Sub Super Admins');
        [$principal, $schoolContext] = $panels['school_admin'];
        $this->actingAs($principal)->get('/dashboard?context='.$schoolContext)->assertDontSee('Subscriptions &amp; Billing', false)->assertDontSee('Sub Super Admins');
        $this->get('/dashboard/billing?context='.$schoolContext)->assertForbidden();

        $limited = $this->user('Limited'); $context = $this->membership($limited, $school, 'sub_super_admin', ['notices.publish']);
        $this->actingAs($limited)->get('/dashboard?context='.$context)->assertOk()->assertDontSee('Analytics &amp; Insights', false)->assertDontSee('Reports &amp; Exports', false);
        $this->get('/dashboard/analytics?context='.$context)->assertForbidden();
        $this->get('/dashboard/reports?context='.$context)->assertForbidden();
        $this->post('/workspace/reports/exports', ['context' => $context, 'type' => 'schools'])->assertForbidden();
        $this->get('/dashboard/audit?context='.$context)->assertForbidden();

        $staff = $this->user('Staff'); $staffContext = $this->membership($staff, $school, 'staff', ['web.access']);
        $this->actingAs($staff)->get('/dashboard/analytics?context='.$staffContext)->assertForbidden();
        $this->get('/dashboard/video?context='.$staffContext)->assertForbidden();
    }

    public function test_analytics_are_limited_to_the_context_schools(): void
    {
        $mine = $this->school('Mine'); $other = $this->school('Other');
        $admin = $this->user('Principal'); $context = $this->membership($admin, $mine, 'school_admin');
        foreach (range(1, 3) as $i) { $this->membership($this->user('Parent'.$i), $mine, 'parent'); }
        foreach (range(1, 5) as $i) { $this->membership($this->user('Outside'.$i), $other, 'parent'); }
        $insider = $this->user('Caller'); $this->membership($insider, $mine, 'teacher');
        $outsider = $this->user('Stranger'); $this->membership($outsider, $other, 'teacher');
        DB::table('call_usage_ledger')->insert([
            ['user_id' => $insider->id, 'user_call_room_id' => 'r1', 'channel_name' => 'c1', 'seconds' => 600, 'source' => 'server', 'created_at' => now()],
            ['user_id' => $outsider->id, 'user_call_room_id' => 'r2', 'channel_name' => 'c2', 'seconds' => 6000, 'source' => 'server', 'created_at' => now()],
        ]);
        $response = $this->actingAs($admin)->getJson('/dashboard/analytics?context='.$context.'&grain=month&from='.now()->subMonths(2)->toDateString().'&to='.now()->toDateString())->assertOk();
        $this->assertSame(3, collect($response->json('kpis'))->firstWhere('key', 'parents')['value']);
        $this->assertSame(10, $response->json('totals.video_minutes'));
        $this->assertSame(1, $response->json('totals.video_calls'));
        $this->assertCount(3, $response->json('series.labels'));
        $this->assertEquals(3, array_sum($response->json('series.parents')));
        $this->get('/dashboard/analytics?context='.$context)->assertOk()->assertSee('Analytics &amp; Insights', false)->assertSee('a-growth');
        $this->getJson('/dashboard/analytics?context='.$context.'&from=2026-05-01&to=2026-01-01')->assertStatus(422);
    }

    public function test_reports_export_in_every_format_and_stay_in_scope(): void
    {
        $mine = $this->school('Mine'); $other = $this->school('Other');
        $admin = $this->user('Principal'); $context = $this->membership($admin, $mine, 'school_admin');
        $risky = User::forceCreate(['first_name' => '=HYPERLINK("x")', 'last_name' => 'Parent', 'type' => 'parent', 'email' => 'risky@example.com',
            'password' => Hash::make('secret123'), 'status' => 1, 'email_verified_at' => now()]);
        $this->membership($risky, $mine, 'parent');
        $this->membership($this->user('Concealed'), $other, 'parent');

        $this->actingAs($admin)->get('/dashboard/reports?context='.$context.'&type=people')->assertOk()
            ->assertSee('risky@example.com')->assertDontSee('concealed')->assertSee('Excel (XLSX)');
        \Illuminate\Support\Facades\Storage::fake('private');
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\GenerateWorkspaceExport::class]);
        $export = function (string $type, string $format) use ($context) {
            $this->post('/workspace/reports/exports', ['context' => $context, 'type' => $type, 'format' => $format])->assertRedirect()->assertSessionHas('workspace_success');
            $id = DB::table('workspace_exports')->max('id');
            app()->call([new \App\Jobs\GenerateWorkspaceExport($id), 'handle']);
            return DB::table('workspace_exports')->find($id);
        };
        $csv = $export('people', 'csv');
        $this->assertSame('ready', $csv->status);
        $content = $this->get('/workspace/reports/exports/'.$csv->id.'?context='.$context)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString('concealed', $content);
        $this->get('/workspace/reports/exports/'.$export('schools', 'xlsx')->id.'?context='.$context)->assertOk()->assertDownload();
        $this->get('/workspace/reports/exports/'.$export('activity', 'pdf')->id.'?context='.$context)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/dashboard/reports?context='.$context)->assertOk()->assertSee('Your exports')->assertSee('Download (2 rows)');
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\GenerateWorkspaceExport::class, 3);
        $this->post('/workspace/reports/exports', ['context' => $context, 'type' => 'subscriptions', 'format' => 'csv'])->assertStatus(302)->assertSessionHasErrors('type');
        $this->post('/workspace/reports/exports', ['context' => $context, 'type' => 'schools', 'school_id' => $other])->assertSessionHasErrors('school_id');
        $this->assertSame(3, DB::table('workspace_audit')->where('actor_id', $admin->id)->where('action', 'like', 'report.%.exported')->count());

        // Another user cannot fetch the file; expired files are pruned and no longer download.
        $stranger = $this->user('Other'); $strangerContext = $this->membership($stranger, $mine, 'school_admin');
        $this->actingAs($stranger)->get('/workspace/reports/exports/'.$csv->id.'?context='.$strangerContext)->assertNotFound();
        DB::table('workspace_exports')->where('id', $csv->id)->update(['expires_at' => now()->subMinute()]);
        $this->artisan('workspace:prune-exports')->assertSuccessful();
        \Illuminate\Support\Facades\Storage::disk('private')->assertMissing($csv->path);
        $this->actingAs($admin)->get('/workspace/reports/exports/'.$csv->id.'?context='.$context)->assertNotFound();
    }

    public function test_export_is_refused_when_access_is_revoked_before_generation(): void
    {
        $school = $this->school('Revoked');
        $admin = $this->user('Principal'); $context = $this->membership($admin, $school, 'school_admin');
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\GenerateWorkspaceExport::class]);
        $this->actingAs($admin)->post('/workspace/reports/exports', ['context' => $context, 'type' => 'schools', 'format' => 'csv'])->assertRedirect();
        DB::table('workspace_memberships')->where('user_id', $admin->id)->update(['status' => 'revoked']);
        $id = DB::table('workspace_exports')->max('id');
        app()->call([new \App\Jobs\GenerateWorkspaceExport($id), 'handle']);
        $this->assertSame('failed', DB::table('workspace_exports')->where('id', $id)->value('status'));
    }

    public function test_support_tickets_are_scoped_and_internal_notes_stay_private(): void
    {
        $mine = $this->school('Mine'); $other = $this->school('Other');
        $panels = $this->panels($mine);
        [$super, $platform] = $panels['super_admin'];
        [$principal, $schoolContext] = $panels['school_admin'];
        $outsider = $this->user('Outsider'); $outsiderContext = $this->membership($outsider, $other, 'school_admin');

        $this->actingAs($principal)->post('/workspace/support', ['context' => $schoolContext, 'subject' => 'Import failed', 'category' => 'technical', 'body' => 'The roster import stopped.'])->assertRedirect();
        $id = DB::table('workspace_support_tickets')->value('id');
        $this->assertSame($mine, (int) DB::table('workspace_support_tickets')->value('school_id'));
        $this->get('/dashboard/support/'.$id.'?context='.$schoolContext)->assertOk()->assertSee('The roster import stopped.');
        $this->post('/workspace/support/'.$id.'/status', ['context' => $schoolContext, 'status' => 'resolved'])->assertForbidden();
        $this->post('/workspace/support/'.$id.'/replies', ['context' => $schoolContext, 'body' => 'Sneaky', 'internal' => 1])->assertForbidden();

        $this->actingAs($outsider)->get('/dashboard/support/'.$id.'?context='.$outsiderContext)->assertNotFound();
        $this->get('/dashboard/support?context='.$outsiderContext)->assertOk()->assertDontSee('Import failed');

        $this->actingAs($super)->get('/dashboard?context='.$platform)->assertSee('Support tickets');
        $this->post('/workspace/support/'.$id.'/replies', ['context' => $platform, 'body' => 'Checking the logs', 'internal' => 1])->assertRedirect();
        $this->post('/workspace/support/'.$id.'/replies', ['context' => $platform, 'body' => 'Fixed, please retry'])->assertRedirect();
        $this->assertSame('in_progress', DB::table('workspace_support_tickets')->value('status'));
        $this->post('/workspace/support/'.$id.'/status', ['context' => $platform, 'status' => 'resolved', 'assign' => 'me'])->assertRedirect();
        $this->assertSame($super->id, (int) DB::table('workspace_support_tickets')->value('assigned_to'));

        $this->actingAs($principal)->get('/dashboard/support/'.$id.'?context='.$schoolContext)->assertSee('Fixed, please retry')->assertDontSee('Checking the logs');
        $this->post('/workspace/support/'.$id.'/replies', ['context' => $schoolContext, 'body' => 'Still failing'])->assertRedirect();
        $this->assertSame('open', DB::table('workspace_support_tickets')->value('status'));
        $this->post('/workspace/support/'.$id.'/status', ['context' => $schoolContext, 'status' => 'closed'])->assertRedirect();
        $this->post('/workspace/support/'.$id.'/replies', ['context' => $schoolContext, 'body' => 'Again'])->assertStatus(422);

        // Sub-Super Admins handle tickets only with support.manage.
        [$sub, $subContext] = $panels['sub_super_admin'];
        $this->actingAs($sub)->post('/workspace/support/'.$id.'/status', ['context' => $subContext, 'status' => 'open'])->assertNotFound();
        DB::table('workspace_memberships')->where('user_id', $sub->id)->update(['permissions' => json_encode(['support.manage'])]);
        $this->post('/workspace/support/'.$id.'/status', ['context' => $subContext, 'status' => 'open'])->assertRedirect();
    }

    public function test_mobile_users_raise_and_follow_only_their_own_tickets(): void
    {
        $school = $this->school('App School'); $elsewhere = $this->school('Elsewhere');
        $parent = $this->user('Parent'); $this->membership($parent, $school, 'parent'); $other = $this->user('Another');
        $this->actingAs($parent, 'sanctum')->postJson('/api/support/tickets', ['subject' => 'Cannot join call', 'category' => 'video', 'body' => 'Black screen', 'school_id' => $elsewhere])->assertStatus(422);
        $id = $this->postJson('/api/support/tickets', ['subject' => 'Cannot join call', 'category' => 'video', 'body' => 'Black screen', 'school_id' => $school])->assertCreated()->json('id');
        $this->getJson('/api/support/tickets')->assertOk()->assertJsonPath('tickets.0.subject', 'Cannot join call');
        DB::table('workspace_support_replies')->insert(['ticket_id' => $id, 'author_id' => $other->id, 'body' => 'Internal only', 'internal' => true, 'created_at' => now()]);
        $this->getJson('/api/support/tickets/'.$id)->assertOk()->assertJsonCount(1, 'replies')->assertJsonMissingPath('ticket.email');
        $this->postJson('/api/support/tickets/'.$id.'/replies', ['body' => 'Still broken'])->assertCreated();
        $this->actingAs($other, 'sanctum')->getJson('/api/support/tickets/'.$id)->assertNotFound();
        $this->postJson('/api/support/tickets/'.$id.'/replies', ['body' => 'Hijack'])->assertNotFound();
    }

    public function test_video_audit_and_billing_pages_render_for_authorized_panels(): void
    {
        $school = $this->school('Video School');
        $panels = $this->panels($school);
        [$super, $platform] = $panels['super_admin'];
        DB::table('user_subscription')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $super->id, 'email' => $super->email,
            'plan_id' => 'P-1', 'type' => 'TRAIL', 'status' => 'ACTIVE', 'activated_date' => now()->toDateString(), 'expire_date' => now()->addDays(3)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($super)->get('/dashboard/billing?context='.$platform)->assertOk()->assertSee('P-1')->assertSee('Active trials');
        $this->get('/dashboard?context='.$platform)->assertOk()->assertSee('Expiring subscriptions');
        foreach ($panels as [$user, $context]) {
            $this->actingAs($user)->get('/dashboard/video?context='.$context)->assertOk()->assertSee('Video Communication Center');
            $this->get('/dashboard/audit?context='.$context)->assertOk()->assertSee('Audit / Activity Log');
        }
    }
}
