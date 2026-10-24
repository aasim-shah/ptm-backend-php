<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Schools;
use App\Models\User;
use App\Models\UserCallRoom;
use App\Models\UserRole;
use App\Models\UserSchools;
use App\Models\UserSubscription;
use App\Services\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Roles & organizations update: the A9 acceptance checks and the Part B behaviour.
 */
class RoleContextTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Super Admin', 'Principal', 'Teacher', 'Parent', 'Student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        // No real push notifications from tests.
        $this->mock(NotificationSender::class)->shouldIgnoreMissing();
        $this->countryId = DB::table('countries')->insertGetId(['code' => 'US', 'name' => 'United States', 'phonecode' => 1]);
    }

    // ---------------------------------------------------------------- helpers

    private function makeUser(string $type, array $attributes = []): User
    {
        $user = User::forceCreate(array_merge([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'type' => $type,
            'country_id' => $this->countryId,
            'email_verified_at' => now(),
            'status' => 1,
        ], $attributes));
        if (in_array($type, ['parent', 'teacher'], true)) {
            $user->assignRole(ucfirst($type));
        }
        return $user;
    }

    private function makeSchool(array $attributes = []): Schools
    {
        return Schools::forceCreate(array_merge([
            'user_id' => 1,
            'country_id' => $this->countryId,
            'school_name' => 'School ' . Str::random(6),
            'address' => 'x', 'locality' => 'x', 'post_town' => 'x', 'image' => '',
            'post_code' => '00000', 'email' => Str::random(6) . '@school.test', 'phone' => '1', 'website' => '',
        ], $attributes));
    }

    private function context(User $user, string $role, $organization, string $status = 'active'): UserRole
    {
        return UserRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'organization_type' => $organization instanceof District ? 'district' : 'school',
            'organization_id' => $organization->id,
            'status' => $status,
            'source' => 'request',
            'requested_at' => now(),
        ]);
    }

    private function actAs(User $user, ?UserRole $context = null): self
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'sanctum');
        return $context ? $this->withHeader('X-PTM-Context', (string) $context->id) : $this->withHeaders(['X-PTM-Context' => '']);
    }

    // ---------------------------------------------------------------- A4 / A8

    public function test_legacy_teacher_gets_an_active_context_and_same_access(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser('teacher');
        UserSchools::insert(['id' => (string) Str::uuid(), 'user_id' => $teacher->id, 'school_id' => $school->id]);

        $response = $this->actAs($teacher)->getJson('/api/auth/details')->assertOk();

        $response->assertJsonPath('has_school', true)
            ->assertJsonPath('contexts.0.role', 'teacher')
            ->assertJsonPath('contexts.0.status', 'active')
            ->assertJsonPath('contexts.0.organization.id', $school->id);
        $this->assertContains('classes.manage', $response->json('contexts.0.permissions'));
        $this->assertSame($response->json('contexts.0.id'), $response->json('active_context_id'));
    }

    public function test_new_parent_without_school_gets_empty_contexts(): void
    {
        $parent = $this->makeUser('parent');

        $this->actAs($parent)->getJson('/api/auth/details')->assertOk()
            ->assertJsonPath('has_school', false)
            ->assertJsonPath('contexts', [])
            ->assertJsonPath('active_context_id', null);
    }

    // ---------------------------------------------------------------- A5.1 / A9 #2

    public function test_register_staff_creates_no_context(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Sam', 'last_name' => 'Staff', 'email' => 'sam@example.com',
            'password' => 'secret123', 'fcm_id' => 'x', 'type' => 'staff', 'title' => 'Office',
        ])->assertOk();

        $response->assertJsonPath('contexts', [])->assertJsonPath('has_school', false);
        $user = User::where('email', 'sam@example.com')->first();
        $this->assertSame('staff', $user->type);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id]);
    }

    public function test_staff_request_is_pending_idempotent_and_grants_nothing(): void
    {
        $school = $this->makeSchool();
        $staff = $this->makeUser('staff');
        $body = ['organization_id' => $school->id, 'organization_type' => 'school', 'role' => 'staff'];

        $this->actAs($staff)->postJson('/api/auth/role-requests', $body)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->actAs($staff)->postJson('/api/auth/role-requests', $body)->assertOk();
        $this->assertSame(1, UserRole::where('user_id', $staff->id)->count());

        $this->actAs($staff)->getJson('/api/auth/details')->assertOk()
            ->assertJsonPath('has_school', true)
            ->assertJsonPath('contexts.0.status', 'pending')
            ->assertJsonPath('contexts.0.permissions', [])
            ->assertJsonPath('active_context_id', null);

        // Pending staff can't reach anything role-gated, with or without the header.
        $this->actAs($staff)->getJson('/api/get/meetings-v2')->assertForbidden();
        $this->actAs($staff)->getJson('/api/teacher/classes/fetch')->assertForbidden();
    }

    public function test_role_request_validation(): void
    {
        $school = $this->makeSchool();
        $district = District::create(['name' => 'North', 'country_id' => $this->countryId]);
        $staff = $this->makeUser('staff');

        $this->actAs($staff)->postJson('/api/auth/role-requests', ['organization_id' => $school->id, 'organization_type' => 'school', 'role' => 'teacher'])
            ->assertStatus(422)->assertJsonPath('error', true);
        $this->actAs($staff)->postJson('/api/auth/role-requests', ['organization_id' => $district->id, 'organization_type' => 'district', 'role' => 'staff'])
            ->assertStatus(422);
        $this->actAs($staff)->postJson('/api/auth/role-requests', ['organization_id' => $school->id, 'organization_type' => 'school', 'role' => 'district_admin'])
            ->assertStatus(422);
        $other = DB::table('countries')->insertGetId(['code' => 'GB', 'name' => 'United Kingdom', 'phonecode' => 44]);
        $foreign = $this->makeSchool(['country_id' => $other]);
        $this->actAs($staff)->postJson('/api/auth/role-requests', ['organization_id' => $foreign->id, 'organization_type' => 'school', 'role' => 'staff'])
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------- A7 / A9 #3, #8

    public function test_school_admin_approves_then_suspends_staff(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser('school_admin');
        $this->context($admin, 'school_admin', $school);
        $staff = $this->makeUser('staff');
        $request = $this->context($staff, 'staff', $school, 'pending');

        $this->actAs($admin)->getJson('/api/admin/role-requests')->assertOk()->assertJsonPath('data.data.0.id', $request->id);
        $this->actAs($admin)->postJson("/api/admin/role-requests/{$request->id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'active');

        $details = $this->actAs($staff)->getJson('/api/auth/details')->assertOk();
        $details->assertJsonPath('contexts.0.status', 'active')->assertJsonPath('active_context_id', $request->id);
        $this->assertContains('messages.use', $details->json('contexts.0.permissions'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'context.active', 'context_id' => $request->id]);

        $this->actAs($staff, $request)->getJson('/api/get/meetings-v2')->assertOk();

        $this->actAs($admin)->postJson("/api/admin/role-requests/{$request->id}/suspend")->assertOk();
        $this->actAs($staff, $request)->getJson('/api/get/meetings-v2')->assertForbidden();
        // /auth/details still answers so the app can show the new status.
        $this->actAs($staff, $request)->getJson('/api/auth/details')->assertOk()
            ->assertJsonPath('contexts.0.status', 'suspended')
            ->assertJsonPath('contexts.0.permissions', []);
    }

    public function test_admin_of_another_school_cannot_decide(): void
    {
        $admin = $this->makeUser('school_admin');
        $this->context($admin, 'school_admin', $this->makeSchool());
        $request = $this->context($this->makeUser('staff'), 'staff', $this->makeSchool(), 'pending');

        $this->actAs($admin)->postJson("/api/admin/role-requests/{$request->id}/approve")->assertForbidden();
        $this->actAs($admin)->getJson('/api/admin/role-requests')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_ended_context_is_hidden(): void
    {
        $staff = $this->makeUser('staff');
        $context = $this->context($staff, 'staff', $this->makeSchool());
        $context->update(['effective_to' => now()->subMinute()]);

        $this->actAs($staff)->getJson('/api/auth/details')->assertOk()->assertJsonPath('contexts', []);
        $this->actAs($staff, $context)->getJson('/api/get/meetings-v2')->assertForbidden();
    }

    // ---------------------------------------------------------------- A5.6 / A9 #5, #6, #7

    public function test_header_with_another_users_context_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $mine = $this->makeUser('parent');
        $this->context($mine, 'parent', $school);
        $theirs = $this->context($this->makeUser('parent'), 'parent', $school);

        $this->actAs($mine, $theirs)->getJson('/api/get/meetings-v2')->assertForbidden();
    }

    public function test_header_with_pending_context_is_forbidden(): void
    {
        $staff = $this->makeUser('staff');
        $pending = $this->context($staff, 'staff', $this->makeSchool(), 'pending');

        $this->actAs($staff, $pending)->getJson('/api/get/meetings-v2')->assertForbidden();
    }

    public function test_staff_without_schedule_permission_cannot_create_meetings(): void
    {
        $school = $this->makeSchool();
        $staff = $this->makeUser('staff');
        $context = $this->context($staff, 'staff', $school);
        DB::table('user_role_permissions')->insert(['user_role_id' => $context->id, 'permission_key' => 'schedule.use', 'granted' => false]);

        $this->actAs($staff, $context)->postJson('/api/add/meetings-v2', [
            'title' => 'x', 'meeting_date' => now()->addDay()->toDateString(),
            'meeting_time' => '10:00', 'meeting_end_time' => '11:00', 'student_parent_ids' => [],
        ])->assertForbidden();
    }

    public function test_staff_cannot_use_teacher_only_actions(): void
    {
        $staff = $this->makeUser('staff');
        $context = $this->context($staff, 'staff', $this->makeSchool());

        $this->actAs($staff, $context)->postJson('/api/teacher/class/create', ['name' => 'X'])->assertForbidden();
        $this->actAs($staff, $context)->postJson('/api/teacher/mark-attendance', [])->assertForbidden();
        // Picker endpoints answer with an empty list instead of an error.
        $this->actAs($staff, $context)->getJson('/api/teacher/classes/fetch')->assertOk()->assertJsonPath('data', []);
    }

    // ---------------------------------------------------------------- A5.5 / A9 #4

    public function test_parent_who_is_also_teacher_switches_roles(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser('parent');
        $parent = $this->context($user, 'parent', $school);
        $teacher = $this->context($user, 'teacher', $school);
        $pending = $this->context($user, 'staff', $this->makeSchool(), 'pending');

        $this->actAs($user, $parent)->getJson('/api/teacher/classes/fetch')->assertForbidden();

        $this->actAs($user, $parent)->postJson('/api/auth/context/switch', ['context_id' => (string) $teacher->id])->assertOk()
            ->assertJsonPath('active_context_id', $teacher->id)
            ->assertJsonCount(3, 'contexts');
        $this->assertSame($teacher->id, (int) $user->fresh()->last_context_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'context.switched', 'context_id' => $teacher->id]);

        $this->actAs($user, $teacher)->getJson('/api/teacher/classes/fetch')->assertOk();
        $this->actAs($user, $teacher)->getJson('/api/parent/children-list')->assertForbidden();

        // Without a header the last switched-to context is used.
        $this->actAs($user)->getJson('/api/auth/details')->assertJsonPath('active_context_id', $teacher->id);

        $this->actAs($user, $teacher)->postJson('/api/auth/context/switch', ['context_id' => $pending->id])->assertForbidden();
        $other = $this->context($this->makeUser('parent'), 'parent', $school);
        $this->actAs($user, $teacher)->postJson('/api/auth/context/switch', ['context_id' => $other->id])->assertForbidden();
    }

    // ---------------------------------------------------------------- A5.2 / A9 #9, B1

    public function test_district_admin_lists_districts_and_requests_access(): void
    {
        $district = District::create(['name' => 'North District', 'country_id' => $this->countryId]);
        $admin = $this->makeUser('district_admin');

        $this->actAs($admin)->getJson('/api/get/schools?organization_type=district')->assertOk()
            ->assertJsonPath('data.other_schools.data.0.school_name', 'North District')
            ->assertJsonPath('data.other_schools.data.0.country.code', 'US');

        $this->actAs($admin)->postJson('/api/auth/role-requests', [
            'organization_id' => $district->id, 'organization_type' => 'district', 'role' => 'district_admin',
        ])->assertOk();

        $this->actAs($admin)->getJson('/api/get/schools?organization_type=district')
            ->assertJsonPath('data.selected_schools.0.id', $district->id)
            ->assertJsonPath('data.other_schools.total', 0);
    }

    public function test_get_schools_returns_selected_and_other(): void
    {
        $linked = $this->makeSchool(['school_name' => 'Alpha']);
        $this->makeSchool(['school_name' => 'Beta']);
        $parent = $this->makeUser('parent');
        UserSchools::insert(['id' => (string) Str::uuid(), 'user_id' => $parent->id, 'school_id' => $linked->id]);

        $this->actAs($parent)->getJson('/api/get/schools')->assertOk()
            ->assertJsonPath('data.selected_schools.0.school_name', 'Alpha')
            ->assertJsonPath('data.other_schools.data.0.school_name', 'Beta')
            ->assertJsonPath('data.other_schools.current_page', 1);
    }

    // ---------------------------------------------------------------- A5.3

    public function test_user_schools_creates_context_and_keeps_teacher_error(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser('teacher');

        $this->actAs($teacher)->postJson('/api/user/schools', ['school_ids' => [$school->id]])->assertOk();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $teacher->id, 'role' => 'teacher', 'organization_id' => $school->id, 'status' => 'active',
        ]);

        $this->actAs($teacher)->postJson('/api/user/schools', ['school_ids' => [$this->makeSchool()->id]])
            ->assertStatus(400)->assertJsonPath('message', 'You have already connected a school');
    }

    // ---------------------------------------------------------------- B1: call minutes

    public function test_update_remaining_time_is_capped_by_server_records(): void
    {
        $parent = $this->makeUser('parent');
        UserSubscription::forceCreate([
            'id' => (string) Str::uuid(), 'user_id' => $parent->id, 'email' => $parent->email, 'plan_id' => 'trial',
            'status' => 'ACTIVE', 'remaining_time' => 1000, 'call_count' => 5,
        ]);
        UserCallRoom::forceCreate([
            'id' => (string) Str::uuid(), 'user_id' => $parent->id, 'chanel_name' => 'room', 'status' => 'LEFT',
            'joined_at' => now()->subSeconds(120), 'left_at' => now()->subSeconds(60),
        ]);

        $this->actAs($parent)->postJson('/api/update-remaining-time', ['used_time' => 500])->assertOk()
            ->assertJsonPath('charged_seconds', 60)
            ->assertJsonStructure(['status', 'message', 'user', 'contexts', 'active_context_id', 'has_school']);
        $this->assertEquals(940, UserSubscription::where('user_id', $parent->id)->value('remaining_time'));

        // The same call can't be billed twice.
        $this->actAs($parent)->postJson('/api/update-remaining-time', ['used_time' => 500])->assertJsonPath('charged_seconds', 0);
        $this->assertSame(1, DB::table('call_usage_ledger')->where('user_id', $parent->id)->count());
    }

    // ---------------------------------------------------------------- A8 backfill

    public function test_backfill_migration_maps_existing_links(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser('parent');
        $teacher = $this->makeUser('teacher');
        $loner = $this->makeUser('parent');
        $principal = $this->makeUser('Principal');
        DB::table('principal')->insert(['id' => (string) Str::uuid(), 'user_id' => $principal->id]);
        $school->forceFill(['principal_id' => $principal->id])->save();
        foreach ([$parent, $teacher] as $user) {
            UserSchools::insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'school_id' => $school->id]);
        }

        $migration = require base_path('database/migrations/2026_10_19_000002_backfill_role_contexts.php');
        $migration->up();
        $migration->up(); // re-running is safe

        $this->assertDatabaseHas('user_roles', ['user_id' => $parent->id, 'role' => 'parent', 'organization_id' => $school->id, 'status' => 'active']);
        $this->assertDatabaseHas('user_roles', ['user_id' => $teacher->id, 'role' => 'teacher', 'status' => 'active']);
        $this->assertDatabaseHas('user_roles', ['user_id' => $principal->id, 'role' => 'school_admin', 'organization_id' => $school->id]);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $loner->id]);
        $this->assertSame(3, UserRole::count());
    }

    // ---------------------------------------------------------------- A7 admin panel page

    public function test_super_admin_uses_role_requests_page(): void
    {
        $boss = $this->makeUser('admin');
        $boss->assignRole('Super Admin');
        $school = $this->makeSchool(['school_name' => 'Oak Primary']);
        $staff = $this->makeUser('staff', ['first_name' => 'Pat']);
        $request = $this->context($staff, 'staff', $school, 'pending');

        $this->actingAs($boss)->get('/role-requests')->assertOk()->assertSee('Pat')->assertSee('Oak Primary');
        $this->actingAs($boss)->post("/role-requests/{$request->id}/approve")->assertRedirect();
        $this->assertSame('active', $request->fresh()->status);

        $this->actingAs($boss)->post("/role-requests/{$request->id}/permissions", [
            'permissions' => ['messages.use' => '1', 'students.view_assigned' => '1'],
        ])->assertRedirect();
        $this->assertEqualsCanonicalizing(['messages.use', 'students.view_assigned'],
            app(\App\Services\RoleContextService::class)->effectivePermissions($request->fresh()));
    }

    // ---------------------------------------------------------------- B3 quirks

    public function test_null_strings_in_query_are_ignored(): void
    {
        $parent = $this->makeUser('parent');
        $this->actAs($parent)->getJson('/api/get/schools?page=null&name=null')->assertOk()
            ->assertJsonPath('data.other_schools.current_page', 1);
    }
}
