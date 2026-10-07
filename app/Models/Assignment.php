<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Assignment extends Model
{
    use HasFactory;

    protected $hidden = ["deleted_at", "updated_at"];

    protected static function boot() {
        parent::boot();
        static::deleting(function ($assignment) { // before delete() method call this
            //Deletes all the Assignment Submissions first
            $assignment_submission = AssignmentSubmission::where('assignment_id', $assignment->id)->get();
            if ($assignment_submission) {
                foreach ($assignment_submission as $submission) {
                    if (isset($submission->file)) {
                        foreach ($submission->file as $file) {
                            if (Storage::disk('public')->exists($file->file_url)) {
                                Storage::disk('public')->delete($file->file_url);
                            }
                        }
                        $submission->delete();
                    }
                }
            }

            //After that Delete Assignment and its files from the server
            if ($assignment->file) {
                foreach ($assignment->file as $file) {
                    if (Storage::disk('public')->exists($file->file_url)) {
                        Storage::disk('public')->delete($file->file_url);
                    }
                }
            }
            $assignment->file()->delete();
        });
    }

    public function subject() {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function submission() {
        return $this->hasOne(AssignmentSubmission::class);
    }

    public function class_section() {
        return $this->belongsTo(ClassSection::class)->with('class', 'section');
    }

    public function file() {
        return $this->morphMany(File::class, 'modal');
    }

    public function scopeAssignmentTeachers($query) {
        $user = Auth::user();
        if ($user->hasRole('Teacher')) {
            $teacher_id = $user->teacher()->pluck('user_id')->first();
            $subject_teacher = SubjectTeacher::select('class_section_id', 'subject_id')->where('teacher_id', $teacher_id)->get();
            if ($subject_teacher) {
                $subject_teacher = $subject_teacher->toArray();
                $class_section_id = array_column($subject_teacher, 'class_section_id');
                $subject_id = array_column($subject_teacher, 'subject_id');
                return $query->whereIn('class_section_id', $class_section_id)->whereIn('subject_id', $subject_id);
            }
            return $query;
        }
        return $query;
    }
}
