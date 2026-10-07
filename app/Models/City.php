<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{


    public function states(){
        return $this->belongsTo(\App\Models\State::class,'id');
    }
}
