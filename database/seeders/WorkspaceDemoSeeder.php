<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Explicit, local-only fixtures; never part of DatabaseSeeder or deployment. */
class WorkspaceDemoSeeder extends Seeder
{
    private function delegateDemo(User $user, int $school, User $operator): void
    {
        // Do not restore revoked delegations or change an existing operator's permissions.
        if (!DB::table('workspace_memberships')->where('user_id', $user->id)->where('role', 'sub_super_admin')->exists()) {
            DB::table('workspace_memberships')->insert(['user_id'=>$user->id, 'school_id'=>$school,
                'role'=>'sub_super_admin', 'status'=>'active', 'permissions'=>json_encode(\App\Services\WorkspaceAccess::DELEGATED_PERMISSIONS),
                'approved_by'=>$operator->id, 'created_at'=>now(), 'updated_at'=>now()]);
        }
    }

    public function run()
    {
        if (!app()->environment(['local', 'testing'])) { throw new \RuntimeException('Workspace demos are local/testing only.'); }
        DB::transaction(function () {
            $users = [];
            foreach (['parent' => 'Amelia', 'teacher' => 'Daniel', 'staff' => 'Sofia', 'school_admin' => 'Olivia', 'district_admin' => 'James', 'super_admin' => 'Alex', 'sub_super_admin' => 'Morgan'] as $role => $name) {
                $users[$role] = User::firstOrCreate(['email' => $role.'@workspace.test'], ['first_name' => $name, 'last_name' => 'Demo',
                    'type' => $role, 'password' => Hash::make('Workspace-demo-2026!'), 'status' => 1, 'email_verified_at' => now()]);
            }
            $users['super_admin']->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web'));
            foreach ($users as $u) { DB::table('workspace_profiles')->updateOrInsert(['user_id' => $u->id], ['timezone' => 'Europe/London', 'created_at' => now(), 'updated_at' => now()]); }
            $school = DB::table('schools')->where('school_name', 'PTM Demo · Riverside School')->value('id');
            if ($school) { $this->delegateDemo($users['sub_super_admin'], $school, $users['super_admin']); $this->command?->info('Demo already exists. Existing fixture data and passwords were preserved.'); return; }
            $schools = [];
            foreach (['Riverside School', 'Oakfield Academy'] as $name) {
                $schools[] = DB::table('schools')->insertGetId(['school_name' => 'PTM Demo · '.$name, 'user_id' => $users['school_admin']->id,
                    'country_id' => 1, 'address' => '12 School Road', 'image' => '', 'post_code' => 'SW1A 1AA', 'created_at' => now(), 'updated_at' => now()]);
            }
            $school = $schools[0];
            $this->delegateDemo($users['sub_super_admin'], $school, $users['super_admin']);
            foreach (['parent', 'teacher', 'staff', 'school_admin'] as $role) {
                DB::table('workspace_memberships')->insert(['user_id' => $users[$role]->id, 'school_id' => $school, 'role' => $role,
                    'status' => 'active', 'permissions' => json_encode($role === 'staff' ? ['notices.publish', 'events.manage'] : []),
                    'approved_by' => $users['school_admin']->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $district = DB::table('workspace_districts')->insertGetId(['name' => 'PTM Demo · Westbrook District', 'market' => 'UK', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($schools as $id) { DB::table('workspace_district_schools')->insert(['district_id' => $district, 'school_id' => $id]); }
            DB::table('workspace_memberships')->insert(['user_id' => $users['district_admin']->id, 'district_id' => $district,
                'role' => 'district_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $class = DB::table('classes')->insertGetId(['name' => 'Year 4', 'school_id' => $school]);
            $section = DB::table('sections')->insertGetId(['name' => 'Maple', 'school_id' => $school]);
            $cs = DB::table('class_sections')->insertGetId(['class_id' => $class, 'section_id' => $section, 'class_teacher_id' => $users['teacher']->id]);
            foreach (['Ella', 'Noah', 'Isla'] as $name) {
                $u = User::firstOrCreate(['email' => strtolower($name).'.student@workspace.test'], ['first_name' => $name, 'last_name' => 'Demo', 'type' => 'student', 'status' => 1, 'email_verified_at' => now()]);
                $student = DB::table('students')->insertGetId(['user_id' => $u->id, 'class_section_id' => $cs, 'class_status' => 'ACCEPTED']);
                if ($name === 'Ella') {
                    DB::table('workspace_guardians')->insert(['student_id' => $student, 'guardian_id' => $users['parent']->id,
                        'school_id' => $school, 'status' => 'verified', 'verified_by' => $users['school_admin']->id, 'verified_at' => now(), 'created_at' => now()]);
                }
                $update = DB::table('workspace_updates')->insertGetId(['school_id' => $school, 'student_id' => $student, 'author_id' => $users['teacher']->id,
                    'title' => $name === 'Ella' ? 'A wonderful week of discoveries' : 'This week in the classroom',
                    'body' => $name.' has shown thoughtful curiosity in our reading and science activities. We have been practising explaining our ideas and listening to each other.',
                    'status' => $name === 'Noah' ? 'draft' : 'published', 'version' => 1, 'published_at' => $name === 'Noah' ? null : now(), 'created_at' => now(), 'updated_at' => now()]);
                $row = DB::table('workspace_updates')->find($update);
                DB::table('workspace_update_versions')->insert(['update_id' => $update, 'actor_id' => $users['teacher']->id, 'version' => 1,
                    'title' => $row->title, 'body' => $row->body, 'status' => $row->status, 'created_at' => now()]);
            }
            foreach ([['notice', 'A new chapter, together', 'Welcome to the new term. We look forward to a year of learning, growing and staying connected.'],
                ['notice', 'Reading week is nearly here', 'Bring a favourite book and share a story with your class next week.'],
                ['event', 'Family learning afternoon', 'Join us for classroom activities and a chance to meet your school community.']] as [$kind, $title, $body]) {
                DB::table('workspace_posts')->insert(['school_id' => $school, 'author_id' => $users['school_admin']->id, 'kind' => $kind,
                    'audience' => 'all', 'title' => $title, 'body' => $body, 'starts_at' => $kind === 'event' ? now()->addDays(3) : null, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('workspace_messages')->insert(['school_id' => $school, 'sender_id' => $users['teacher']->id,
                'recipient_id' => $users['parent']->id, 'body' => 'Hello Amelia, Ella had a lovely week. I have shared a short progress update for you.', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('workspace_appointments')->insert(['school_id' => $school, 'organizer_id' => $users['parent']->id,
                'participant_id' => $users['teacher']->id, 'title' => 'Ella’s learning catch-up', 'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addMinutes(30), 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->command?->info('Web demo: {super_admin|sub_super_admin|school_admin|district_admin}@workspace.test. Parent/Teacher/Staff fixtures are mobile-only by default.');
        $this->command?->info('Password: Workspace-demo-2026! (local fixtures only)');
    }
}
