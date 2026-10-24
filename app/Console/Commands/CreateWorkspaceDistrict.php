<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateWorkspaceDistrict extends Command
{
    protected $signature = 'workspace:district {name} {market : US|UK} {--school=* : Existing school IDs} {--by= : Active Super Admin user ID for the audit trail}';
    protected $description = 'Create a district and link existing schools without reassigning another district’s schools';

    public function handle(): int
    {
        $actor = \App\Models\User::where('id', $this->option('by'))->where('status', 1)->whereNotNull('email_verified_at')->first();
        if (!$actor || !$actor->hasRole('Super Admin')) { $this->error('Provide --by with the active platform operator’s user ID.'); return 1; }
        $ids = array_unique($this->option('school'));
        if (!array_key_exists($this->argument('market'), config('workspaces.markets')) || !$ids || DB::table('schools')->whereIn('id', $ids)->count() !== count($ids)) {
            $this->error('Provide US or UK and at least one existing school.'); return 1;
        }
        $id = DB::transaction(function () use ($actor, $ids) {
            DB::table('schools')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if (DB::table('workspace_district_schools')->whereIn('school_id', $ids)->exists()) { return null; }
            $id = DB::table('workspace_districts')->insertGetId(['name' => $this->argument('name'), 'market' => $this->argument('market'), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($ids as $schoolId) { DB::table('workspace_district_schools')->insert(['district_id' => $id, 'school_id' => $schoolId]); }
            app(\App\Services\WorkspaceAccess::class)->audit($actor, null, 'district.created.console', $id);
            return $id;
        });
        if (!$id) { $this->error('A school already belongs to a district. No changes made.'); return 1; }
        $this->info('District created: '.$id); return 0;
    }
}
