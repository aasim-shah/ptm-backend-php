<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationRating extends Model
{
    use HasFactory;

    protected $table='ratings';
    protected $fillable = [
        'user_id',
        'rating',
        'review',
    ];

    public function user(){
        return $this->hasOne(User::class,'rate_id', 'id');
    }


}
