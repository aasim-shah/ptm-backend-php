<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $name
 * @property string $description
 * @property string $type
 * @property string $amount
 * @property string $currency
 * @property boolean $status
 * @property string $created_at
 * @property string $updated_at
 */
class Plan extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'plan';

    const REGULAR_MONTH = 'REGULAR-Month';
    const TRAIL = 'TRAIL';
    const ACTIVE = 'ACTIVE';

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
    protected $fillable = ['plan_id', 'name', 'description', 'type', 'amount', 'currency', 'status', 'created_at', 'updated_at'];
}
