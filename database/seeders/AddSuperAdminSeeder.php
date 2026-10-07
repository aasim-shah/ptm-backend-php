<?php

namespace Database\Seeders;

use App\Models\SessionYear;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AddSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //Add Super Admin User
        $super_admin_role = Role::where('name', 'Super Admin')->first();
        // Never overwrite an existing admin's credentials. Set SUPER_ADMIN_EMAIL /
        // SUPER_ADMIN_PASSWORD before seeding; otherwise a random password is printed once.
        $user = User::find(1);
        if (!$user) {
            $email = env('SUPER_ADMIN_EMAIL', 'superadmin@gmail.com');
            $password = env('SUPER_ADMIN_PASSWORD') ?: \Illuminate\Support\Str::random(20);
            $user = User::create([
                'id' => 1,
                'first_name' => 'super',
                'last_name' => 'admin',
                'type' => 'admin',
                'email' => $email,
                'password' => Hash::make($password),
                'gender' => 'Male',
                'image' => 'logo.svg',
                'mobile' => ""
            ]);
            if (!env('SUPER_ADMIN_PASSWORD') && $this->command) {
                $this->command->warn("Super Admin created: {$email} / {$password} (change it after first login)");
            }
        }
        $user->assignRole([$super_admin_role->id]);
        // Everything except teacher-scoped permissions (see 2026_10_07_000000_grant_admin_panel_roles).
        $super_admin_role->syncPermissions(\Spatie\Permission\Models\Permission::whereNotIn('name', [
            'class-teacher', 'manage-online-exam', 'exam-result', 'exam-upload-marks',
            'assignment-list', 'assignment-create', 'assignment-edit', 'assignment-delete',
            'attendance-create', 'attendance-edit', 'attendance-delete',
            'lesson-list', 'lesson-create', 'lesson-edit', 'lesson-delete',
            'topic-list', 'topic-create', 'topic-edit', 'topic-delete',
        ])->get());

        SessionYear::updateOrCreate(['id' => 1],[
            'name' => '2022-23',
            'default' => 1,
            'start_date' => '2022-06-01',
            'end_date' => '2023-04-30',
        ]);

        // add session year in setting table
        $session_year = new Settings();
        $session_year->type = 'session_year';
        $session_year->message = 1;
        $session_year->save();
    }
}
