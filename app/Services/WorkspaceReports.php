<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Report definitions for Reports & Exports. Every report is limited to the context's schools. */
class WorkspaceReports
{
    public const MAX_ROWS = 5000;
    public const EXPORT_MAX_ROWS = 50000;

    private int $limit = self::MAX_ROWS;

    public function __construct(private WorkspaceAccess $access, private WorkspaceAnalytics $analytics) {}

    /** Report types this context may generate, keyed by type. */
    public function available(array $context): array
    {
        if (!$this->access->can($context, 'reports.export')) { return []; }
        $types = [];
        if ($this->access->can($context, 'memberships.manage')) {
            $types['people'] = ['title' => 'People directory', 'description' => 'Parents, teachers, staff and administrators with their school and status.'];
        }
        if ($this->access->can($context, 'students.view')) {
            $types['students'] = ['title' => 'Students roster', 'description' => 'Students with their school, class, enrollment and verified guardians.'];
        }
        $types['schools'] = ['title' => 'Schools summary', 'description' => 'Members, students and classes for each school.'];
        if (in_array($context['role'], ['super_admin', 'sub_super_admin', 'district_admin'], true)) {
            $types['districts'] = ['title' => 'Districts summary', 'description' => 'Schools and members for each district.'];
        }
        $types['activity'] = ['title' => 'Engagement & activity', 'description' => 'New members, messages, meetings, announcements, events and updates per school.'];
        $types['video_usage'] = ['title' => 'Video usage & minutes', 'description' => 'Calls and minutes used by each connected account.'];
        if (WorkspaceAccess::platformWide($context)) {
            $types['subscriptions'] = ['title' => 'Subscriptions & billing', 'description' => 'Plan, status, activation and expiry for each subscription.'];
        }
        if ($this->access->can($context, 'audit.view')) {
            $types['audit'] = ['title' => 'Activity log', 'description' => 'Administrative actions and sign-ins with who did them and when.'];
        }
        return $types;
    }

    /** @return array{title: string, columns: array, rows: array, truncated: bool} */
    public function build(string $type, array $context, CarbonImmutable $from, CarbonImmutable $to, ?int $school = null, ?string $role = null, int $limit = self::MAX_ROWS): array
    {
        $this->limit = $limit;
        $types = $this->available($context);
        abort_unless(isset($types[$type]), 403);
        $schools = $school ? [$school] : array_keys($context['schools']);
        [$columns, $rows] = match ($type) {
            'people' => $this->people($schools, $role, $school === null),
            'students' => $this->students($context, $schools),
            'audit' => $this->audit($context, $schools, $from, $to, $school === null),
            'schools' => $this->schools($schools),
            'districts' => $this->districts($context, $schools),
            'activity' => $this->activity($schools, $from, $to),
            'video_usage' => $this->videoUsage($context, $schools, $from, $to),
            'subscriptions' => $this->subscriptions($from, $to),
        };
        $truncated = count($rows) > $this->limit;
        return ['title' => $types[$type]['title'], 'columns' => $columns, 'rows' => array_slice($rows, 0, $this->limit), 'limit' => $this->limit, 'truncated' => $truncated];
    }

    /** Prevent spreadsheet formulas from being evaluated when an export is opened. */
    public static function safe($value)
    {
        return is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    private function people(array $schools, ?string $role, bool $includeDistricts = true): array
    {
        // District Admins hold a district membership (no school), so match them through their district's schools.
        $districts = $includeDistricts ? DB::table('workspace_district_schools')->whereIn('school_id', $schools)->pluck('district_id')->unique()->all() : [];
        $rows = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->leftJoin('schools as s', 's.id', '=', 'm.school_id')
            ->leftJoin('workspace_districts as d', 'd.id', '=', 'm.district_id')
            ->where(fn ($q) => $q->whereIn('m.school_id', $schools)->orWhere(fn ($q) => $q->whereNull('m.school_id')->whereIn('m.district_id', $districts)))
            ->whereNull('u.deleted_at')->when($role, fn ($q) => $q->where('m.role', $role))
            ->orderBy('s.school_name')->orderBy('u.last_name')->limit($this->limit + 1)
            ->get(['u.first_name', 'u.last_name', 'u.email', 'm.role', 's.school_name', 'd.name as district_name', 'm.status', 'u.status as account_status', 'u.email_verified_at', 'm.created_at']);
        return [['Name', 'Email', 'Role', 'School / district', 'Membership', 'Account', 'Joined'], $rows->map(fn ($r) => [
            trim($r->first_name.' '.$r->last_name), $r->email, WorkspaceAccess::LABELS[$r->role] ?? $r->role, $r->school_name ?? $r->district_name, ucfirst($r->status),
            (int) $r->account_status !== 1 ? 'Disabled' : ($r->email_verified_at ? 'Verified' : 'Unverified'), $r->created_at ? substr($r->created_at, 0, 10) : '',
        ])->all()];
    }

    private function students(array $context, array $schools): array
    {
        $verified = DB::table('workspace_guardians')->where('status', 'verified')->whereIn('school_id', $schools)->selectRaw('student_id, COUNT(*) as total')->groupBy('student_id')->pluck('total', 'student_id');
        $rows = DB::table('students as st')->join('users as u', 'u.id', '=', 'st.user_id')->join('class_sections as cs', 'cs.id', '=', 'st.class_section_id')
            ->join('classes as c', 'c.id', '=', 'cs.class_id')->leftJoin('sections as sec', 'sec.id', '=', 'cs.section_id')->leftJoin('schools as s', 's.id', '=', 'c.school_id')
            ->whereIn('c.school_id', $schools)->whereNull('st.deleted_at')->where('u.status', 1)->orderBy('s.school_name')->orderBy('c.name')->orderBy('u.last_name')->limit($this->limit + 1)
            ->get(['st.id', 'st.admission_no', 'st.class_status', 'u.first_name', 'u.last_name', 's.school_name', 'c.name as class_name', 'sec.name as section_name']);
        return [['Student', 'Admission no.', 'School', 'Class', 'Section', 'Enrollment', 'Verified guardians'], $rows->map(fn ($r) => [
            trim($r->first_name.' '.$r->last_name), $r->admission_no, $r->school_name, $r->class_name, $r->section_name,
            \App\Http\Controllers\Workspace\DirectoryController::STUDENT_STATUS[strtoupper($r->class_status ?? 'ACCEPTED')] ?? $r->class_status, (int) ($verified[$r->id] ?? 0),
        ])->all()];
    }

    private function audit(array $context, array $schools, CarbonImmutable $from, CarbonImmutable $to, bool $all): array
    {
        $rows = DB::table('workspace_audit as a')->leftJoin('schools as s', 's.id', '=', 'a.school_id')->whereBetween('a.created_at', [$from, $to])
            ->when(!(WorkspaceAccess::platformWide($context) && $all), fn ($q) => $q->whereIn('a.school_id', $schools))
            ->orderByDesc('a.id')->limit($this->limit + 1)->get(['a.*', 's.school_name']);
        $rows = app(AuditPresenter::class)->present($rows);
        return [['When (UTC)', 'Action', 'Done by', 'About', 'School', 'Code'], $rows->map(fn ($r) => [
            $r->created_at, $r->action_label, $r->actor_name, $r->subject_label, $r->school_name ?? 'Platform', $r->action,
        ])->all()];
    }

    private function memberCounts(array $schools)
    {
        return DB::table('workspace_memberships')->whereIn('school_id', $schools)->where('status', 'active')
            ->selectRaw('school_id, role, COUNT(DISTINCT user_id) as total')->groupBy('school_id', 'role')->get()->groupBy('school_id');
    }

    private function schools(array $schools): array
    {
        $members = $this->memberCounts($schools);
        $students = DB::table('students')->join('class_sections as cs', 'cs.id', '=', 'students.class_section_id')->join('classes as c', 'c.id', '=', 'cs.class_id')
            ->whereIn('c.school_id', $schools)->whereNull('students.deleted_at')->selectRaw('c.school_id, COUNT(*) as total')->groupBy('c.school_id')->pluck('total', 'c.school_id');
        $classes = DB::table('classes')->whereIn('school_id', $schools)->whereNull('deleted_at')->selectRaw('school_id, COUNT(*) as total')->groupBy('school_id')->pluck('total', 'school_id');
        $count = fn ($id, $role) => (int) optional(($members[$id] ?? collect())->firstWhere('role', $role))->total;
        $rows = DB::table('schools')->whereIn('id', $schools)->orderBy('school_name')->get(['id', 'school_name', 'post_code'])->map(fn ($s) => [
            $s->id, $s->school_name, $s->post_code, $count($s->id, 'parent'), $count($s->id, 'teacher'), $count($s->id, 'staff'),
            (int) ($students[$s->id] ?? 0), (int) ($classes[$s->id] ?? 0),
        ])->all();
        return [['School ID', 'School', 'Postcode / ZIP', 'Parents', 'Teachers', 'Staff', 'Students', 'Classes'], $rows];
    }

    private function districts(array $context, array $schools): array
    {
        $links = DB::table('workspace_district_schools')->whereIn('school_id', $schools)->get()->groupBy('district_id');
        $districts = DB::table('workspace_districts')->when(!WorkspaceAccess::platformWide($context), fn ($q) => $q->whereIn('id', $links->keys()))->orderBy('name')->get();
        $rows = $districts->map(function ($d) use ($links) {
            $ids = ($links[$d->id] ?? collect())->pluck('school_id')->all();
            $members = DB::table('workspace_memberships')->whereIn('school_id', $ids)->where('status', 'active')->distinct()->count('user_id');
            return [$d->id, $d->name, $d->market, count($ids), $members];
        })->all();
        return [['District ID', 'District', 'Market', 'Schools', 'Active members'], $rows];
    }

    private function activity(array $schools, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from, $to];
        $per = fn ($q, string $column = 'created_at') => $q->whereIn('school_id', $schools)->whereBetween($column, $range)
            ->selectRaw('school_id, COUNT(*) as total')->groupBy('school_id')->pluck('total', 'school_id');
        $columns = [
            'members' => $per(DB::table('workspace_memberships')),
            'messages' => $per(DB::table('workspace_messages')),
            'meetings' => $per(DB::table('workspace_appointments')),
            'announcements' => $per(DB::table('workspace_posts')->where('kind', 'notice')),
            'events' => $per(DB::table('workspace_posts')->where('kind', 'event')),
            'updates' => $per(DB::table('workspace_updates')->where('status', 'published'), 'published_at'),
        ];
        $rows = DB::table('schools')->whereIn('id', $schools)->orderBy('school_name')->get(['id', 'school_name'])
            ->map(fn ($s) => array_merge([$s->school_name], array_map(fn ($c) => (int) ($c[$s->id] ?? 0), array_values($columns))))->all();
        return [['School', 'New members', 'Messages', 'Meeting requests', 'Announcements', 'Events', 'Student updates published'], $rows];
    }

    private function videoUsage(array $context, array $schools, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $users = DB::table('workspace_memberships')->whereIn('school_id', $schools)->select('user_id');
        $rows = DB::table('call_usage_ledger as l')->join('users as u', 'u.id', '=', 'l.user_id')->whereBetween('l.created_at', [$from, $to])
            ->when(!WorkspaceAccess::platformWide($context) || count($schools) !== count($context['schools']), fn ($q) => $q->whereIn('l.user_id', $users))
            ->selectRaw('u.first_name, u.last_name, u.email, COUNT(DISTINCT COALESCE(l.user_call_room_id, l.channel_name)) as calls, SUM(l.seconds) as seconds, MAX(l.created_at) as last_call')
            ->groupBy('l.user_id', 'u.first_name', 'u.last_name', 'u.email')->orderByDesc('seconds')->limit($this->limit + 1)->get()
            ->map(fn ($r) => [trim($r->first_name.' '.$r->last_name), $r->email, (int) $r->calls, round($r->seconds / 60, 1), substr($r->last_call, 0, 16)])->all();
        return [['Name', 'Email', 'Calls', 'Minutes', 'Last call (UTC)'], $rows];
    }

    private function subscriptions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('user_subscription as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->whereBetween('s.created_at', [$from, $to])
            ->orderByDesc('s.created_at')->limit($this->limit + 1)
            ->get(['u.first_name', 'u.last_name', 's.email', 's.plan_id', 's.type', 's.status', 's.activated_date', 's.expire_date'])
            ->map(fn ($r) => [trim($r->first_name.' '.$r->last_name), $r->email, $r->plan_id, $r->type, $r->status, $r->activated_date, $r->expire_date])->all();
        return [['Name', 'Email', 'Plan', 'Type', 'Status', 'Activated', 'Expires'], $rows];
    }
}
