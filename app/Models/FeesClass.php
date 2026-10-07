<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeesClass extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function fees_type(){
        return $this->belongsTo(FeesType::class ,'fees_type_id');
    }

    public function fees_paid() {
        return $this->hasMany(FeesPaid::class, 'fees_class_id');
    }

    public function class() {
        return $this->belongsTo(ClassSchool::class, 'class_id')->with('medium');
    }
}
