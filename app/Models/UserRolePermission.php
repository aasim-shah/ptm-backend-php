<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-context change to the role's default permissions (granted = add, false = remove).
 */
class UserRolePermission extends Model
{
    protected $fillable = ['user_role_id', 'permission_key', 'granted'];

    protected $casts = ['granted' => 'boolean'];
}
