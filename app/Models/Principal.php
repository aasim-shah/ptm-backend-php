<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $created_at
 * @property string $updated_at
 * @property Schools[] $schools
 */
class Principal extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'principal';
    protected $filable = [
        'user_id',
    ];

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
    protected $fillable = ['created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function schools()
    {
        return $this->hasOne('App\Models\Schools','principal_id','user_id');
    }
}
