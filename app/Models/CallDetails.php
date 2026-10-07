<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property integer $host_user_id
 * @property integer $end_user_id
 * @property string $sid
 * @property string $channel_name
 * @property string $duration
 * @property string $created_at
 * @property string $updated_at
 * @property User $host_user
 * @property User $end_user
 */
class CallDetails extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'call_details';

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
    protected $fillable = ['host_user_id', 'end_user_id', 'sid', 'channel_name', 'duration', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function host_user()
    {
        return $this->belongsTo('App\Models\User', 'end_user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function end_user()
    {
        return $this->belongsTo('App\Models\User', 'host_user_id');
    }
}
