<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Server-side bootstrap; never exposed to public signup or school staff. */
class ConnectWorkspace extends Command
{
    protected $signature = 'workspace:connect {email} {role : parent|teacher|staff|school_admin|district_admin|sub_super_admin}
        {--school= : Existing school ID} {--district= : Existing district ID}
        {--permission=* : Approved delegated capabilities} {--by= : Active Super Admin user ID for the audit trail}';
    protected $description = 'Connect an existing verified user to an authorized school or district workspace';

    public function handle(): int
    {
        $actor = User::where('id', $this->option('by'))->where('status', 1)->whereNotNull('email_verified_at')->first();
        if (!$actor || !$actor->hasRole('Super Admin')) { $this->error('Provide --by with the active platform operator’s user ID.'); return 1; }
        $user = User::where('email', $this->argument('email'))->where('status', 1)->whereNotNull('email_verified_at')->first();
        $role = $this->argument('role');
        if (!$user || ($role === 'super_admin' || !isset(WorkspaceAccess::LABELS[$role]))) { $this->error('Provide an active verified account and a supported role.'); return 1; }
        $district = $role === 'district_admin' || ($role === 'sub_super_admin' && $this->option('district'));
        $id = $this->option($district ? 'district' : 'school');
        if (!$id || $this->option($district ? 'school' : 'district') || !DB::table($district ? 'workspace_districts' : 'schools')->where('id', $id)->exists()) {
            $this->error('Provide exactly one existing school or district appropriate to the role.'); return 1;
        }
        $permissions = $this->option('permission');
        if (array_diff($permissions, $role === 'sub_super_admin' ? WorkspaceAccess::DELEGATED_PERMISSIONS : WorkspaceAccess::STAFF_PERMISSIONS) || (!in_array($role, ['staff', 'sub_super_admin']) && $permissions)) {
            $this->error('Only staff or Sub-Super Admins can receive their supported delegated permissions.'); return 1;
        }
        DB::transaction(function () use ($actor, $user, $role, $district, $id, $permissions) {
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'role' => $role,
                'school_id' => $district ? null : $id, 'district_id' => $district ? $id : null],
                ['status' => 'active', 'approved_by' => $actor->id, 'permissions' => json_encode($permissions), 'starts_at' => now(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            app(WorkspaceAccess::class)->audit($actor, $district ? null : (int) $id, 'membership.provisioned.console', $user->id.':'.$role);
        });
        $this->info('Workspace connected. No password or global role was changed.'); return 0;
    }
}
