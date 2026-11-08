<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full chat history in MySQL (replaces Firestore messages/{chatId}/chatMessages).
 * The `chat` table keeps only the conversation summary used by the chat lists.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 36)->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('receiver_id')->index();
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_extension', 16)->nullable();
            $table->boolean('is_image')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'id']);
            $table->foreign('chat_id')->references('id')->on('chat')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users');
            $table->foreign('receiver_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_messages');
    }
};
