<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Self-signup without approval (Docs 12 and 13): Staff / School Admin / District Admin
 * requests that are still pending from the approval flow become active, decided by the
 * system. Skipped when ROLE_REQUIRE_APPROVAL is on, because pending is then intended.
 */
return new class extends Migration
{
    public function up()
    {
        if (config('ptm.role_require_approval')) {
            return;
        }

        $now = now();
        $pending = DB::table('user_roles')
            ->where('status', 'pending')
            ->where('source', 'request')
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->get();

        foreach ($pending as $context) {
            DB::transaction(function () use ($context, $now) {
                DB::table('user_roles')->where('id', $context->id)->update([
                    'status' => 'active',
                    'decided_at' => $now,
                    'decided_by' => null,
                    'effective_from' => $context->effective_from ?? $now,
                    'updated_at' => $now,
                ]);
                DB::table('audit_logs')->insert([
                    'actor_user_id' => null,
                    'action' => 'context.granted',
                    'subject_type' => 'UserRole',
                    'subject_id' => (string) $context->id,
                    'context_id' => $context->id,
                    'payload' => json_encode([
                        'source' => 'migration_no_approval',
                        'role' => $context->role,
                        'organization_type' => $context->organization_type,
                        'organization_id' => $context->organization_id,
                    ]),
                    'created_at' => $now,
                ]);
            });
        }
    }

    public function down()
    {
        // Data fix only; granted roles are not taken back.
    }
};
