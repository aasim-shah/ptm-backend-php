<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'title',
        'gender',
        'email',
        'country_id',
        'fcm_id',
        'email_verified_at',
        'password',
        'mobile',
        'job_title',
        'industry',
        'image',
        'hear_about_us',
        'language',
        'dob',
        'current_address',
        'type',
        'pincode',
        'permanent_address',
        'status',
        'reset_request',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'pivot',
        'password',
        'remember_token',
        "deleted_at",
        "created_at",
        "updated_at"
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function student() {
        return $this->hasOne(Students::class, 'user_id', 'id');
    }
    public function students() {
        return $this->hasMany(Students::class, 'user_id', 'id');
    }
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
    public function parent() {
        return $this->hasOne(Parents::class, 'user_id', 'id');
    }
    public function principal() {
        return $this->hasOne(Principal::class, 'user_id', 'id');
    }

    public function teacher() {
        return $this->hasOne(Teacher::class, 'user_id', 'id');
    }

    public function rate() {
        return $this->hasOne(ApplicationRating::class, 'user_id', 'id');
    }
    public function userSchool() {
        return $this->hasMany(UserSchools::class, 'user_id');
    }



    //Getter Attributes
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }

    public function getUserMeetingInfo(){
        return $this->hasOne(UserMeeting::class,'user_id','id');
    }
    public function chat(){
        return $this->hasMany(Chat::class,'user_id','id');
    }
    public function toChatUser(){
        return $this->hasMany(Chat::class,'to_user_id','id');
    }
    public function sendPasswordResetNotification($token)
    {
        try {
            $this->notify(new CustomResetPassword($token));
            return true;
        }catch (\Exception $e){
            return true;
        }
    }
}
