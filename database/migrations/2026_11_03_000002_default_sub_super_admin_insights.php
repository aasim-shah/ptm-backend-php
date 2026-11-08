<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Gives existing Sub-Super Admins Analytics & Insights and Reports & Exports, the agreed default for every panel. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workspace_memberships')->where('role', 'sub_super_admin')->where('status', 'active')->orderBy('id')->each(function ($row) {
            $permissions = json_decode($row->permissions ?? '[]', true);
            $permissions = is_array($permissions) ? $permissions : [];
            $merged = array_values(array_unique(array_merge($permissions, ['analytics.view', 'reports.export'])));
            if ($merged !== $permissions) {
                DB::table('workspace_memberships')->where('id', $row->id)->update(['permissions' => json_encode($merged), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Permissions granted here can be removed per delegate in the Super Admin panel.
    }
};
