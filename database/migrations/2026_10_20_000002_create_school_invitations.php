<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::create('workspace_invitations', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools'); $t->foreignId('invited_by')->constrained('users');
            $t->string('email'); $t->string('role',32); $t->json('permissions'); $t->string('token_hash',64)->unique();
            $t->timestamp('expires_at'); $t->timestamp('accepted_at')->nullable(); $t->timestamp('revoked_at')->nullable();
            $t->foreignId('accepted_by')->nullable()->constrained('users'); $t->timestamps(); $t->index(['school_id','email']);
        });
    }
    public function down() { Schema::dropIfExists('workspace_invitations'); }
};
