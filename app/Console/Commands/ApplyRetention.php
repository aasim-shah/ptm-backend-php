<?php

namespace App\Console\Commands;

use App\Services\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Ends idle device sessions and removes activity log entries past the retention period. */
class ApplyRetention extends Command
{
    protected $signature = 'workspace:apply-retention';
    protected $description = 'Revoke expired device sessions and apply the activity log retention period';

    public function handle(PlatformSettings $settings): int
    {
        $idle = now()->subMinutes((int) config('session.lifetime', 120));
        $sessions = DB::table('account_sessions')->whereNull('revoked_at')->where('last_seen_at', '<', $idle)->update(['revoked_at' => now(), 'updated_at' => now()]);
        $days = max(90, (int) $settings->get('retention.audit_days'));
        $audit = DB::table('workspace_audit')->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Ended {$sessions} idle sessions; removed {$audit} activity entries older than {$days} days.");
        return self::SUCCESS;
    }
}
