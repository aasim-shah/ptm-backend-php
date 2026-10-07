<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Parents extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = ['user_id','first_name','last_name','gender','email','mobile','occupation','dob'];

    protected $hidden = ["deleted_at", "created_at", "updated_at"];

    public function announcement()
    {
        return $this->morphMany(Announcement::class, 'table');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
    public function schools()
    {
        return $this->belongsToMany(Schools::class,'user_schools','user_id','school_id','user_id');
    }

    public function fatherRelationChild()
    {
        return $this->hasMany(Students::class, 'father_id');
    }

    public function motherRelationChild()
    {
        return $this->hasMany(Students::class, 'mother_id');
    }

    public function guardianRelationChild()
    {
        return $this->hasMany(Students::class, 'guardian_id');
    }

    public function children()
    {
        return $this->fatherRelationChild()->union($this->motherRelationChild())->union($this->guardianRelationChild());
    }

    //Getter Attributes
    public function getImageAttribute($value)
    {
        return url(Storage::url($value));
    }
}
