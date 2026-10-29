<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::create('account_security', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users');
            $t->text('secret')->nullable(); $t->timestamp('pending_until')->nullable();
            $t->timestamp('enabled_at')->nullable(); $t->json('recovery_hashes')->nullable();
            $t->unsignedBigInteger('last_counter')->nullable(); $t->unsignedInteger('version')->default(1); $t->timestamps();
        });
        Schema::create('account_sessions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users');
            $t->string('session_hash', 64)->unique(); $t->string('device', 191);
            $t->timestamp('last_seen_at'); $t->timestamp('revoked_at')->nullable(); $t->timestamps();
            $t->index(['user_id', 'revoked_at']);
        });
        Schema::create('account_token_mfa', function (Blueprint $t) {
            $t->unsignedBigInteger('token_id')->primary(); $t->foreignId('user_id')->constrained('users');
            $t->unsignedInteger('version'); $t->timestamp('verified_at');
        });
    }
    public function down()
    {
        Schema::dropIfExists('account_token_mfa'); Schema::dropIfExists('account_sessions'); Schema::dropIfExists('account_security');
    }
};
