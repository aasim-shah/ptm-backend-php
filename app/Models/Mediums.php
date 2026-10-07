<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Mediums extends Model
{
    protected $hidden = ["deleted_at", "created_at", "updated_at"];
    use SoftDeletes;
    use HasFactory;
}
