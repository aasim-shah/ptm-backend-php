<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property integer $school_id
 * @property string $name
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property School $school
 */
class Section extends Model
{
    use SoftDeletes;
    use HasFactory;
    /**
     * @var array
     */
    protected $fillable = ['school_id', 'name', 'created_at', 'updated_at', 'deleted_at'];
    protected $hidden = ["deleted_at","created_at","updated_at"];
    const default_sections = [
        'Primary section',
        'Secondary section'
    ];
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\Schools');
    }
    public function classes() {
        return $this->belongsToMany(ClassSchool::class, 'class_sections', 'section_id', 'class_id');
    }
}
