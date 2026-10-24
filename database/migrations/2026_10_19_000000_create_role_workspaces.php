<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('workspace_districts', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('market', 2); $t->timestamps();
        });
        Schema::create('workspace_district_schools', function (Blueprint $t) {
            $t->foreignId('district_id')->constrained('workspace_districts')->cascadeOnDelete();
            $t->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $t->primary(['district_id', 'school_id']);
            $t->unique('school_id');
        });
        Schema::create('workspace_memberships', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users');
            $t->foreignId('school_id')->nullable()->constrained('schools');
            $t->foreignId('district_id')->nullable()->constrained('workspace_districts');
            $t->string('role', 32); $t->json('permissions')->nullable();
            $t->string('status', 16)->default('active');
            $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamps(); $t->index(['user_id', 'status']);
            $t->unique(['user_id', 'school_id', 'role']);
            $t->unique(['user_id', 'district_id', 'role']);
        });
        Schema::create('workspace_guardians', function (Blueprint $t) {
            $t->id(); $t->foreignId('student_id')->constrained('students');
            $t->foreignId('guardian_id')->constrained('users');
            $t->foreignId('school_id')->constrained('schools');
            $t->string('status', 16)->default('pending');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable(); $t->timestamp('ends_at')->nullable();
            $t->timestamps(); $t->unique(['student_id', 'guardian_id', 'school_id']);
        });
        Schema::create('workspace_updates', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools');
            $t->foreignId('student_id')->constrained('students');
            $t->foreignId('author_id')->constrained('users');
            $t->string('title', 160); $t->text('body'); $t->string('status', 16)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('published_at')->nullable(); $t->timestamps();
            $t->index(['school_id', 'status']);
        });
        Schema::create('workspace_update_versions', function (Blueprint $t) {
            $t->id(); $t->foreignId('update_id')->constrained('workspace_updates');
            $t->foreignId('actor_id')->constrained('users');
            $t->unsignedInteger('version'); $t->string('title', 160); $t->text('body');
            $t->string('status', 16); $t->timestamp('created_at');
            $t->unique(['update_id', 'version']);
        });
        Schema::create('workspace_posts', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools');
            $t->foreignId('author_id')->constrained('users');
            $t->string('kind', 16); $t->string('audience', 32)->default('all');
            $t->string('title', 160); $t->text('body');
            $t->timestamp('starts_at')->nullable(); $t->timestamps();
            $t->index(['school_id', 'kind', 'audience']);
        });
        Schema::create('workspace_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools');
            $t->foreignId('sender_id')->constrained('users');
            $t->foreignId('recipient_id')->constrained('users');
            $t->text('body'); $t->timestamp('read_at')->nullable(); $t->timestamps();
            $t->index(['school_id', 'recipient_id', 'read_at']);
        });
        Schema::create('workspace_appointments', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools');
            $t->foreignId('organizer_id')->constrained('users');
            $t->foreignId('participant_id')->constrained('users');
            $t->string('title', 160); $t->timestamp('starts_at'); $t->timestamp('ends_at');
            $t->string('status', 16)->default('pending'); $t->timestamps();
            $t->index(['school_id', 'starts_at']);
        });
        Schema::create('workspace_audit', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->constrained('users');
            $t->unsignedBigInteger('school_id')->nullable();
            $t->string('action'); $t->string('subject'); $t->timestamp('created_at');
        });
        // Existing institution-appointed principals are authoritative. Legacy parent
        // and teacher links need review, so they are not silently approved here.
        DB::table('schools')->whereNotNull('principal_id')->orderBy('id')->each(function ($school) {
            if (DB::table('users')->where('id', $school->principal_id)->whereNull('deleted_at')->exists()) {
                DB::table('workspace_memberships')->insert([
                    'user_id' => $school->principal_id, 'school_id' => $school->id,
                    'role' => 'school_admin', 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down()
    {
        foreach (['workspace_audit', 'workspace_appointments', 'workspace_messages', 'workspace_posts',
            'workspace_update_versions', 'workspace_updates', 'workspace_guardians', 'workspace_memberships',
            'workspace_district_schools', 'workspace_districts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
