<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::create('workspace_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users'); $t->foreignId('school_id')->constrained('schools');
            $t->string('kind', 24); $t->unsignedBigInteger('resource_id'); $t->string('event_key', 160);
            $t->timestamp('read_at')->nullable(); $t->timestamps(); $t->unique(['user_id', 'event_key']);
        });
        Schema::create('workspace_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('notification_id')->constrained('workspace_notifications');
            $t->string('channel', 16); $t->string('status', 16)->default('pending'); $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at'); $t->timestamp('locked_at')->nullable(); $t->timestamp('sent_at')->nullable();
            $t->string('last_error')->nullable(); $t->timestamps(); $t->unique(['notification_id', 'channel']); $t->index(['status', 'available_at']);
        });
        Schema::create('workspace_devices', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users'); $t->string('token_hash', 64)->unique();
            $t->text('token'); $t->string('platform', 16); $t->timestamp('revoked_at')->nullable(); $t->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('workspace_devices'); Schema::dropIfExists('workspace_deliveries'); Schema::dropIfExists('workspace_notifications');
    }
};
