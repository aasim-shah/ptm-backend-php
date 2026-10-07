<?php

namespace App\Models;

use App\Helpers\Utility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Announcement extends Model
{
    use SoftDeletes;

    protected $hidden = ["deleted_at", "updated_at"];
    protected $appends = ['created_at_for_web'];

    public function table() {
        return $this->morphTo()->withTrashed();
    }

    public function file() {
        return $this->morphMany(File::class, 'modal');
    }

    public function school() {
        return $this->belongsTo(Schools::class, 'school_id');
    }

    public function getCreatedAtForWebAttribute(){
        return Utility::convertDateWithTimeZone($this->attributes['created_at'],Auth::user()->timezone_offset);
    }

}
