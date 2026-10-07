<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LessonTopic extends Model
{
    use HasFactory;

    protected static function boot() {
        parent::boot();
        static::deleting(function ($topic) { // before delete() method call this
            if ($topic->file) {
                foreach ($topic->file as $file) {
                    if (Storage::disk('public')->exists($file->file_url)) {
                        Storage::disk('public')->delete($file->file_url);
                    }
                }
                $topic->file()->delete();
            }
        });
    }

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function file() {
        return $this->morphMany(File::class, 'modal');
    }

    public function lesson() {
        return $this->belongsTo(Lesson::class);
    }

    public function scopeLessonTopicTeachers($query) {
        $user = Auth::user();
        if ($user->hasRole('Teacher')) {
            $teacher_id = $user->teacher()->pluck('user_id')->first();
            $subject_teacher = SubjectTeacher::select('class_section_id', 'subject_id')->where('teacher_id', $teacher_id)->get();
            if ($subject_teacher) {
                $subject_teacher = $subject_teacher->toArray();
                $class_section_id = array_column($subject_teacher, 'class_section_id');
                $subject_id = array_column($subject_teacher, 'subject_id');
                $lesson_id = Lesson::select('id')->whereIn('class_section_id', $class_section_id)->whereIn('subject_id', $subject_id)->get()->pluck('id');
                return $query->whereIn('lesson_id', $lesson_id);
            }
            return $query;
        }
        return $query;
    }
}
