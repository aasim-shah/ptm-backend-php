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
        // main online exam table
        Schema::create('online_exams', function (Blueprint $table) {
            $table->id();

            // foreign key of class subejcts
            $table->bigInteger('class_subject_id')->unsigned()->index();
            $table->foreign('class_subject_id')->references('id')->on('class_subjects')->onDelete('cascade');

            $table->string('title', 128);
            $table->bigInteger('exam_key');
            $table->integer('duration')->comment('in minutes');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->bigInteger('session_year_id')->unsigned()->index();
            $table->foreign('session_year_id')->references('id')->on('session_years')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // online exam questios table
        Schema::create('online_exam_questions', function (Blueprint $table) {
            $table->id();

           // foreign key of class subejcts
           $table->bigInteger('class_subject_id')->unsigned()->index();
           $table->foreign('class_subject_id')->references('id')->on('class_subjects')->onDelete('cascade');

            $table->tinyInteger('question_type')->comment('0 - simple 1 - equation based');
            $table->string('question', 1024);
            $table->string('image_url',1024)->nullable(true);
            $table->string('note', 1024)->nullable(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // online exam question's options table
        Schema::create('online_exam_question_options', function (Blueprint $table) {
            $table->id();

            //foreign key of online_exam_questions
            $table->bigInteger('question_id')->unsigned()->index();
            $table->foreign('question_id')->references('id')->on('online_exam_questions')->onDelete('cascade');

            $table->string('option', 1024);
            $table->timestamps();
            $table->softDeletes();
        });

        // online exam question's answers table
        Schema::create('online_exam_question_answers', function (Blueprint $table) {
            $table->id();

            //foreign key of online_exam_questions
            $table->bigInteger('question_id')->unsigned()->index();
            $table->foreign('question_id')->references('id')->on('online_exam_questions')->onDelete('cascade');

            $table->bigInteger('answer')->unsigned()->index()->comment('option id');
            $table->foreign('answer')->references('id')->on('online_exam_question_options')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        // online exam question choiced table
        Schema::create('online_exam_question_choices', function (Blueprint $table) {
            $table->id();

            //foreign key of online_exams
            $table->bigInteger('online_exam_id')->unsigned()->index();
            $table->foreign('online_exam_id')->references('id')->on('online_exams')->onDelete('cascade');

            $table->bigInteger('question_id')->unsigned()->index();
            $table->foreign('question_id')->references('id')->on('online_exam_questions')->onDelete('cascade');

            $table->integer('marks')->nullable(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // student status for online exam table table
        Schema::create('student_online_exam_statuses', function (Blueprint $table) {
            $table->id();

            // foreign key of students
            $table->bigInteger('student_id')->unsigned()->index();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            //foreign key of online_exams
            $table->bigInteger('online_exam_id')->unsigned()->index();
            $table->foreign('online_exam_id')->references('id')->on('online_exams')->onDelete('cascade');

            $table->tinyInteger('status')->comment('1 - in progress 2 - completed');
            $table->timestamps();
            $table->softDeletes();
        });

        // student answers for online exam table table
        Schema::create('online_exam_student_answers', function (Blueprint $table) {
            $table->id();

            // foreign key of students
            $table->bigInteger('student_id')->unsigned()->index();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            //foreign key of online_exams
            $table->bigInteger('online_exam_id')->unsigned()->index();
            $table->foreign('online_exam_id')->references('id')->on('online_exams')->onDelete('cascade');

            //foreign key of online_exam_questions
            $table->bigInteger('question_id')->unsigned()->index()->comment('online exam question choice id');
            $table->foreign('question_id')->references('id')->on('online_exam_question_choices')->onDelete('cascade');

            $table->bigInteger('option_id')->unsigned()->index();
            $table->foreign('option_id')->references('id')->on('online_exam_question_options')->onDelete('cascade');

            $table->date('submitted_date');

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
        Schema::dropIfExists('online_exam_student_answers');
        Schema::dropIfExists('student_online_exam_statuses');
        Schema::dropIfExists('online_exam_question_choices');
        Schema::dropIfExists('online_exam_question_answers');
        Schema::dropIfExists('online_exam_question_options');
        Schema::dropIfExists('online_exam_questions');
        Schema::dropIfExists('online_exams');
    }
};
