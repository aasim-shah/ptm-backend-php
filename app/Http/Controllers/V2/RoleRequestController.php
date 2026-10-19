<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Services\RoleContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin panel page for role requests (Part A7): approve, reject, suspend, reactivate,
 * end, and per-context permissions. Super Admin sees everything; a Principal sees the
 * contexts of the schools they administer.
 */
class RoleRequestController extends Controller
{
    private const ACTIONS = [
        'approve' => [['pending', 'rejected'], 'active'],
        'reject' => [['pending'], 'rejected'],
        'suspend' => [['active'], 'suspended'],
        'reactivate' => [['suspended'], 'active'],
    ];

    public function __construct(private RoleContextService $contexts)
    {
    }

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $query = $this->contexts->administrableQuery($request->user())
            ->with('user:id,first_name,last_name,email,type,title')
            ->orderBy('requested_at', 'DESC');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $contexts = $query->paginate(20)->withQueryString();
        foreach ($contexts as $context) {
            $context->organization_summary = $this->contexts->organizationSummary($context->organization_type, $context->organization_id);
            $context->effective_permissions = $this->contexts->effectivePermissions($context);
        }

        return view('role_requests.index', [
            'contexts' => $contexts,
            'status' => $status,
            'permissions' => DB::table('context_permissions')->orderBy('key')->get(),
        ]);
    }

    public function action(Request $request, $id, string $action)
    {
        $context = $this->authorizedContext($request, $id);
        if ($action === 'end') {
            $this->contexts->end($request->user(), $context);
            return back()->with('success', 'Access ended.');
        }
        if (!isset(self::ACTIONS[$action])) {
            abort(404);
        }
        [$from, $to] = self::ACTIONS[$action];
        if (!in_array($context->status, $from, true)) {
            return back()->withErrors(['message' => "Can't {$action} a {$context->status} request."]);
        }
        $this->contexts->decide($request->user(), $context, $to);
        return back()->with('success', 'Request updated.');
    }

    public function permissions(Request $request, $id)
    {
        $context = $this->authorizedContext($request, $id);
        $selected = array_keys($request->input('permissions', []));
        $all = DB::table('context_permissions')->pluck('key');
        $this->contexts->setPermissions($request->user(), $context,
            $all->mapWithKeys(fn ($key) => [$key => in_array($key, $selected, true)])->all());
        return back()->with('success', 'Permissions saved.');
    }

    private function authorizedContext(Request $request, $id): UserRole
    {
        $context = UserRole::findOrFail($id);
        abort_unless($this->contexts->canAdminister($request->user(), $context), 403);
        return $context;
    }
}
