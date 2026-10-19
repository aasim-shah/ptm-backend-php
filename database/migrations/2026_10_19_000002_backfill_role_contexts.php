<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration for roles going live (backend update, Part A8).
 *
 * - Every parent/teacher gets one active context per linked school (user_schools).
 * - Principals (schools.principal_id) get an active school_admin context for that school.
 *   This mapping is the default from the spec; confirm it with the product owner.
 * - Parents/teachers without a school get no context, so has_school stays false.
 *
 * Re-running is safe: rows that already exist are skipped (insertOrIgnore on the unique key).
 */
return new class extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('user_schools')
            ->join('users', 'users.id', '=', 'user_schools.user_id')
            ->whereNull('users.deleted_at')
            ->whereIn(DB::raw('LOWER(users.type)'), ['parent', 'teacher'])
            ->select('user_schools.user_id', 'user_schools.school_id', 'users.type')
            ->orderBy('user_schools.user_id')
            ->chunk(500, function ($links) use ($now) {
                DB::table('user_roles')->insertOrIgnore($links->map(fn ($link) => [
                    'user_id' => $link->user_id,
                    'role' => strtolower($link->type),
                    'organization_type' => 'school',
                    'organization_id' => $link->school_id,
                    'status' => 'active',
                    'source' => 'legacy_link',
                    'requested_at' => $now,
                    'decided_at' => $now,
                    'effective_from' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        $principals = DB::table('schools')
            ->join('users', 'users.id', '=', 'schools.principal_id')
            ->whereNull('users.deleted_at')
            ->select('schools.id as school_id', 'schools.principal_id')
            ->get();
        DB::table('user_roles')->insertOrIgnore($principals->map(fn ($row) => [
            'user_id' => $row->principal_id,
            'role' => 'school_admin',
            'organization_type' => 'school',
            'organization_id' => $row->school_id,
            'status' => 'active',
            'source' => 'legacy_link',
            'requested_at' => $now,
            'decided_at' => $now,
            'effective_from' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down()
    {
        DB::table('user_roles')->where('source', 'legacy_link')->delete();
    }
};
