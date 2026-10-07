<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property integer $user_id
 * @property string $meeting_hash
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 */
class MainMeeting extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'main_meeting';

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
    protected $fillable = ['user_id','title','description', 'meeting_hash', 'status', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
    public function meeting_details()
    {
        return $this->hasMany('App\Models\Meetings','meeting_hash','meeting_hash');
    }
}
