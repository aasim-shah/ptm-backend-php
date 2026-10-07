<?php

namespace App\Models;


use App\Models\Subject;
use App\Models\ClassSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnlineExam extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function model() {
        return $this->morphTo();
    }

    public function subject() {
        return $this->belongsTo(Subject::class,'subject_id');
    }

    public function question_choice(){
        return $this->hasMany(OnlineExamQuestionChoice::class,'online_exam_id');
    }

    public function student_attempt(){
        return $this->hasOne(StudentOnlineExamStatus::class,'online_exam_id');
    }

}
