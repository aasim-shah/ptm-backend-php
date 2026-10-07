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
        Schema::table('timetables', function (Blueprint $table) {
            $table->integer('class_section_id')->after('subject_teacher_id');
        });
    }
    
    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropColumn('class_section_id');
            
        });
    }
};
