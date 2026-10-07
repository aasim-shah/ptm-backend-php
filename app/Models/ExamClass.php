<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamClass extends Model
{
    use HasFactory;
    use \Awobaz\Compoships\Compoships;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function class() {
        return $this->belongsTo(ClassSchool::class, 'class_id');
    }

    public function exam() {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function timetableByClassID() {
        return $this->hasMany(ExamTimetable::class, 'class_id', 'class_id');
    }

    public function class_timetable($exam_id = NULL, $class_id = NULL) {
        //IF possible then don't use this method in eager loading because it doesn't match class_id AND exam_id
//        return $this->timetableByExamID()->where('class_id', $this->class_id)->get();
        return $this->hasMany(ExamTimetable::class, ['class_id', 'exam_id'], ['class_id', 'exam_id']);
//        $query = new ExamTimetable();
//        if ($exam_id) {
//            $query = $query->where('exam_id', $exam_id);
//        }
//
//        if ($class_id) {
//            $query = $query->where('class_id', $class_id);
//        }
//
//        return $query->get();

    }

    public function timetableByExamID() {
        return $this->hasMany(ExamTimetable::class, 'exam_id', 'exam_id');
    }
}
