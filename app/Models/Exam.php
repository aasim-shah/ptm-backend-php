<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function exam_classes() {
        return $this->hasMany(ExamClass::class);
    }
    public function session_year() {
        return $this->belongsTo(SessionYear::class);
    }
    public function marks() {
        return $this->hasManyThrough(ExamMarks::class, ExamTimetable::class, 'exam_id', 'exam_timetable_id')->orderBy('date', 'asc');
    }
    public function timetable(){
        return $this->hasMany(ExamTimetable::class);
    }
    public function results(){
        return $this->hasMany(ExamResult::class,'exam_id');
    }
}
