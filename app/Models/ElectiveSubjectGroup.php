<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectiveSubjectGroup extends Model
{
    use HasFactory;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function electiveSubjects() {
        return $this->hasMany(ClassSubject::class, 'elective_subject_group_id');
    }
}
