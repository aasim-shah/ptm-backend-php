<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserRole;
use App\Services\RoleContextService;

/**
 * The LoginResponse shape (Part A4) returned by login, register, details, context switch
 * and update-remaining-time. Always carries has_school, active_context_id and contexts.
 */
class LoginResponse
{
    public static function make(User $user, string $message, array $extra = [], ?UserRole $active = null, int $status = 200)
    {
        $fields = app(RoleContextService::class)->loginFields($user, $active ?? Access::context());
        $user->has_schools = $fields['has_school'];

        return response()->json(array_merge(
            ['status' => $status, 'message' => $message],
            $extra,
            ['has_school' => $fields['has_school'], 'user' => $user],
            ['active_context_id' => $fields['active_context_id'], 'contexts' => $fields['contexts']]
        ), $status);
    }
}
