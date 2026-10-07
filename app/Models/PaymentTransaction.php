<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function student(){
        return $this->belongsTo(Students::class ,'student_id','user_id')->withTrashed();
    }
    public function class() {
        return $this->belongsTo(ClassSchool::class, 'class_id');
    }
    public function session_year() {
        return $this->belongsTo(SessionYear::class);
    }
}
