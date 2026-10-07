<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin panel access is now decided by Spatie roles (Super Admin, Principal)
 * instead of the user-editable users.type column.
 *
 * - Super Admin gets every permission except the teacher-scoped ones (those pages
 *   assume the logged-in user is a class teacher and crash otherwise).
 * - Users linked to a school as principal get the Principal role if missing.
 */
return new class extends Migration
{
    public const TEACHER_ONLY = [
        'class-teacher', 'manage-online-exam', 'exam-result', 'exam-upload-marks',
        'assignment-list', 'assignment-create', 'assignment-edit', 'assignment-delete',
        'attendance-create', 'attendance-edit', 'attendance-delete',
        'lesson-list', 'lesson-create', 'lesson-edit', 'lesson-delete',
        'topic-list', 'topic-create', 'topic-edit', 'topic-delete',
    ];

    public function up()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(
                Permission::where('guard_name', 'web')->whereNotIn('name', self::TEACHER_ONLY)->get()
            );
        }

        $principal = Role::where('name', 'Principal')->where('guard_name', 'web')->first();
        if ($principal) {
            $principalIds = DB::table('schools')->whereNotNull('principal_id')->pluck('principal_id')->unique();
            foreach ($principalIds as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user && !$user->hasRole('Principal')) {
                    $user->assignRole($principal);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down()
    {
        // Permission grants are not reverted automatically.
    }
};
