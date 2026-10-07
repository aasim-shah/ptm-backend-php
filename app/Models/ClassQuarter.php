<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $quarter_id
 * @property integer $class_id
 * @property string $session_year
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property ClassSchool $class
 * @property Quarters $quarter
 */
class ClassQuarter extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'class_quarter';

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
    protected $fillable = ['quarter_id', 'class_id', 'session_year','session_year_id', 'status', 'created_at', 'updated_at'];

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
    public function quarter()
    {
        return $this->belongsTo('App\Models\Quarters');
    }
}
