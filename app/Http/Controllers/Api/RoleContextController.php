<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Schools;
use App\Models\User;
use App\Services\RoleContextService;
use App\Support\LoginResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile endpoints for role contexts (Part A5.4 and A5.5).
 */
class RoleContextController extends Controller
{
    public function __construct(private RoleContextService $contexts)
    {
    }

    /**
     * POST /auth/role-requests
     * Staff, School Admin and District Admin ask an organization for access.
     */
    public function requestRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|integer',
            'organization_type' => 'required|in:school,district',
            'role' => 'required|in:staff,school_admin,district_admin',
        ], [
            'role.in' => 'Parents and teachers join a school by picking it, not with a role request.',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $role = $request->input('role');
        $type = $request->input('organization_type');
        if (($role === 'district_admin') !== ($type === 'district')) {
            return $this->error($role === 'district_admin'
                ? 'District admin access must be requested from a school district.'
                : 'Staff and school admin access must be requested from a school.', 422);
        }

        $organization = $type === 'district'
            ? District::find($request->input('organization_id'))
            : Schools::find($request->input('organization_id'));
        if (!$organization || ($organization->status ?? 'active') !== 'active') {
            return $this->error('This organization is not available.', 404);
        }

        /** @var User $user */
        $user = $request->user();
        if ($user->country_id && (int) $organization->country_id !== (int) $user->country_id) {
            return $this->error('This organization is outside your country.', 422);
        }

        [$context, $created] = $this->contexts->requestRole($user, $role, $type, (int) $organization->id);

        return response()->json([
            'status' => 200,
            'message' => $created ? 'Request sent' : 'Request already exists',
            'data' => ['id' => $context->id, 'status' => $context->status],
        ], 200);
    }

    /**
     * POST /auth/context/switch
     * Makes one of the caller's active contexts the session's active context.
     */
    public function switchContext(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $context = $this->contexts->find($user, $request->input('context_id'));
        if (!$context) {
            return $this->error('This role context does not belong to your account.', 403);
        }
        if (!$context->isUsable()) {
            return $this->error("This role is {$context->status}. You can't switch to it yet.", 403);
        }

        $this->contexts->recordSwitch($user, $context);
        $request->attributes->set(RoleContextService::REQUEST_ATTRIBUTE, $context);

        return LoginResponse::make($user->refresh(), 'Role switched', ['token_type' => 'Bearer'], $context);
    }

    private function error(string $message, int $code)
    {
        return response()->json(['error' => true, 'message' => $message, 'code' => $code], $code);
    }
}
