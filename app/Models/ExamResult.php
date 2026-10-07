<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    use HasFactory;
    protected $hidden = ["deleted_at","created_at","updated_at"];

    public function student(){
        return $this->belongsTo(Students::class ,'student_id','user_id')->withTrashed();
    }
    public function session_year(){
        return $this->belongsTo(SessionYear::class,'session_year_id');
    }

    public function exam(){
        return $this->belongsTo(Exam::class,'exam_id');
    }

}
