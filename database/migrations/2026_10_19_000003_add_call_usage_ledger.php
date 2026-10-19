<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-owned call minute ledger (backend update, Part B1 / Doc 12 §8).
 *
 * user_call_room now records when the server saw the join and the leave, and how many
 * seconds of the current call session were already charged. Both the Agora webhook (leave
 * event) and the app's POST /update-remaining-time charge only the part not yet charged,
 * so a session is never billed twice. call_usage_ledger is insert-only: one row per charge.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('user_call_room', function (Blueprint $table) {
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->unsignedInteger('charged_seconds')->default(0);
        });

        Schema::create('call_usage_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_call_room_id', 64)->nullable()->index();
            $table->string('channel_name')->nullable();
            $table->unsignedInteger('seconds');
            $table->unsignedInteger('client_reported_seconds')->nullable();
            $table->string('source', 32);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('call_usage_ledger');
        Schema::table('user_call_room', function (Blueprint $table) {
            $table->dropColumn(['joined_at', 'left_at', 'charged_seconds']);
        });
    }
};
