<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function actingAs($user, $guard = null)
    {
        $this->flushSession();
        return parent::actingAs($user, $guard ?? 'web');
    }

    private function user(string $name): User
    {
        return User::forceCreate(['first_name' => $name, 'last_name' => 'Person', 'email' => strtolower($name).uniqid().'@example.com',
            'password' => Hash::make('secret123'), 'type' => 'parent', 'status' => 1, 'email_verified_at' => now()]);
    }

    private function school(string $name = 'School'): int
    {
        return DB::table('schools')->insertGetId(['user_id' => 1, 'country_id' => 1, 'school_name' => $name,
            'address' => 'School Road', 'image' => '', 'post_code' => '00000', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function membership(User $user, int $school, string $role, array $permissions = []): string
    {
        return 'm'.DB::table('workspace_memberships')->insertGetId(['user_id' => $user->id, 'school_id' => $school,
            'role' => $role, 'status' => 'active', 'permissions' => json_encode($role === 'staff' ? array_unique(array_merge(['web.access'], $permissions)) : $permissions), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function student(int $school, ?User $teacher = null): array
    {
        $class = DB::table('classes')->insertGetId(['name' => 'Year 4', 'school_id' => $school]);
        $section = DB::table('sections')->insertGetId(['name' => 'A', 'school_id' => $school]);
        $cs = DB::table('class_sections')->insertGetId(['class_id' => $class, 'section_id' => $section, 'class_teacher_id' => $teacher?->id]);
        $user = $this->user('Student');
        $id = DB::table('students')->insertGetId(['user_id' => $user->id, 'class_section_id' => $cs, 'class_status' => 'ACCEPTED']);
        return [$id, $user, $cs];
    }

    private function guardian(User $parent, int $school, int $student, string $status = 'verified'): int
    {
        return DB::table('workspace_guardians')->insertGetId(['guardian_id' => $parent->id, 'school_id' => $school,
            'student_id' => $student, 'status' => $status, 'verified_at' => $status === 'verified' ? now() : null]);
    }

    public function test_universal_login_uses_memberships_not_user_type(): void
    {
        $staff = $this->user('Staff'); $school = $this->school(); $this->membership($staff, $school, 'staff');
        $this->post('/login-web', ['email' => $staff->email, 'password' => 'secret123'])->assertRedirect(route('workspace.home'));
        $this->get('/dashboard')->assertOk()->assertSee('Staff Member')->assertSee('Support your school day.');
        $this->get('/roles')->assertRedirect(route('login'));
    }

    public function test_all_role_homes_and_shared_navigation_render(): void
    {
        foreach (['staff', 'school_admin'] as $role) {
            $user = $this->user(ucfirst($role)); $school = $this->school(); $context = $this->membership($user, $school, $role);
            $this->actingAs($user)->get('/dashboard?context='.$context)->assertOk()
                ->assertSee(WorkspaceAccess::LABELS[$role])->assertSee('Messages')->assertSee('Schedule')->assertSee('Events')->assertSee('Profile');
        }
    }

    public function test_every_workspace_page_renders_for_authorized_roles(): void
    {
        foreach (['staff', 'school_admin'] as $role) {
            $u = $this->user('Render'); $s = $this->school(); $c = $this->membership($u, $s, $role, WorkspaceAccess::STAFF_PERMISSIONS);
            [$student] = $this->student($s, $u); $this->guardian($u, $s, $student);
            foreach (['students', 'updates', 'messages', 'schedule', 'events', 'notices', 'profile'] as $page) {
                $this->actingAs($u)->get('/dashboard/'.$page.'?context='.$c)->assertStatus($role === 'staff' && $page === 'updates' ? 403 : 200);
            }
            $this->get('/dashboard/people?context='.$c)->assertStatus($role === 'school_admin' ? 200 : 403);
        }
    }

    public function test_forged_context_and_inactive_or_expired_memberships_are_denied(): void
    {
        $u = $this->user('One'); $v = $this->user('Two'); $s = $this->school(); $c = $this->membership($u, $s, 'teacher');
        $this->actingAs($v)->getJson('/api/dashboard?context='.$c)->assertForbidden();
        DB::table('workspace_memberships')->where('user_id', $u->id)->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($u)->getJson('/api/dashboard?context='.$c)->assertForbidden();
        $u->update(['status' => 0]);
        $this->getJson('/api/dashboard?context='.$c)->assertForbidden();
    }

    public function test_parent_only_sees_verified_students_and_published_updates(): void
    {
        $parent = $this->user('Parent'); $teacher = $this->user('Teacher'); $s = $this->school();
        $pc = $this->membership($parent, $s, 'parent'); $tc = $this->membership($teacher, $s, 'school_admin');
        [$student] = $this->student($s, $teacher); [$other] = $this->student($s, $teacher);
        $this->guardian($parent, $s, $student); $this->guardian($parent, $s, $other, 'pending');
        foreach (['draft', 'published'] as $status) {
            $this->actingAs($teacher)->postJson('/workspace/updates', ['context' => $tc, 'school_id' => $s, 'student_id' => $student,
                'title' => $status.' title', 'body' => 'Progress detail', 'status' => $status])->assertOk();
        }
        $response = $this->actingAs($parent)->getJson('/api/dashboard?context='.$pc)->assertOk();
        $response->assertJsonCount(1, 'students')->assertJsonCount(1, 'updates')->assertJsonPath('updates.0.status', 'published');
        $this->assertArrayNotHasKey('father_id', $response->json('students.0'));
    }

    public function test_teacher_cannot_update_another_section_or_school(): void
    {
        $u = $this->user('Teacher'); $other = $this->user('Other'); $s = $this->school(); $outside = $this->school();
        $c = $this->membership($u, $s, 'teacher'); [$student] = $this->student($s, $other);
        $this->actingAs($u)->postJson('/workspace/updates', ['context' => $c, 'school_id' => $s, 'student_id' => $student,
            'title' => 'Hidden', 'body' => 'No access', 'status' => 'published'])->assertForbidden();
        $this->postJson('/workspace/posts', ['context' => $c, 'school_id' => $outside, 'kind' => 'notice', 'title' => 'Attack', 'body' => 'Text', 'audience' => 'all'])->assertForbidden();
        $this->assertDatabaseCount('workspace_updates', 0);
    }

    public function test_published_corrections_preserve_history_and_reject_stale_versions(): void
    {
        $u = $this->user('Teacher'); $s = $this->school(); $c = $this->membership($u, $s, 'school_admin'); [$student] = $this->student($s, $u);
        $data = ['context' => $c, 'school_id' => $s, 'student_id' => $student, 'title' => 'Progress', 'body' => 'Original', 'status' => 'published'];
        $this->actingAs($u)->postJson('/workspace/updates', $data)->assertOk(); $id = DB::table('workspace_updates')->value('id');
        $data += ['update_id' => $id, 'version' => 1]; $data['body'] = 'Corrected';
        $this->postJson('/workspace/updates', $data)->assertOk();
        $this->postJson('/workspace/updates', $data)->assertStatus(409);
        $this->assertDatabaseCount('workspace_update_versions', 2);
        $this->assertDatabaseHas('workspace_update_versions', ['update_id' => $id, 'version' => 1, 'body' => 'Original']);
        $data['version'] = 2; $data['status'] = 'draft';
        $this->postJson('/workspace/updates', $data)->assertUnprocessable();
    }

    public function test_staff_permissions_are_enforced_server_side(): void
    {
        $u = $this->user('Staff'); $s = $this->school(); $c = $this->membership($u, $s, 'staff');
        $data = ['context' => $c, 'school_id' => $s, 'kind' => 'notice', 'title' => 'News', 'body' => 'School news', 'audience' => 'all'];
        $this->actingAs($u)->postJson('/workspace/posts', $data)->assertForbidden();
        DB::table('workspace_memberships')->where('user_id', $u->id)->update(['permissions' => json_encode(['web.access', 'notices.publish'])]);
        $this->postJson('/workspace/posts', $data)->assertOk();
        $data['kind'] = 'event'; $data['starts_at'] = now()->addDay()->toIso8601String();
        $this->postJson('/workspace/posts', $data)->assertForbidden();
        $this->get('/dashboard/students?context='.$c)->assertForbidden();
    }

    public function test_administrator_cannot_grant_privileged_roles_or_touch_other_school(): void
    {
        $u = $this->user('Admin'); $v = $this->user('Target'); $s = $this->school(); $other = $this->school(); $c = $this->membership($u, $s, 'school_admin');
        $this->actingAs($u)->postJson('/workspace/memberships', ['context' => $c, 'school_id' => $s, 'email' => $v->email, 'role' => 'school_admin'])->assertUnprocessable();
        $this->postJson('/workspace/memberships', ['context' => $c, 'school_id' => $other, 'email' => $v->email, 'role' => 'staff'])->assertUnprocessable();
        $this->postJson('/workspace/memberships', ['context' => $c, 'school_id' => $s, 'email' => $v->email, 'role' => 'staff', 'permissions' => ['events.manage']])->assertOk();
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $v->id, 'school_id' => $s, 'role' => 'staff']);
    }

    public function test_guardian_approval_and_revocation_change_parent_visibility(): void
    {
        $a = $this->user('Admin'); $p = $this->user('Parent'); $s = $this->school(); $ac = $this->membership($a, $s, 'school_admin'); $pc = $this->membership($p, $s, 'parent');
        [$student] = $this->student($s);
        $id = $this->guardian($p, $s, $student, 'pending');
        $this->actingAs($p)->postJson('/workspace/guardians', ['context' => $pc, 'school_id' => $s, 'student_id' => $student])->assertForbidden();
        $this->getJson('/api/dashboard?context='.$pc)->assertJsonCount(0, 'students');
        $this->actingAs($a)->postJson('/workspace/guardians/'.$id.'/status', ['context' => $ac, 'status' => 'verified'])->assertOk();
        $this->actingAs($p)->getJson('/api/dashboard?context='.$pc)->assertJsonCount(1, 'students');
        $this->actingAs($a)->postJson('/workspace/guardians/'.$id.'/status', ['context' => $ac, 'status' => 'revoked'])->assertOk();
        $this->actingAs($p)->getJson('/api/dashboard?context='.$pc)->assertJsonCount(0, 'students');
    }

    public function test_stale_session_cannot_keep_revoked_membership(): void
    {
        $a = $this->user('Admin'); $t = $this->user('Teacher'); $s = $this->school(); $ac = $this->membership($a, $s, 'school_admin'); $tc = $this->membership($t, $s, 'teacher');
        $this->actingAs($a)->postJson('/workspace/memberships/'.substr($tc, 1).'/revoke', ['context' => $ac])->assertOk();
        $this->actingAs($t)->withSession(['workspace_context' => $tc])->get('/dashboard')->assertForbidden();
    }

    public function test_district_is_limited_to_linked_schools_and_renders_aggregate(): void
    {
        $u = $this->user('District'); $a = $this->school('Alpha'); $b = $this->school('Beta'); $outside = $this->school('Outside');
        $district = DB::table('workspace_districts')->insertGetId(['name' => 'District One', 'market' => 'UK']);
        DB::table('workspace_district_schools')->insert([['district_id' => $district, 'school_id' => $a], ['district_id' => $district, 'school_id' => $b]]);
        $id = DB::table('workspace_memberships')->insertGetId(['user_id' => $u->id, 'district_id' => $district, 'role' => 'district_admin', 'status' => 'active']);
        $this->actingAs($u)->get('/dashboard?context=m'.$id)->assertOk()->assertSee('Alpha')->assertSee('Beta')->assertDontSee('Outside');
        $this->getJson('/api/dashboard?context=m'.$id.'s'.$outside)->assertForbidden();
        $this->getJson('/api/dashboard?context=m'.$id.'s'.$a)->assertOk()->assertJsonPath('context.organization', 'Alpha');
    }

    public function test_multiple_roles_and_schools_do_not_merge_privileges(): void
    {
        $u = $this->user('Multiple'); $a = $this->school(); $b = $this->school();
        $parent = $this->membership($u, $a, 'parent'); $staff = $this->membership($u, $b, 'staff', ['notices.publish']);
        $this->actingAs($u)->getJson('/api/me/workspaces')->assertOk()->assertJsonCount(2, 'contexts');
        $this->postJson('/workspace/posts', ['context' => $parent, 'school_id' => $a, 'kind' => 'notice', 'title' => 'No', 'body' => 'Text', 'audience' => 'all'])->assertForbidden();
        $this->postJson('/workspace/posts', ['context' => $staff, 'school_id' => $a, 'kind' => 'notice', 'title' => 'No', 'body' => 'Text', 'audience' => 'all'])->assertUnprocessable();
    }

    public function test_private_messages_and_meetings_require_current_relationships(): void
    {
        $p = $this->user('Parent'); $t = $this->user('Teacher'); $stranger = $this->user('Stranger'); $s = $this->school();
        $pc = $this->membership($p, $s, 'parent'); $tc = $this->membership($t, $s, 'teacher'); $sc = $this->membership($stranger, $s, 'teacher');
        [$student] = $this->student($s, $t); $g = $this->guardian($p, $s, $student);
        $this->actingAs($p)->postJson('/workspace/messages', ['context' => $pc, 'school_id' => $s, 'recipient_id' => $stranger->id, 'body' => 'No'])->assertForbidden();
        $id = DB::table('workspace_messages')->insertGetId(['school_id'=>$s, 'sender_id'=>$p->id, 'recipient_id'=>$t->id, 'body'=>'Private hello', 'created_at'=>now(), 'updated_at'=>now()]);
        $this->actingAs($stranger, 'sanctum')->getJson('/api/dashboard?context='.$sc)->assertJsonPath('summary.1.value', 0);
        $this->actingAs($t, 'sanctum')->getJson('/api/dashboard?context='.$tc)->assertJsonPath('summary.1.value', 1);
        DB::table('workspace_guardians')->where('id', $g)->update(['status' => 'revoked']);
        $this->actingAs($t, 'sanctum')->getJson('/api/dashboard?context='.$tc)->assertJsonPath('summary.1.value', 0);
    }

    public function test_appointments_enforce_conflicts_participant_approval_and_closed_states(): void
    {
        $a = $this->user('Organizer'); $b = $this->user('Recipient'); $s = $this->school(); $ac = $this->membership($a, $s, 'staff'); $bc = $this->membership($b, $s, 'staff');
        $data = ['context' => $ac, 'school_id' => $s, 'participant_id' => $b->id, 'title' => 'Discuss event', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHour()->toIso8601String()];
        $this->actingAs($a)->postJson('/workspace/appointments', $data)->assertOk();
        $this->postJson('/workspace/appointments', $data)->assertStatus(409);
        $id = DB::table('workspace_appointments')->value('id');
        $this->postJson('/workspace/appointments/'.$id.'/status', ['context' => $ac, 'status' => 'accepted'])->assertForbidden();
        $this->actingAs($b)->postJson('/workspace/appointments/'.$id.'/status', ['context' => $bc, 'status' => 'accepted'])->assertOk();
        $this->postJson('/workspace/appointments/'.$id.'/status', ['context' => $bc, 'status' => 'cancelled'])->assertOk();
        $this->postJson('/workspace/appointments/'.$id.'/status', ['context' => $bc, 'status' => 'accepted'])->assertStatus(409);
    }

    public function test_profile_cannot_change_another_account_or_permissions(): void
    {
        $u = $this->user('Profile'); $v = $this->user('Other'); $c = $this->membership($u, $this->school(), 'staff');
        $this->actingAs($u)->postJson('/workspace/profile', ['context' => $c, 'id' => $v->id, 'first_name' => 'Changed', 'last_name' => 'Name', 'role' => 'school_admin', 'type' => 'Principal'])->assertOk();
        $this->assertEquals('Other', $v->fresh()->first_name); $this->assertEquals('parent', $u->fresh()->type);
        $this->postJson('/workspace/profile', ['context' => $c, 'first_name' => 'Changed', 'last_name' => 'Name', 'password' => 'long-password-123', 'password_confirmation' => 'long-password-123', 'current_password' => 'wrong'])->assertUnprocessable();
    }

    public function test_context_switch_does_not_grant_membership_and_logout_works(): void
    {
        $u = $this->user('Switch'); $s = $this->school(); $c = $this->membership($u, $s, 'staff');
        $this->actingAs($u)->post('/workspace/select', ['context' => 'm999999'])->assertForbidden();
        $this->post('/workspace/select', ['context' => $c])->assertRedirect(route('workspace.home'))->assertSessionHas('workspace_context', $c);
        $this->post('/workspace/logout')->assertRedirect(route('login')); $this->assertGuest();
    }
    public function test_saved_timezone_is_used_for_meetings_and_rendering(): void
    {
        $a = $this->user('Time'); $b = $this->user('Contact'); $s = $this->school();
        $ac = $this->membership($a, $s, 'staff'); $this->membership($b, $s, 'staff');
        $this->actingAs($a)->postJson('/workspace/profile', ['context' => $ac, 'first_name' => 'Time', 'last_name' => 'Person', 'timezone' => 'Asia/Karachi'])->assertOk();
        $start = now()->addDays(3)->timezone('Asia/Karachi')->startOfHour();
        $this->postJson('/workspace/appointments', ['context' => $ac, 'school_id' => $s, 'participant_id' => $b->id, 'title' => 'Local time meeting',
            'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i')])->assertOk();
        $this->assertDatabaseHas('workspace_appointments', ['starts_at' => $start->copy()->utc()->toDateTimeString()]);
        $this->get('/dashboard/schedule?context='.$ac)->assertOk()->assertSee('Asia/Karachi');
        $this->postJson('/workspace/profile', ['context' => $ac, 'first_name' => 'Time', 'last_name' => 'Person', 'timezone' => 'Invalid/Zone'])->assertUnprocessable();
    }

    public function test_legacy_principal_access_is_bound_to_the_student_school(): void
    {
        $u = $this->user('Principal');
        $u->assignRole(Role::findOrCreate('Principal', 'web'));
        $own = $this->school(); $outside = $this->school();
        DB::table('principal')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $u->id]);
        DB::table('schools')->where('id', $own)->update(['principal_id' => $u->id]);
        [$one, $student, $section] = $this->student($own); [$two, $stranger, $otherSection] = $this->student($outside);
        $this->assertTrue((bool) \App\Support\Access::canViewStudent($u, $student->id));
        $this->assertFalse((bool) \App\Support\Access::canViewStudent($u, $stranger->id));
        $ownClass = DB::table('class_sections')->where('id', $section)->value('class_id');
        $outsideClass = DB::table('class_sections')->where('id', $otherSection)->value('class_id');
        $this->assertTrue(\App\Support\Access::principalOwnsClass($u, $ownClass));
        $this->assertFalse(\App\Support\Access::principalOwnsClass($u, $outsideClass));
        $this->actingAs($u, 'sanctum')->getJson('/api/student-attendance?student_id='.$stranger->id)->assertForbidden();
    }

    public function test_disabled_and_unverified_users_cannot_sign_in_to_a_workspace(): void
    {
        $u = $this->user('Inactive'); $this->membership($u, $this->school(), 'staff'); $u->update(['status' => 0]);
        $this->post('/login-web', ['email' => $u->email, 'password' => 'secret123'])->assertRedirect(); $this->assertGuest();
        $u->update(['status' => 1, 'email_verified_at' => null]);
        $this->post('/login-web', ['email' => $u->email, 'password' => 'secret123'])->assertRedirect(); $this->assertGuest();
    }

    public function test_dashboard_accepts_a_real_bearer_token_and_no_public_access(): void
    {
        $u = $this->user('Token'); $c = $this->membership($u, $this->school(), 'staff');
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $token = $u->createToken('workspace-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/dashboard?context='.$c)->assertOk()->assertJsonPath('context.role', 'staff');
    }

    public function test_school_announcements_filter_audience_and_unrelated_schools(): void
    {
        $u = $this->user('Audience'); $s = $this->school(); $other = $this->school(); $c = $this->membership($u, $s, 'parent');
        foreach ([[$s, 'parent', 'Visible'], [$s, 'staff', 'Private staff notice'], [$other, 'all', 'Other school notice']] as [$school, $audience, $title]) {
            DB::table('workspace_posts')->insert(['school_id' => $school, 'author_id' => $u->id, 'kind' => 'notice', 'audience' => $audience, 'title' => $title, 'body' => 'Body', 'created_at' => now()]);
        }
        $this->actingAs($u)->getJson('/api/dashboard?context='.$c)->assertJsonCount(1, 'notices')->assertJsonPath('notices.0.title', 'Visible');
    }

    public function test_staff_roster_permission_never_grants_academic_update_access(): void
    {
        $u = $this->user('Roster'); $s = $this->school(); $c = $this->membership($u, $s, 'staff', ['students.view']); [$student] = $this->student($s);
        DB::table('workspace_updates')->insert(['school_id' => $s, 'student_id' => $student, 'author_id' => $u->id, 'title' => 'Confidential draft', 'body' => 'Private academic data', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u)->getJson('/api/dashboard?context='.$c)->assertOk()->assertJsonCount(0, 'updates');
        $this->get('/dashboard/updates?context='.$c)->assertForbidden();
        $this->getJson('/dashboard/people?context='.$c)->assertForbidden();
        $this->getJson('/dashboard/updates?context='.$c)->assertForbidden();
        $this->get('/dashboard/students?context='.$c)->assertOk()->assertDontSee('Private academic data');
    }

    public function test_console_provisioning_requires_an_identified_platform_operator(): void
    {
        $operator = $this->user('Operator'); $target = $this->user('Connect'); $school = $this->school();
        $operator->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $this->artisan('workspace:connect', ['email' => $target->email, 'role' => 'school_admin', '--school' => $school])->assertExitCode(1);
        $this->artisan('workspace:connect', ['email' => $target->email, 'role' => 'school_admin', '--school' => $school, '--by' => $operator->id])->assertExitCode(0);
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $target->id, 'school_id' => $school, 'role' => 'school_admin', 'approved_by' => $operator->id]);
        $this->assertDatabaseHas('workspace_audit', ['actor_id' => $operator->id, 'action' => 'membership.provisioned.console']);
        $this->artisan('workspace:district', ['name' => 'District', 'market' => 'UK', '--school' => [$school], '--by' => $operator->id])->assertExitCode(0);
        $this->artisan('workspace:district', ['name' => 'Duplicate', 'market' => 'UK', '--school' => [$school], '--by' => $operator->id])->assertExitCode(1);
    }

    public function test_academic_structure_requires_school_admin_and_an_active_school_teacher(): void
    {
        $admin = $this->user('Structure'); $teacher = $this->user('Assigned'); $outside = $this->user('Outside');
        $a = $this->school(); $b = $this->school(); $ac = $this->membership($admin, $a, 'school_admin');
        $tc = $this->membership($teacher, $a, 'teacher'); $this->membership($outside, $b, 'teacher');
        $data = ['context' => $ac, 'school_id' => $a, 'name' => 'Year 5', 'section_name' => 'Birch', 'teacher_id' => $teacher->id];
        $this->actingAs($admin)->postJson('/workspace/classes', $data)->assertOk();
        $this->get('/dashboard/classes?context='.$ac)->assertOk()->assertSee('Year 5')->assertSee('Birch');
        $data['teacher_id'] = $outside->id; $this->postJson('/workspace/classes', $data)->assertUnprocessable();
        $data['context'] = $tc; $data['teacher_id'] = $teacher->id;
        $this->actingAs($teacher)->postJson('/workspace/classes', $data)->assertForbidden();
        $this->get('/dashboard/classes?context='.$tc)->assertForbidden();
        $this->assertDatabaseCount('class_sections', 1);
    }

}
