<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeesPaid extends Model
{
    use HasFactory;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function session_year(){
        return $this->belongsTo(SessionYear::class,'session_year_id');
    }

    public function student(){
        return $this->belongsTo(Students::class ,'student_id','user_id');
    }

    public function class() {
        return $this->belongsTo(ClassSchool::class,'class_id')->with('medium')->withTrashed();
    }

    public function payment_transaction() {
        return $this->belongsTo(PaymentTransaction::class,'payment_transaction_id');
    }
}
