<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meetings extends Model
{
    use HasFactory;


    protected $fillable = [
        'title',
        'user_id',
        'teacher_id',
        'parent_id',
        'meeting_date',
        'meeting_time',
        'description',
        'status',
        'meeting_created_user_type',
        'subject',
        'location',
        'is_online',
        'notes',
        'student_id'
    ];


    public function teacher() {

        return $this->belongsTo(User::class,'teacher_id','id');
    }
    public function created_user() {

        return $this->belongsTo(User::class,'user_id','id');
    }

    public function parent() {

        return $this->belongsTo(User::class,'parent_id','id');
    }
    public function student() {
        return $this->belongsTo(User::class,'student_id','id');
    }
     public function principal() {
        return $this->belongsTo(User::class,'principal_id','id');
    }

    public function getCreatedAtFormattedAttribute()
    {
        return Carbon::parse($this->attributes['created_at'])->format('Y-m-d');
        // Adjust the format according to your preference, this is just an example
    }

    // Override the 'created_at' attribute
    public function getCreatedAtAttribute($value)
    {
        return $this->attributes['created_at'] = $this->getCreatedAtFormattedAttribute();
    }

    public function getUpdatedAtFormattedAttribute()
    {
        return Carbon::parse($this->attributes['updated_at'])->format('Y-m-d');
        // Adjust the format according to your preference, this is just an example
    }

    public function getUpdatedAtAttribute($value)
    {
        return $this->attributes['updated_at'] = $this->getCreatedAtFormattedAttribute();
    }


}
