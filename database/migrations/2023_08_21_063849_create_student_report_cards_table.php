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
        Schema::create('student_report_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_year');
            $table->uuid('quarter_id');
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->bigInteger('class_id')->unsigned()->index();
            $table->bigInteger('subject_id')->unsigned()->index();
            $table->string('points')->nullable();
            $table->text('review')->nullable();

            $table->foreign('quarter_id')->references('id')->on('quarters')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
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
        Schema::dropIfExists('student_report_cards');
    }
};
