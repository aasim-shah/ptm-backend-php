<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $quarter_id
 * @property integer $student_id
 * @property integer $class_id
 * @property integer $subject_id
 * @property string $session_year
 * @property string $points
 * @property string $review
 * @property string $created_at
 * @property string $updated_at
 * @property ClassSchool $class
 * @property User $user
 * @property Quarters $quarter
 * @property Subject $subject
 */
class StudentReportCards extends Model
{
    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['quarter_id','missing_cause', 'student_id', 'class_id', 'subject_id', 'session_year', 'points', 'review', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Models\ClassSchool');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'student_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function quarter()
    {
        return $this->belongsTo('App\Models\Quarters');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo('App\Models\Subject');
    }
}
