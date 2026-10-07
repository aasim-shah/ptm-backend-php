<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $quarter_id
 * @property integer $student_id
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property Quarters $quarter
 * @property User $user
 */
class StudentQuarter extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'student_quarter';

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
    protected $fillable = ['quarter_id','session_year_id', 'student_id','class_id', 'status', 'created_at', 'updated_at'];

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
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'student_id','id');
    }
}
