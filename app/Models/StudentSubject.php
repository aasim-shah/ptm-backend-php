<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSubject extends Model
{
    use HasFactory;
    protected $fillable = ['student_id'];
    protected $hidden = ["deleted_at","created_at","updated_at"];
    public function subject(){
        return $this->belongsTo(Subject::class);
    }
}
