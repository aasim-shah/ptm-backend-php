<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnlineExamQuestionAnswer extends Model
{
    use HasFactory;
    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function question_option() {
        return $this->belongsTo(OnlineExamQuestionOption::class,'answer');
    }
    public function options() {
        return $this->belongsTo(OnlineExamQuestionOption::class,'answer');
    }
}
