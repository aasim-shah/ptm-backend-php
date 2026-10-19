<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One role context: a user holding a role in an organization (a school or a district).
 * Its id is the context id the app sends back in X-PTM-Context.
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $role
 * @property string $organization_type
 * @property integer $organization_id
 * @property string $status
 * @property string $source
 */
class UserRole extends Model
{
    public const ROLES = ['parent', 'teacher', 'staff', 'school_admin', 'district_admin'];
    public const INSTITUTION_ROLES = ['teacher', 'staff', 'school_admin', 'district_admin'];
    public const REQUESTABLE_ROLES = ['staff', 'school_admin', 'district_admin'];
    public const STATUSES = ['pending', 'active', 'rejected', 'suspended'];

    protected $fillable = [
        'user_id', 'role', 'organization_type', 'organization_id', 'status', 'source',
        'requested_at', 'decided_at', 'decided_by', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permissionOverrides()
    {
        return $this->hasMany(UserRolePermission::class, 'user_role_id');
    }

    /** Contexts whose relationship has not ended. */
    public function scopeCurrent($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
        });
    }

    public function hasEnded(): bool
    {
        return $this->effective_to !== null && $this->effective_to->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && !$this->hasEnded();
    }

    public function isSchool(): bool
    {
        return $this->organization_type === 'school';
    }

    public function isDistrict(): bool
    {
        return $this->organization_type === 'district';
    }

    public function organization()
    {
        return $this->isDistrict()
            ? District::find($this->organization_id)
            : Schools::find($this->organization_id);
    }
}
