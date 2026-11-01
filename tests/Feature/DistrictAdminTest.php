<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Schools;
use App\Models\User;
use App\Services\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Super Admin districts API (/admin/districts) and how its districts reach the District Admin signup.
 */
class DistrictAdminTest extends TestCase
{
    use RefreshDatabase;

    private int $usId;
    private int $ukId;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Super Admin', 'Teacher', 'Parent'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->mock(NotificationSender::class)->shouldIgnoreMissing();
        $this->usId = DB::table('countries')->insertGetId(['code' => 'US', 'name' => 'United States', 'phonecode' => 1]);
        $this->ukId = DB::table('countries')->insertGetId(['code' => 'GB', 'name' => 'United Kingdom', 'phonecode' => 44]);
    }

    private function makeUser(string $type, int $countryId, bool $superAdmin = false): User
    {
        $user = User::forceCreate([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'type' => $type,
            'country_id' => $countryId,
            'email_verified_at' => now(),
            'status' => 1,
        ]);
        if ($superAdmin) {
            $user->assignRole('Super Admin');
        }
        return $user;
    }

    private function makeSchool(int $countryId): Schools
    {
        return Schools::forceCreate([
            'user_id' => 1,
            'country_id' => $countryId,
            'school_name' => 'School ' . Str::random(6),
            'address' => 'x', 'locality' => 'x', 'post_town' => 'x', 'image' => '',
            'post_code' => '00000', 'email' => Str::random(6) . '@school.test', 'phone' => '1', 'website' => '',
        ]);
    }

    private function actAs(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'sanctum');
        return $this;
    }

    public function test_only_super_admin_can_manage_districts(): void
    {
        $districtAdmin = $this->makeUser('district_admin', $this->usId);
        $district = District::create(['name' => 'North', 'country_id' => $this->usId]);

        $this->actAs($districtAdmin)->getJson('/api/admin/districts')->assertStatus(403);
        $this->actAs($districtAdmin)->postJson('/api/admin/districts', ['name' => 'South', 'country_id' => $this->usId])->assertStatus(403);
        $this->actAs($districtAdmin)->putJson("/api/admin/districts/{$district->id}", ['status' => 'inactive'])->assertStatus(403);
        $this->actAs($districtAdmin)->postJson("/api/admin/districts/{$district->id}/schools", ['school_ids' => [1]])->assertStatus(403);
        $this->assertSame(1, District::count());
    }

    public function test_super_admin_creates_district_and_district_admin_can_join_it(): void
    {
        $boss = $this->makeUser('parent', $this->usId, true);

        $id = $this->actAs($boss)->postJson('/api/admin/districts', ['name' => ' Springfield District ', 'country_id' => $this->usId])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Springfield District')
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'district.created', 'subject_id' => (string) $id]);

        // Same name, same country: rejected. Same name in the UK: fine.
        $this->actAs($boss)->postJson('/api/admin/districts', ['name' => 'Springfield District', 'country_id' => $this->usId])
            ->assertStatus(422)->assertJsonPath('message', 'A district with this name already exists in this country.');
        $this->actAs($boss)->postJson('/api/admin/districts', ['name' => 'Springfield District', 'country_id' => $this->ukId])
            ->assertStatus(201);
        $this->actAs($boss)->postJson('/api/admin/districts', ['name' => 'Nowhere', 'country_id' => 99999])->assertStatus(422);

        // The US District Admin signup lists only the US district and can join it.
        $usAdmin = $this->makeUser('district_admin', $this->usId);
        $listed = $this->actAs($usAdmin)->getJson('/api/get/schools?page=1&organization_type=district')
            ->assertOk()->json('data.other_schools.data');
        $this->assertSame([$id], array_column($listed, 'id'));
        $this->actAs($usAdmin)->postJson('/api/auth/role-requests', [
            'organization_id' => $id, 'organization_type' => 'district', 'role' => 'district_admin',
        ])->assertOk();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $usAdmin->id, 'role' => 'district_admin', 'organization_type' => 'district', 'organization_id' => $id,
        ]);
    }

    public function test_inactive_district_drops_out_of_the_app_list(): void
    {
        $boss = $this->makeUser('parent', $this->usId, true);
        $district = District::create(['name' => 'North', 'country_id' => $this->usId]);

        $this->actAs($boss)->putJson("/api/admin/districts/{$district->id}", ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $usAdmin = $this->makeUser('district_admin', $this->usId);
        $this->actAs($usAdmin)->getJson('/api/get/schools?page=1&organization_type=district')
            ->assertOk()->assertJsonPath('data.other_schools.total', 0);
        $this->actAs($boss)->getJson('/api/admin/districts?status=inactive')
            ->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_assign_schools_to_district_in_same_country_only(): void
    {
        $boss = $this->makeUser('parent', $this->usId, true);
        $district = District::create(['name' => 'North', 'country_id' => $this->usId]);
        $usSchool = $this->makeSchool($this->usId);
        $ukSchool = $this->makeSchool($this->ukId);

        $this->actAs($boss)->postJson("/api/admin/districts/{$district->id}/schools", ['school_ids' => [$usSchool->id, $ukSchool->id]])
            ->assertStatus(422);
        $this->assertNull($usSchool->fresh()->district_id);

        $this->actAs($boss)->postJson("/api/admin/districts/{$district->id}/schools", ['school_ids' => [$usSchool->id]])
            ->assertOk()->assertJsonPath('data.schools_count', 1);
        $this->assertSame($district->id, (int) $usSchool->fresh()->district_id);

        $this->actAs($boss)->postJson('/api/admin/districts/99999/schools', ['school_ids' => [$usSchool->id]])->assertStatus(404);
    }
}
