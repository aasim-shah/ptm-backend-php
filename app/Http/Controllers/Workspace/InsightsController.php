<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceAnalytics;
use App\Services\WorkspaceReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Analytics & Insights, Reports & Exports, Video Calls, Billing and Audit pages for the four administrative panels. */
class InsightsController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private WorkspaceAnalytics $analytics, private WorkspaceReports $reports) {}

    private function context(Request $request): array
    {
        $context = $this->access->resolve($request->user(), $request->input('context', $request->session()->get('workspace_context')));
        abort_unless(in_array($context['role'], WorkspaceAnalytics::ADMIN_ROLES, true), 403);
        return $context;
    }

    private function window(Request $request, int $defaultDays = 180): array
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'grain' => ['nullable', Rule::in(array_keys(WorkspaceAnalytics::GRAINS))]]);
        return $this->analytics->window($request->from, $request->to, $request->grain, $defaultDays);
    }

    private function page(Request $request, array $context, string $page, array $data)
    {
        return view('workspace.'.$page, $data + ['context' => $context, 'contexts' => $this->access->webContexts($request->user()),
            'access' => $this->access, 'page' => $page]);
    }

    public function analytics(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'analytics.view'), 403);
        [$from, $to, $grain] = $this->window($request);
        $series = $this->analytics->series($context, $from, $to, $grain);
        $totals = $this->analytics->totals($context, $from, $to);
        $students = $this->access->students($request->user(), $context)->count();
        $kpis = $this->analytics->kpis($context, $students);
        if ($request->expectsJson()) { return response()->json(compact('series', 'totals', 'kpis') + ['as_of' => now()->toIso8601String()]); }
        return $this->page($request, $context, 'analytics', compact('series', 'totals', 'kpis', 'from', 'to', 'grain'));
    }

    public function video(Request $request)
    {
        $context = $this->context($request);
        [$from, $to] = $this->window($request, 30);
        $calls = DB::table('call_usage_ledger as l')->join('users as u', 'u.id', '=', 'l.user_id')->whereBetween('l.created_at', [$from, $to])
            ->when($context['role'] !== 'super_admin', fn ($q) => $q->whereIn('l.user_id', $this->analytics->scopedUsers($context)))
            ->select('l.id', 'l.seconds', 'l.source', 'l.created_at', 'u.first_name', 'u.last_name')->orderByDesc('l.id')->paginate(20)->withQueryString();
        $meetings = DB::table('workspace_appointments')->whereIn('school_id', array_keys($context['schools']))->where('starts_at', '>=', now())
            ->whereIn('status', ['pending', 'accepted'])->orderBy('starts_at')->limit(8)->get();
        return $this->page($request, $context, 'video', ['totals' => $this->analytics->totals($context, $from, $to), 'calls' => $calls,
            'meetings' => $meetings, 'from' => $from, 'to' => $to]);
    }

    private function reportInput(Request $request, array $context): array
    {
        $types = $this->reports->available($context);
        $request->validate(['type' => ['nullable', Rule::in(array_keys($types))], 'school_id' => ['nullable', 'integer', Rule::in(array_keys($context['schools']))],
            'role' => 'nullable|in:parent,teacher,staff,school_admin,district_admin,sub_super_admin', 'format' => 'nullable|in:csv,xlsx,pdf']);
        return [$types, $request->type ?? array_key_first($types)];
    }

    public function reports(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'reports.export'), 403);
        [$types, $type] = $this->reportInput($request, $context);
        [$from, $to] = $this->window($request, 90);
        $report = $this->reports->build($type, $context, $from, $to, $request->integer('school_id') ?: null, $request->role);
        $exports = DB::table('workspace_exports')->where('user_id', $request->user()->id)->where('context', $context['id'])
            ->where('created_at', '>', now()->subDays(7))->orderByDesc('id')->limit(10)->get();
        return $this->page($request, $context, 'reports', compact('types', 'type', 'report', 'from', 'to', 'exports'));
    }

    public function requestExport(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'reports.export'), 403);
        [, $type] = $this->reportInput($request, $context);
        abort_unless($type, 403);
        [$from, $to] = $this->window($request, 90);
        $format = $request->format ?? 'csv';
        abort_if(DB::table('workspace_exports')->where('user_id', $request->user()->id)->where('status', 'queued')->count() >= 5, 429, 'Wait for your queued exports to finish.');
        $id = DB::transaction(function () use ($request, $context, $type, $format, $from, $to) {
            $id = DB::table('workspace_exports')->insertGetId(['user_id' => $request->user()->id, 'context' => $context['id'], 'type' => $type, 'format' => $format,
                'filters' => json_encode(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'school_id' => $request->integer('school_id') ?: null, 'role' => $request->role]),
                'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $request->integer('school_id') ?: (count($context['schools']) === 1 ? array_key_first($context['schools']) : null),
                'report.'.$type.'.exported', $context['id'].':'.$format.':'.$id);
            return $id;
        });
        \App\Jobs\GenerateWorkspaceExport::dispatch($id);
        $message = 'Your '.strtoupper($format).' export is being prepared. It appears under Your exports when ready and is kept for '.\App\Jobs\GenerateWorkspaceExport::LIFETIME_HOURS.' hours.';
        return $request->expectsJson() ? response()->json(['message' => $message, 'id' => $id], 202) : back()->with('workspace_success', $message);
    }

    public function exportFile(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'reports.export'), 403);
        $export = DB::table('workspace_exports')->where('id', $id)->where('user_id', $request->user()->id)->where('context', $context['id'])->first();
        abort_unless($export && $export->status === 'ready' && $export->expires_at > now()->toDateTimeString(), 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('private')->exists($export->path), 404);
        $this->access->audit($request->user(), null, 'report.downloaded', $context['id'].':'.$id);
        $types = ['csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'];
        return \Illuminate\Support\Facades\Storage::disk('private')->download($export->path,
            'ptm-'.str_replace('_', '-', $export->type).'-'.\Carbon\Carbon::parse($export->created_at)->format('Ymd-His').'.'.$export->format,
            ['Content-Type' => $types[$export->format], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }

    public function audit(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'audit.view'), 403);
        $request->validate(['action' => 'nullable|string|max:80']);
        $entries = DB::table('workspace_audit as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->leftJoin('schools as s', 's.id', '=', 'a.school_id')
            ->when($context['role'] !== 'super_admin', fn ($q) => $q->whereIn('a.school_id', array_keys($context['schools'])))
            ->when($request->action, fn ($q) => $q->where('a.action', 'like', str_replace(['%', '_'], ['\%', '\_'], $request->action).'%'))
            ->select('a.*', 'u.first_name', 'u.last_name', 's.school_name')->orderByDesc('a.id')->paginate(30)->withQueryString();
        return $this->page($request, $context, 'audit', ['entries' => $entries]);
    }

    public function billing(Request $request)
    {
        $context = $this->context($request);
        abort_unless($context['role'] === 'super_admin', 403);
        $base = DB::table('user_subscription');
        $summary = [
            ['label' => 'Active subscriptions', 'value' => (clone $base)->where('status', 'ACTIVE')->where('expire_date', '>=', now()->toDateString())->count(), 'icon' => 'check-circle'],
            ['label' => 'Active trials', 'value' => (clone $base)->where('status', 'ACTIVE')->where('type', \App\Models\Plan::TRAIL)->where('expire_date', '>=', now()->toDateString())->count(), 'icon' => 'clock-o'],
            ['label' => 'Expiring in 30 days', 'value' => (clone $base)->where('status', 'ACTIVE')->whereBetween('expire_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(), 'icon' => 'hourglass-half'],
            ['label' => 'Expired or cancelled', 'value' => (clone $base)->where(fn ($q) => $q->where('status', '!=', 'ACTIVE')->orWhere('expire_date', '<', now()->toDateString()))->count(), 'icon' => 'times-circle'],
        ];
        $subscriptions = DB::table('user_subscription as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->select('s.*', 'u.first_name', 'u.last_name')->orderByDesc('s.created_at')->paginate(25)->withQueryString();
        return $this->page($request, $context, 'billing', compact('summary', 'subscriptions'));
    }
}
