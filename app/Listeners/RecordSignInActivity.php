<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes sign-ins, sign-outs and failed sign-in attempts to the activity log so
 * administrators can review account access. A failed attempt for an unknown
 * email is not stored, so the log cannot be used to collect guessed addresses.
 */
class RecordSignInActivity
{
    public function handle(Login|Logout|Failed $event): void
    {
        try {
            if (!Schema::hasTable('workspace_audit')) { return; }
            $user = $event->user;
            if (!$user) { return; }
            $action = match (true) { $event instanceof Login => 'auth.login', $event instanceof Logout => 'auth.logout', default => 'auth.failed' };
            $ip = request()?->ip();
            DB::table('workspace_audit')->insert(['actor_id' => $user->id, 'school_id' => null, 'action' => $action,
                'subject' => $action === 'auth.failed' ? 'From '.($ip ?? 'unknown address') : (string) $user->id, 'created_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
