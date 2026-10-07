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
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('class_id');
        });
        Schema::create('exam_classes', function (Blueprint $table) {
            $table->id();
            $table->integer('exam_id');
            $table->integer('class_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->integer('class_id')->after('exam_id');
        });
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('minimum_percentage');
            $table->integer('starting_range')->after('id');
            $table->dropColumn('maximum_percentage');
            $table->integer('ending_range')->after('starting_range');
        });
        Schema::table('exam_marks', function (Blueprint $table) {
            $table->string('teacher_review', 1024)->nullable()->default(NULL)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_classes');
        Schema::table('exams', function (Blueprint $table) {
            $table->integer('class_id')->after('description');
        });
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropColumn('class_id');
        });
        Schema::table('exam_marks', function (Blueprint $table) {
            $table->string('teacher_review', 1024)->nullable()->change();
        });
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('starting_range');
            $table->integer('minimum_percentage')->after('id');
            $table->dropColumn('ending_range');
            $table->integer('maximum_percentage')->after('minimum_percentage');
        });
    }
};
