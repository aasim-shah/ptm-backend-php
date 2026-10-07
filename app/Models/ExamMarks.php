<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamMarks extends Model
{
    use HasFactory;
    protected $hidden = ["deleted_at","created_at","updated_at"];

    public function timetable(){
        return $this->belongsTo(ExamTimetable::class,'exam_timetable_id');
    }

    public function subject(){
        return $this->belongsTo(Subject::class);
    }

    public function student(){
        return $this->belongsTo(Students::class,'student_id','user_id')->withTrashed();
    }


    //     return $this->hasManyThrough(Exam::class,ExamTimetable::class,'id','id','exam_id')->orderBy('date','asc');
    // }

    // // Working demo
    // public function results(){
    //     return $this->hasManyThrough(ExamMarks::class,ExamTimetable::class,'exam_id','exam_timetable_id')->orderBy('date','asc');
    // }
}
