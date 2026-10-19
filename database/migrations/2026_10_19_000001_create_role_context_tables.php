<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles & organizations (backend update, Part A2).
 *
 * One account can hold several role contexts: user -> user_roles -> organization -> permissions.
 *
 * Organizations are polymorphic instead of a single `organizations` table:
 *   - organization_type = 'school'   -> organization_id is schools.id (the id the app already sees)
 *   - organization_type = 'district' -> organization_id is districts.id
 * Schools point at their district through schools.district_id (NULL = standalone school).
 *
 * Permission tables are prefixed `context_` because Spatie already owns `permissions`
 * and `role_has_permissions` for the admin panel.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'children.manage' => 'My Kids card; loads parent data',
        'reports.view_children' => 'Reports tab (parent view)',
        'classes.manage' => 'My Classes card; create and manage classes and subjects',
        'students.view_assigned' => 'See students of assigned classes',
        'reports.manage' => 'Reports tab (teacher view); create and edit report cards',
        'attendance.manage' => 'Mark and edit attendance',
        'messages.use' => 'Chat tab',
        'schedule.use' => 'Calendar tab and meeting scheduling',
        'video.use' => 'Video calls',
        'events.view' => 'News & Events card',
    ];

    public const ROLE_DEFAULTS = [
        'parent' => ['children.manage', 'reports.view_children', 'messages.use', 'schedule.use', 'video.use', 'events.view'],
        'teacher' => ['classes.manage', 'students.view_assigned', 'reports.manage', 'attendance.manage',
            'messages.use', 'schedule.use', 'video.use', 'events.view'],
        'staff' => ['messages.use', 'schedule.use', 'video.use', 'events.view'],
        'school_admin' => ['messages.use', 'schedule.use', 'video.use', 'events.view'],
        'district_admin' => ['messages.use', 'schedule.use', 'video.use', 'events.view'],
    ];

    public function up()
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('country_id')->index();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedBigInteger('district_id')->nullable()->index()->after('country_id');
            $table->string('status', 16)->default('active')->after('district_id');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 32);
            $table->string('organization_type', 16);
            $table->unsignedBigInteger('organization_id');
            $table->string('status', 16)->default('pending');
            // legacy_link = created from a user_schools link; request = /auth/role-requests; admin = web panel
            $table->string('source', 16)->default('request');
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role', 'organization_type', 'organization_id'], 'user_roles_unique_context');
            $table->index(['organization_type', 'organization_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('context_permissions', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('description');
        });

        Schema::create('context_role_permissions', function (Blueprint $table) {
            $table->string('role', 32);
            $table->string('permission_key', 64);
            $table->primary(['role', 'permission_key']);
            $table->foreign('permission_key')->references('key')->on('context_permissions')->cascadeOnDelete();
        });

        Schema::create('user_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_role_id');
            $table->string('permission_key', 64);
            $table->boolean('granted');
            $table->timestamps();
            $table->unique(['user_role_id', 'permission_key']);
            $table->foreign('user_role_id')->references('id')->on('user_roles')->cascadeOnDelete();
            $table->foreign('permission_key')->references('key')->on('context_permissions')->cascadeOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('last_context_id')->nullable();
        });

        $now = now();
        DB::table('context_permissions')->insert(array_map(
            fn ($key, $description) => ['key' => $key, 'description' => $description],
            array_keys(self::PERMISSIONS), self::PERMISSIONS
        ));
        $rows = [];
        foreach (self::ROLE_DEFAULTS as $role => $keys) {
            foreach ($keys as $key) {
                $rows[] = ['role' => $role, 'permission_key' => $key];
            }
        }
        DB::table('context_role_permissions')->insert($rows);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_context_id');
        });
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_role_permissions');
        Schema::dropIfExists('context_role_permissions');
        Schema::dropIfExists('context_permissions');
        Schema::dropIfExists('user_roles');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['district_id', 'status']);
        });
        Schema::dropIfExists('districts');
    }
};
