<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Timetable extends Model
{
    protected $hidden = ["deleted_at", "created_at", "updated_at"];
    use SoftDeletes;

    public function subject_teacher() {
        return $this->belongsTo(SubjectTeacher::class)->with('subject', 'teacher.user:id,first_name,last_name')->withTrashed();
    }

    public function class_section() {
        return $this->belongsTo(ClassSection::class)->with('class.medium', 'section');
    }

    public function subject() {
        return $this->belongsTo(SubjectTeacher::class, 'subject_teacher_id')->with('subject')->withTrashed();
    }
}
