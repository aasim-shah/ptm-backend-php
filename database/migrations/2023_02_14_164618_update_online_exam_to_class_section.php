<?php

use App\Models\ClassSection;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = DB::table('online_exams')->get();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::drop('online_exams');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Schema::create('online_exams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('model');

            //add foreign key of subject
            $table->bigInteger('subject_id')->unsigned()->index();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');

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
        foreach ($data as $item) {
            $class_id = ClassSubject::where('id',$item->class_subject_id)->pluck('class_id')->first();
            $subject_id = ClassSubject::where('id',$item->class_subject_id)->pluck('subject_id')->first();
            DB::table('online_exams')->insert([
                'id' => $item->id,
                'model_type' => 'App\Models\ClassSchool',
                'model_id' => $class_id,
                'subject_id' => $subject_id,
                'title' => $item->title,
                'exam_key' => $item->exam_key,
                'duration' => $item->duration,
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'session_year_id' => $item->session_year_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $data = DB::table('online_exams')->get();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::drop('online_exams');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

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
        foreach ($data as $item) {
            if($item->model_type == 'App\Models\ClassSchool'){
                $class_subject_id = ClassSubject::where(['class_id' => $item->model_id, 'subject_id' => $item->subject_id])->pluck('id')->first();
            }else{
                $class_id = ClassSection::where('id',$item->model_id)->pluck('class_id')->first();
                $class_subject_id = ClassSubject::where(['class_id' => $class_id, 'subject_id' => $item->subject_id])->pluck('id')->first();
            }
            DB::table('online_exams')->insert([
                'id' => $item->id,
                'class_subject_id' => $class_subject_id,
                'title' => $item->title,
                'exam_key' => $item->exam_key,
                'duration' => $item->duration,
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'session_year_id' => $item->session_year_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        }
    }
};
