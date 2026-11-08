<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class TrialSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_limits_have_defaults_when_the_environment_omits_them(): void
    {
        $keys = ['TRAIL', 'TRAIL_DAYS', 'TRAIL_CALL_COUNT', 'PREMIUM_CALL_COUNT'];
        $saved = [];
        foreach ($keys as $key) { $saved[$key] = Env::get($key); Env::getRepository()->clear($key); }
        try {
            $config = require config_path('environment.php');
        } finally {
            foreach ($saved as $key => $value) { if ($value !== null) { Env::getRepository()->set($key, (string) $value); } }
        }
        $this->assertSame([60, 5, 5, 100], [$config['TRAIL'], $config['TRAIL_DAYS'], $config['TRAIL_CALL_COUNT'], $config['PREMIUM_CALL_COUNT']]);
    }

    public function test_registration_creates_an_active_trial(): void
    {
        foreach (['Parent', 'Teacher', 'Staff'] as $role) { \Spatie\Permission\Models\Role::findOrCreate($role, 'web'); }
        $this->postJson('/api/auth/register', [
            'first_name' => 'Tia', 'last_name' => 'Trial', 'email' => 'tia@example.com',
            'password' => 'secret123', 'fcm_id' => 'x', 'type' => 'parent',
        ])->assertOk();
        $user = User::where('email', 'tia@example.com')->firstOrFail();
        $this->assertDatabaseHas('user_subscription', ['user_id' => $user->id, 'status' => 'ACTIVE', 'type' => 'TRAIL',
            'call_count' => config('environment.TRAIL_CALL_COUNT')]);
    }
}
