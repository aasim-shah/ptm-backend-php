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
        Schema::create('student_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('class_section_id');
            $table->integer('session_year_id');
            $table->tinyInteger('result')->default(1)->comment('1=>Pass,0=>fail');
            $table->tinyInteger('status')->default(1)->comment('1=>continue,0=>leave');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_sessions');
    }
};
