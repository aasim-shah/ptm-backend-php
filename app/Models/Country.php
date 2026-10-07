<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $code
 * @property string $name
 * @property integer $phonecode
 */
class Country extends Model
{
    /**
     * @var array
     */
    protected $fillable = ['code', 'name', 'phonecode'];
}
