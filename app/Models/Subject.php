<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @property integer $id
 * @property string $name
 * @property string $code
 * @property string $bg_color
 * @property string $image
 * @property integer $medium_id
 * @property string $type
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property OnlineExam[] $onlineExams
 * @property StudentReportCards[] $studentReportCards
 * @property SchoolSubjects[] $SchoolSubjects
 */
class Subject extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
     * @var array
     */
    protected $fillable = ['name', 'code','teacher_id', 'bg_color', 'image', 'medium_id', 'type', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function onlineExams()
    {
        return $this->hasMany('App\Models\OnlineExam');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function studentReportCards()
    {
        return $this->hasMany('App\Models\StudentReportCards');
    }

    public function medium() {
        return $this->belongsTo(Mediums::class)->withTrashed();
    }
    public function subject_teacher() {
        return $this->hasMany(SubjectTeacher::class,'subject_id');
    }

    public function scopeSubjectTeacher($query) {
        $user = Auth::user();
        if ($user->hasRole('Teacher')) {
            $subjects_ids = $user->teacher->subjects()->pluck('subject_id');
            return $query->whereIn('id', $subjects_ids);
        }
        return $query;
    }

//Getter Attributes
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schoolSubjects()
    {
        return $this->hasMany('App\Models\SchoolSubjects','');
    }
    public function class_subject() {
        return $this->hasMany(ClassSubject::class)->where('deleted_at',null);
    }
}
