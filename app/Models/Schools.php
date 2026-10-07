<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property integer $id
 * @property string $principal_id
 * @property integer $user_id
 * @property integer $country_id
 * @property string $school_name
 * @property string $address
 * @property string $locality
 * @property string $post_town
 * @property string $post_code
 * @property string $image
 * @property string $email
 * @property string $phone
 * @property string $website
 * @property string $created_at
 * @property string $updated_at
 * @property Announcement[] $announcements
 * @property ClassSchool[] $classes
 * @property Mediums[] $mediums
 * @property Principal $principal
 * @property Section[] $sections
 * @property UserSchools[] $userSchools
 * @property SchoolSubjects[] $schoolSubjects
 */
class
Schools extends Model
{

    use HasFactory;

    /**
     * @var array
     */
    protected $fillable = ['principal_id', 'user_id', 'country_id', 'school_name', 'address', 'locality', 'post_town', 'post_code', 'image', 'email', 'phone', 'website', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function announcements()
    {
        return $this->hasMany('App\Models\Announcement');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classes()
    {
        return $this->hasMany('App\Models\ClassSchool');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mediums()
    {
        return $this->hasMany('App\Models\Mediums');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function principal()
    {
        return $this->belongsTo('App\Models\Principal','principal_id','user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sections()
    {
        return $this->hasMany('App\Models\Section');
    }

    public function country()
    {

        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userSchools()
    {
        return $this->hasMany('App\Models\UserSchools','school_id');
    }

    public function school(){
        return $this->hasMany(User::class, 'user_id');
    }
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schoolSubjects()
    {
        return $this->hasMany('App\Models\SchoolSubjects');
    }
}
