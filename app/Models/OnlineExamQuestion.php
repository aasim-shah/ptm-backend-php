<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnlineExamQuestion extends Model
{
    use HasFactory;
    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function class_subject() {
        return $this->belongsTo(ClassSubject::class,'class_subject_id');
    }
    public function options(){
        return $this->hasMany(OnlineExamQuestionOption::class,'question_id');
    }
    public function answers(){
        return $this->hasMany(OnlineExamQuestionAnswer::class,'question_id')->with('options');
    }

    public function getImageUrlAttribute($value) {
        if($value){
            return url(Storage::url($value));
        }else{
            return null;
        }
    }
}
