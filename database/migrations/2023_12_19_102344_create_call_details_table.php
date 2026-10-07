<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->unsignedBigInteger('end_user_id')->nullable();
            $table->string('sid')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('duration')->nullable();
            $table->foreign('host_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('end_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_details');
    }
};
