<?php
namespace App\Http\Middleware;

use App\Services\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;

class AdministrativeWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $contexts = app(WorkspaceAccess::class)->webContexts($request->user());
        abort_unless($contexts, 403, 'Use the mobile app. Web access is limited to administrators and explicitly authorized staff.');
        $id = $request->input('context', $request->session()->get('workspace_context'));
        abort_if($id !== null && !is_string($id), 422, 'Invalid workspace context.');
        if ($id !== null) { abort_unless(collect($contexts)->contains('id', $id), 403, 'This administrative workspace is unavailable.'); }
        else { $request->merge(['context' => $contexts[0]['id']]); }
        return $next($request);
    }
}
