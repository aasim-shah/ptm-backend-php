<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Services\RoleContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Web Panel API for role requests and contexts (Part A7). The mobile app does not call it.
 *
 * Who may act on what is decided by RoleContextService::canAdminister:
 * school admins handle their school's staff/teachers/parents, district admins their
 * district's schools (including school admins), Super Admin everything.
 */
class RoleRequestAdminController extends Controller
{
    public function __construct(private RoleContextService $contexts)
    {
    }

    /** GET /admin/role-requests?status=pending (default) | active | suspended | rejected | all */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $query = $this->contexts->administrableQuery($request->user())
            ->with('user:id,first_name,last_name,email,type,title')
            ->orderBy('requested_at', 'DESC');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $page = $query->paginate(20);
        $page->setCollection($page->getCollection()->map(fn (UserRole $context) => $this->present($context)));

        return response()->json(['error' => false, 'message' => 'Role requests fetched', 'data' => $page, 'code' => 200]);
    }

    /** POST /admin/role-requests/{id}/{action}  action = approve | reject | suspend | reactivate | end */
    public function decide(Request $request, $id, string $action)
    {
        $context = $this->findAdministrable($request, $id);
        if (!$context instanceof UserRole) {
            return $context;
        }

        $allowed = [
            'approve' => ['pending', 'rejected'],
            'reject' => ['pending'],
            'suspend' => ['active'],
            'reactivate' => ['suspended'],
        ];
        if ($action === 'end') {
            $this->contexts->end($request->user(), $context);
        } elseif (isset($allowed[$action])) {
            if (!in_array($context->status, $allowed[$action], true)) {
                return $this->error("Can't {$action} a {$context->status} context.", 422);
            }
            $status = ['approve' => 'active', 'reject' => 'rejected', 'suspend' => 'suspended', 'reactivate' => 'active'][$action];
            $this->contexts->decide($request->user(), $context, $status);
        } else {
            return $this->error('Unknown action.', 404);
        }

        return response()->json(['error' => false, 'message' => 'Context updated', 'data' => $this->present($context->refresh()), 'code' => 200]);
    }

    /**
     * PUT /admin/role-requests/{id}/permissions
     * Body: { "permissions": { "messages.use": true, "schedule.use": false, ... } }
     */
    public function permissions(Request $request, $id)
    {
        $context = $this->findAdministrable($request, $id);
        if (!$context instanceof UserRole) {
            return $context;
        }
        $validator = Validator::make($request->all(), ['permissions' => 'required|array']);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $this->contexts->setPermissions($request->user(), $context, $request->input('permissions'));
        return response()->json(['error' => false, 'message' => 'Permissions updated', 'data' => $this->present($context), 'code' => 200]);
    }

    /** GET /admin/permissions: the keys and each role's defaults, for the edit form. */
    public function permissionCatalog()
    {
        return response()->json([
            'error' => false,
            'message' => 'Permissions fetched',
            'data' => [
                'permissions' => DB::table('context_permissions')->orderBy('key')->get(),
                'defaults' => DB::table('context_role_permissions')->get()->groupBy('role')
                    ->map(fn ($rows) => $rows->pluck('permission_key')->values()),
            ],
            'code' => 200,
        ]);
    }

    public function present(UserRole $context): array
    {
        return array_merge($this->contexts->serialize($context), [
            'user' => $context->user,
            'source' => $context->source,
            'requested_at' => $context->requested_at,
            'decided_at' => $context->decided_at,
            'decided_by' => $context->decided_by,
            'effective_from' => $context->effective_from,
            'effective_to' => $context->effective_to,
            'overrides' => $context->permissionOverrides()->get(['permission_key', 'granted']),
        ]);
    }

    private function findAdministrable(Request $request, $id)
    {
        $context = UserRole::find($id);
        if (!$context) {
            return $this->error('Context not found.', 404);
        }
        if (!$this->contexts->canAdminister($request->user(), $context)) {
            return $this->error(trans('no_permission_message'), 403);
        }
        return $context;
    }

    private function error(string $message, int $code)
    {
        return response()->json(['error' => true, 'message' => $message, 'code' => $code], $code);
    }
}
