<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A school district: an organization that groups schools (schools.district_id).
 *
 * @property integer $id
 * @property string $name
 * @property integer $country_id
 * @property string $status
 */
class District extends Model
{
    protected $fillable = ['name', 'country_id', 'status'];

    public function schools()
    {
        return $this->hasMany(Schools::class, 'district_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * The district in the School object shape the app parses (GET /get/schools?organization_type=district).
     */
    public function toSchoolShape(): array
    {
        return [
            'id' => $this->id,
            'organization_type' => 'district',
            'school_name' => $this->name,
            'country_id' => $this->country_id,
            'district_id' => null,
            'principal_id' => null,
            'user_id' => null,
            'address' => null,
            'locality' => null,
            'post_town' => null,
            'post_code' => null,
            'image' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'country' => $this->country,
        ];
    }
}
