<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('class_section_id');
            $table->integer('category_id');
            $table->string('admission_no', 512);
            $table->integer('roll_number');
            $table->string('caste', 128)->nullable();
            $table->string('religion', 128)->nullable();
            $table->date('admission_date');
            $table->string('blood_group', 32)->nullable();
            $table->string('height', 32)->nullable();
            $table->string('weight', 64)->nullable();
            $table->string('father_name', 128)->nullable();
            $table->string('father_phone', 64)->nullable();
            $table->string('father_occupation', 128)->nullable();
            $table->string('father_image', 512)->nullable();
            $table->string('mother_name', 128)->nullable();
            $table->string('mother_occupation', 128)->nullable();
            $table->string('mother_image', 512)->nullable();
            $table->integer('parent_id');
            $table->tinyInteger('is_new_admission')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('qualification', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('session_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->tinyInteger('default')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->integer('medium_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id');
            $table->integer('section_id');
            $table->integer('class_teacher_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mediums', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->string('code', 64)->nullable();
            $table->string('bg_color', 32)->nullable();
            $table->string('image', 512)->nullable();
            $table->integer('medium_id')->nullable();
            $table->string('type', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id')->nullable();
            $table->string('type', 32)->comment('Compulsory / Elective');
            $table->integer('subject_id');
            $table->integer('elective_subject_group_id')->nullable()->comment('if type=Elective');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('elective_subject_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('total_subjects');
            $table->integer('total_selectable_subjects');
            $table->integer('class_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subject_teachers', function (Blueprint $table) {
            $table->id();
            $table->integer('class_section_id');
            $table->integer('subject_id');
            $table->integer('teacher_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('subject_id');
            $table->integer('class_section_id');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->string('description', 1024)->nullable();
            $table->integer('class_section_id');
            $table->integer('subject_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lesson_topics', function (Blueprint $table) {
            $table->id();
            $table->integer('lesson_id');
            $table->string('name', 128);
            $table->string('description', 1024)->nullable();
            $table->string('file', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->integer('class_section_id');
            $table->integer('subject_id');
            $table->string('name', 128);
            $table->string('instructions', 1024)->nullable();
            $table->dateTime('due_date');
            $table->integer('points')->nullable();
            $table->boolean('resubmission')->default(0);
            $table->integer('extra_days_for_resubmission')->nullable();
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->integer('assignment_id');
            $table->integer('student_id');
            $table->integer('session_year_id');
            $table->text('feedback')->nullable();
            $table->integer('points')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0 = Pending/In Review , 1 = Accepted , 2 = Rejected , 3 = Resubmitted');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('description', 1024)->nullable();
            $table->integer('class_id');
            $table->integer('session_year_id');
            $table->tinyInteger('publish')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_timetables', function (Blueprint $table) {
            $table->id();
            $table->integer('exam_id');
            $table->integer('subject_id');
            $table->integer('total_marks');
            $table->integer('passing_marks');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->integer('exam_timetable_id');
            $table->integer('student_id');
            $table->integer('subject_id');
            $table->integer('obtained_marks');
            $table->string('teacher_review', 1024)->nullable();
            $table->boolean('passing_status')->comment('1=Pass, 0=Fail');
            $table->integer('session_year_id');
            $table->tinyText('grade')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->integer('exam_id');
            $table->integer('class_section_id');
            $table->integer('student_id');
            $table->integer('total_marks');
            $table->integer('obtained_marks');
            $table->float('percentage');
            $table->tinyText('grade');
            $table->integer('session_year_id');
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->integer('minimum_percentage');
            $table->integer('maximum_percentage');
            $table->tinyText('grade');
            $table->timestamps();
        });

        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->integer('subject_teacher_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('note', 1024)->nullable();
            $table->integer('day')->comment('1=monday,2=tuesday,3=wednesday,4=thursday,5=friday,6=saturday,7=sunday');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('description', 1024)->nullable();
            $table->integer('class_id');
            $table->float('amount');
            $table->date('due_date');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fees_sub_types', function (Blueprint $table) {
            $table->id();
            $table->integer('fees_id');
            $table->string('name');
            $table->string('description', 1024)->nullable();
            $table->float('amount');
            $table->integer('session_year_id');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fees_paid', function (Blueprint $table) {
            $table->id();
            $table->integer('fees_id');
            $table->integer('student_id');
            $table->tinyInteger('status')->default(0)->comment('0=Not Paid,1=Paid');
            $table->string('description', 1024)->nullable();
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 128);
            $table->string('description', 1024);
            $table->nullableMorphs('table');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title', 512);
            $table->string('description', 1024)->nullable();
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('session_year_id');
            $table->tinyInteger('type')->comment('0=Absent, 1=Present');
            $table->date('date');
            $table->string('remark', 512);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('students');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('session_years');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('mediums');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('elective_subject_groups');
        Schema::dropIfExists('subject_teachers');
        Schema::dropIfExists('student_subjects');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('lesson_topics');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_timetables');
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('timetables');
        Schema::dropIfExists('fees');
        Schema::dropIfExists('fees_sub_types');
        Schema::dropIfExists('fees_paid');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('academic_calendars');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('teachers');
    }
};
