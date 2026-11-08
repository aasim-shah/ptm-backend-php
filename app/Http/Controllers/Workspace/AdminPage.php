<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Shared plumbing for the administrative panel pages: context, rendering and responses. */
abstract class AdminPage extends Controller
{
    public function __construct(protected WorkspaceAccess $access) {}

    /** The current administrative context; every request re-resolves it from the database. */
    protected function context(Request $request, array $roles = WorkspaceAnalytics::ADMIN_ROLES): array
    {
        abort_unless((int) $request->user()->status && $request->user()->email_verified_at, 403, 'An active, verified account is required.');
        $id = $request->input('context', $request->hasSession() ? $request->session()->get('workspace_context') : null);
        abort_if($id !== null && !is_string($id), 422, 'Invalid workspace context.');
        $context = $this->access->resolve($request->user(), $id);
        abort_unless(in_array($context['role'], $roles, true), 403);
        return $context;
    }

    protected function platformOnly(array $context): void
    {
        abort_unless(WorkspaceAccess::platformWide($context), 403, 'Only the Super Admin can do this from the full-platform view.');
    }

    protected function render(Request $request, array $context, string $page, string $view, array $data = [])
    {
        $timezone = DB::table('workspace_profiles')->where('user_id', $request->user()->id)->value('timezone') ?? 'UTC';
        return view($view, $data + ['context' => $context, 'contexts' => $this->access->webContexts($request->user()),
            'access' => $this->access, 'page' => $page, 'timezone' => $timezone,
            'formatTime' => fn ($date, $format = 'j M Y · H:i') => $date ? \Carbon\Carbon::parse($date, 'UTC')->timezone($timezone)->format($format) : '—']);
    }

    protected function done(Request $request, string $message, ?string $to = null)
    {
        if ($request->expectsJson()) { return response()->json(['message' => $message]); }
        return ($to ? redirect()->to($to) : back())->with('workspace_success', $message);
    }

    /** Escapes LIKE wildcards in a user's search term. */
    protected function like(?string $term): ?string
    {
        $term = trim((string) $term);
        return $term === '' ? null : '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }

    protected function url(array $context, string $route, array $query = [], array $params = []): string
    {
        return route($route, $params + ['context' => $context['id']] + $query);
    }
}
