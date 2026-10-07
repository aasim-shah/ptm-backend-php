<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class SubjectTeacher extends Model
{
    use SoftDeletes;

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class)->with('class.medium', 'section')->withTrashed();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class,'teacher_id','user_id')->with('user')->withTrashed();
    }

    public function scopeSubjectTeacher($query, $class_section_id = null)
    {
        $user = Auth::user();
        if ($user->hasRole('Teacher')) {
            $teacher_id = $user->teacher()->pluck('user_id');
            return $query->whereIn('teacher_id', $teacher_id);
        }
        return $query;
    }
}