<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $hidden = ["remark","deleted_at","created_at","updated_at"];
    protected $fillable = ["remark","class_section_id","session_year_id","student_id","type","date","subject_id"];

    public function student()
    {
        return $this->belongsTo(Students::class,'user_id','user_id')->with('user');
    }

    public function subject(){
        return $this->belongsTo(Subject::class,'subject_id');
    }
}
