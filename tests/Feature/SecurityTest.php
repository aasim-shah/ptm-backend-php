<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Super Admin', 'Principal', 'Teacher', 'Parent', 'Student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $type, ?string $role = null, string $email = null): User
    {
        $user = User::forceCreate([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => $email ?? $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'type' => $type,
            'email_verified_at' => now(),
        ]);
        if ($role) {
            $user->assignRole($role);
        }
        return $user;
    }

    public function test_register_rejects_privileged_types(): void
    {
        foreach (['Super Admin', 'admin', 'Principal', 'student'] as $type) {
            $response = $this->postJson('/api/auth/register', [
                'first_name' => 'Eve',
                'last_name' => 'Attacker',
                'email' => 'eve' . md5($type) . '@example.com',
                'password' => 'secret123',
                'fcm_id' => 'x',
                'type' => $type,
            ]);

            $this->assertNotEquals(200, $response->status(), "type=$type must be rejected");
            $this->assertDatabaseMissing('users', ['email' => 'eve' . md5($type) . '@example.com']);
        }
    }

    public function test_default_login_and_register_routes_are_gone(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'x'])->assertStatus(405);
        $this->post('/register', [])->assertNotFound();
    }

    public function test_admin_panel_requires_admin_role_not_type_column(): void
    {
        $this->makeUser('admin', null, 'fake-admin@example.com');

        $this->post('/login-web', ['email' => 'fake-admin@example.com', 'password' => 'secret123'])
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_super_admin_can_open_dashboard(): void
    {
        $this->makeUser('admin', 'Super Admin', 'boss@example.com');

        $this->post('/login-web', ['email' => 'boss@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
        $this->get('/home')->assertOk();
    }

    public function test_parent_token_cannot_call_teacher_api(): void
    {
        $parent = $this->makeUser('parent', 'Parent');
        $this->actingAs($parent, 'sanctum')
            ->getJson('/api/teacher/classes/fetch')
            ->assertForbidden();
    }

    public function test_teacher_token_can_call_teacher_api(): void
    {
        $teacher = $this->makeUser('teacher', 'Teacher');
        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/teacher/classes/fetch')
            ->assertOk();
    }

    public function test_student_attendance_requires_authentication_and_access(): void
    {
        $this->getJson('/api/student-attendance?student_id=1')->assertUnauthorized();

        $stranger = $this->makeUser('parent', 'Parent');
        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/student-attendance?student_id=999999')
            ->assertForbidden();
    }

    public function test_debug_routes_are_removed(): void
    {
        $this->postJson('/api/test-notification', ['email' => 'x@example.com'])->assertNotFound();
        $this->get('/clear')->assertNotFound();
    }

    public function test_paypal_admin_routes_require_super_admin(): void
    {
        $this->get('/plan/create')->assertRedirect(route('login'));

        $parent = $this->makeUser('parent', 'Parent');
        $this->actingAs($parent)->get('/plan/create')->assertForbidden();
    }

    public function test_webhooks_reject_unsigned_requests(): void
    {
        $this->postJson('/webhook/agora', ['eventType' => 104, 'payload' => []])->assertStatus(401);
        $this->postJson('/webhook/paypal/subscription-activated', ['event_type' => 'BILLING.SUBSCRIPTION.CANCELLED'])
            ->assertStatus(401);
    }
}
