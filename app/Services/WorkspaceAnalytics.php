<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Aggregate figures for the administrative dashboards, always limited to the context's schools. */
class WorkspaceAnalytics
{
    public const ADMIN_ROLES = ['super_admin', 'sub_super_admin', 'district_admin', 'school_admin'];
    public const GRAINS = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'quarter' => 'Quarterly'];

    public function __construct(private WorkspaceAccess $access) {}

    private function schools(array $context): array
    {
        return array_keys($context['schools']);
    }

    private function platform(array $context): bool
    {
        return $context['role'] === 'super_admin';
    }

    private function active($q, string $alias = 'm')
    {
        return $q->where($alias.'.status', 'active')
            ->where(fn ($q) => $q->whereNull($alias.'.starts_at')->orWhere($alias.'.starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull($alias.'.ends_at')->orWhere($alias.'.ends_at', '>', now()));
    }

    /** User ids connected to the context's schools; the platform context covers every account. */
    public function scopedUsers(array $context)
    {
        return DB::table('workspace_memberships')->whereIn('school_id', $this->schools($context))->select('user_id');
    }

    public function districtCount(array $context): int
    {
        if ($this->platform($context)) { return DB::table('workspace_districts')->count(); }
        return DB::table('workspace_district_schools')->whereIn('school_id', $this->schools($context))->distinct()->count('district_id');
    }

    public function members(array $context, string $role): int
    {
        return $this->active(DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id'))
            ->whereIn('m.school_id', $this->schools($context))->where('m.role', $role)
            ->where('u.status', 1)->whereNull('u.deleted_at')->whereNotNull('u.email_verified_at')
            ->distinct()->count('m.user_id');
    }

    /** Six primary KPI cards: four shared people counts, then two that suit the panel's scope. */
    public function kpis(array $context, int $students): array
    {
        $cards = [
            ['key' => 'parents', 'label' => 'Parents / Guardians', 'value' => $this->members($context, 'parent'), 'icon' => 'users'],
            ['key' => 'teachers', 'label' => 'Teachers', 'value' => $this->members($context, 'teacher'), 'icon' => 'user'],
            ['key' => 'staff', 'label' => 'Staff', 'value' => $this->members($context, 'staff'), 'icon' => 'id-badge'],
            ['key' => 'students', 'label' => 'Students', 'value' => $students, 'icon' => 'graduation-cap'],
        ];
        $schools = ['key' => 'schools', 'label' => 'Schools', 'value' => count($context['schools']), 'icon' => 'university'];
        $classes = ['key' => 'classes', 'label' => 'Classes', 'value' => $this->classCount($context), 'icon' => 'book'];
        return array_merge($cards, match ($context['role']) {
            'school_admin' => [$classes, ['key' => 'meetings', 'label' => 'Upcoming meetings', 'value' => $this->upcomingMeetings($context), 'icon' => 'calendar']],
            'district_admin' => [$schools, $classes],
            default => [$schools, ['key' => 'districts', 'label' => 'Districts', 'value' => $this->districtCount($context), 'icon' => 'map-o']],
        });
    }

    public function classCount(array $context): int
    {
        return DB::table('classes')->whereIn('school_id', $this->schools($context))->whereNull('deleted_at')->count();
    }

    public function upcomingMeetings(array $context): int
    {
        return DB::table('workspace_appointments')->whereIn('school_id', $this->schools($context))->where('starts_at', '>=', now())->whereIn('status', ['pending', 'accepted'])->count();
    }

    /** Student updates by status over the window: the "report completion" view. */
    public function reportCompletion(array $context, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = DB::table('workspace_updates')->whereIn('school_id', $this->schools($context))->whereBetween('updated_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        return ['published' => (int) ($counts['published'] ?? 0), 'draft' => (int) $counts->except('published')->sum()];
    }

    /** Students and classes per school (top 8 by students); a School Admin sees students per class instead. */
    public function breakdown(array $context): array
    {
        $base = DB::table('classes as c')->leftJoin('class_sections as cs', fn ($j) => $j->on('cs.class_id', '=', 'c.id')->whereNull('cs.deleted_at'))
            ->leftJoin('students as s', 's.class_section_id', '=', 'cs.id')
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 's.user_id')->where('u.status', 1))
            ->whereIn('c.school_id', $this->schools($context))->whereNull('c.deleted_at');
        if ($context['role'] === 'school_admin') {
            $rows = $base->groupBy('c.id', 'c.name')->selectRaw('c.name as label, COUNT(DISTINCT u.id) as students')->orderByDesc('students')->limit(8)->get();
            return ['title' => 'Students by class', 'labels' => $rows->pluck('label')->all(), 'students' => $rows->pluck('students')->map(fn ($v) => (int) $v)->all(), 'classes' => null];
        }
        $rows = $base->groupBy('c.school_id')->selectRaw('c.school_id, COUNT(DISTINCT u.id) as students, COUNT(DISTINCT c.id) as classes')->orderByDesc('students')->limit(8)->get();
        return ['title' => 'Schools & classes', 'labels' => $rows->map(fn ($r) => $context['schools'][$r->school_id] ?? '#'.$r->school_id)->all(),
            'students' => $rows->pluck('students')->map(fn ($v) => (int) $v)->all(), 'classes' => $rows->pluck('classes')->map(fn ($v) => (int) $v)->all()];
    }

    /** Work that needs an administrator's decision, each linked to the page that resolves it. */
    public function attention(array $context, \App\Models\User $user): array
    {
        $schools = $this->schools($context);
        $items = [];
        if ($this->access->can($context, 'memberships.manage')) {
            $items[] = ['icon' => 'user-plus', 'label' => 'Guardian verifications', 'detail' => 'Parents waiting for student approval', 'page' => 'people',
                'count' => DB::table('workspace_guardians')->whereIn('school_id', $schools)->where('status', 'pending')->count()];
            $items[] = ['icon' => 'envelope-o', 'label' => 'Open invitations', 'detail' => 'Sent but not yet accepted', 'page' => 'people',
                'count' => DB::table('workspace_invitations')->whereIn('school_id', $schools)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count()];
            $items[] = ['icon' => 'exclamation-circle', 'label' => 'Account issues', 'detail' => 'Members disabled or unverified', 'page' => 'people',
                'count' => DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->whereIn('m.school_id', $schools)->where('m.status', 'active')
                    ->where(fn ($q) => $q->where('u.status', '!=', 1)->orWhereNull('u.email_verified_at'))->distinct()->count('m.user_id')];
        }
        $adFree = DB::table('workspace_ad_free_requests')->where('status', 'pending');
        if (!$this->platform($context)) { $adFree->whereIn('school_id', $schools); }
        $items[] = ['icon' => 'shield', 'label' => 'Ad-free requests', 'detail' => 'Awaiting Super Admin review', 'page' => 'administration', 'count' => $adFree->count()];
        $expiring = DB::table('user_subscription')->where('status', 'ACTIVE')
            ->whereBetween('expire_date', [now()->toDateString(), now()->addDays(30)->toDateString().' 23:59:59']);
        if (!$this->platform($context)) { $expiring->whereIn('user_id', $this->scopedUsers($context)); }
        $items[] = ['icon' => 'credit-card', 'label' => 'Expiring subscriptions', 'detail' => 'Ending within 30 days', 'page' => $this->platform($context) ? 'billing' : 'reports', 'count' => $expiring->count()];
        $items[] = ['icon' => 'calendar-o', 'label' => 'Meeting requests', 'detail' => 'Waiting for a response', 'page' => 'schedule',
            'count' => DB::table('workspace_appointments')->whereIn('school_id', $schools)->where('status', 'pending')->where('starts_at', '>', now())->count()];
        $items[] = ['icon' => 'video-camera', 'label' => 'Meetings today', 'detail' => 'Accepted for today', 'page' => 'video',
            'count' => DB::table('workspace_appointments')->whereIn('school_id', $schools)->where('status', 'accepted')->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->count()];
        $support = app(WorkspaceSupport::class);
        $items[] = ['icon' => 'life-ring', 'label' => 'Support tickets', 'detail' => $support->handles($context) ? 'Open, waiting for support' : 'Your open tickets', 'page' => 'support',
            'count' => $support->visible($user, $context)->where('t.status', 'open')->count()];
        return $items;
    }

    public function recentSignups(array $context, int $limit = 6)
    {
        return DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('schools as s', 's.id', '=', 'm.school_id')->whereIn('m.school_id', $this->schools($context))->whereNull('u.deleted_at')
            ->select('m.id', 'm.role', 'm.status', 'm.created_at', 'u.first_name', 'u.last_name', 'u.status as account_status', 'u.email_verified_at', 's.school_name')
            ->orderByDesc('m.created_at')->orderByDesc('m.id')->limit($limit)->get();
    }

    /** Validated reporting window: [from, to, grain]. Long daily windows fall back to weekly buckets. */
    public function window(?string $from, ?string $to, ?string $grain, int $defaultDays = 180): array
    {
        $to = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = $from ? CarbonImmutable::parse($from)->startOfDay() : $to->subDays($defaultDays - 1)->startOfDay();
        abort_if($from->gt($to), 422, 'The start date must be before the end date.');
        abort_if($from->diffInDays($to) > 1100, 422, 'Choose a range of three years or less.');
        $grain = isset(self::GRAINS[$grain]) ? $grain : ($from->diffInDays($to) > 120 ? 'month' : 'week');
        if ($grain === 'day' && $from->diffInDays($to) > 92) { $grain = 'week'; }
        return [$from, $to, $grain];
    }

    /** SQL expression that maps a timestamp column to the same label labels() generates. Columns are internal constants. */
    private function bucket(string $column, string $grain): string
    {
        return match ($grain) {
            'day' => "DATE($column)",
            'week' => "DATE(DATE_SUB($column, INTERVAL WEEKDAY($column) DAY))",
            'month' => "DATE_FORMAT($column, '%Y-%m')",
            'quarter' => "CONCAT(YEAR($column), '-Q', QUARTER($column))",
        };
    }

    public function labels(CarbonImmutable $from, CarbonImmutable $to, string $grain): array
    {
        $labels = [];
        $cursor = match ($grain) { 'week' => $from->startOfWeek(Carbon::MONDAY), 'month' => $from->startOfMonth(), 'quarter' => $from->startOfQuarter(), default => $from };
        while ($cursor->lte($to)) {
            $labels[] = match ($grain) { 'day', 'week' => $cursor->toDateString(), 'month' => $cursor->format('Y-m'), 'quarter' => $cursor->year.'-Q'.$cursor->quarter };
            $cursor = match ($grain) { 'day' => $cursor->addDay(), 'week' => $cursor->addWeek(), 'month' => $cursor->addMonth(), 'quarter' => $cursor->addQuarter() };
        }
        return $labels;
    }

    /** Human-readable versions of labels() for chart axes. */
    public function displayLabels(array $labels, string $grain): array
    {
        return array_map(fn ($label) => match ($grain) {
            'day', 'week' => CarbonImmutable::parse($label)->format('j M'),
            'month' => CarbonImmutable::createFromFormat('Y-m-d', $label.'-01')->format('M Y'),
            'quarter' => str_replace('-', ' ', $label),
        }, $labels);
    }

    private function counts($query, string $column, string $grain, array $labels, string $aggregate = 'COUNT(*)'): array
    {
        $rows = $query->selectRaw($this->bucket($column, $grain).' as bucket, '.$aggregate.' as total')->groupBy('bucket')->pluck('total', 'bucket');
        return array_map(fn ($label) => round((float) ($rows[$label] ?? 0), 1), $labels);
    }

    /** Every chart series used by the dashboard and the Analytics & Insights page. */
    public function series(array $context, CarbonImmutable $from, CarbonImmutable $to, string $grain): array
    {
        $schools = $this->schools($context);
        $labels = $this->labels($from, $to, $grain);
        $range = [$from, $to];
        $memberships = fn (?string $role = null) => DB::table('workspace_memberships')->whereIn('school_id', $schools)
            ->whereBetween('created_at', $range)->when($role, fn ($q) => $q->where('role', $role));
        $registrations = $this->platform($context)
            ? $this->counts(DB::table('users')->whereNull('deleted_at')->whereBetween('created_at', $range), 'created_at', $grain, $labels)
            : $this->counts($memberships(), 'created_at', $grain, $labels);
        $ledger = DB::table('call_usage_ledger')->whereBetween('created_at', $range)
            ->when(!$this->platform($context), fn ($q) => $q->whereIn('user_id', $this->scopedUsers($context)));
        $activity = DB::query()->fromSub(
            DB::table('workspace_messages')->whereIn('school_id', $schools)->whereBetween('created_at', $range)->select('created_at', 'sender_id as user_id')
                ->unionAll(DB::table('workspace_appointments')->whereIn('school_id', $schools)->whereBetween('created_at', $range)->select('created_at', 'organizer_id as user_id'))
                ->unionAll((clone $ledger)->select('created_at', 'user_id')), 'activity');
        $posts = fn (string $kind) => DB::table('workspace_posts')->whereIn('school_id', $schools)->where('kind', $kind)->whereBetween('created_at', $range);
        $subscriptions = DB::table('user_subscription')->whereBetween('created_at', $range)
            ->when(!$this->platform($context), fn ($q) => $q->whereIn('user_id', $this->scopedUsers($context)));
        return [
            'labels' => $this->displayLabels($labels, $grain),
            'grain' => $grain,
            'registrations' => $registrations,
            'parents' => $this->counts($memberships('parent'), 'created_at', $grain, $labels),
            'teachers' => $this->counts($memberships('teacher'), 'created_at', $grain, $labels),
            'staff' => $this->counts($memberships('staff'), 'created_at', $grain, $labels),
            'active_users' => $this->counts($activity, 'created_at', $grain, $labels, 'COUNT(DISTINCT user_id)'),
            'video_minutes' => $this->counts(clone $ledger, 'created_at', $grain, $labels, 'SUM(seconds) / 60'),
            'video_calls' => $this->counts(clone $ledger, 'created_at', $grain, $labels, 'COUNT(DISTINCT COALESCE(user_call_room_id, channel_name))'),
            'messages' => $this->counts(DB::table('workspace_messages')->whereIn('school_id', $schools)->whereBetween('created_at', $range), 'created_at', $grain, $labels),
            'meetings' => $this->counts(DB::table('workspace_appointments')->whereIn('school_id', $schools)->whereBetween('created_at', $range), 'created_at', $grain, $labels),
            'announcements' => $this->counts($posts('notice'), 'created_at', $grain, $labels),
            'events' => $this->counts($posts('event'), 'created_at', $grain, $labels),
            'updates' => $this->counts(DB::table('workspace_updates')->whereIn('school_id', $schools)->where('status', 'published')->whereBetween('published_at', $range), 'published_at', $grain, $labels),
            'subscriptions' => $this->counts($subscriptions, 'created_at', $grain, $labels),
        ];
    }

    /** Totals for the selected window, shown above the charts. */
    public function totals(array $context, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $schools = $this->schools($context);
        $range = [$from, $to];
        $notifications = fn (string $kind) => DB::table('workspace_notifications')->whereIn('school_id', $schools)->where('kind', $kind)->whereBetween('created_at', $range);
        $readRate = function (string $kind) use ($notifications) {
            $sent = $notifications($kind)->count();
            return $sent ? round($notifications($kind)->whereNotNull('read_at')->count() / $sent * 100) : null;
        };
        $updates = DB::table('workspace_updates')->whereIn('school_id', $schools)->whereBetween('updated_at', $range);
        $allUpdates = (clone $updates)->count();
        $ledger = DB::table('call_usage_ledger')->whereBetween('created_at', $range)
            ->when(!$this->platform($context), fn ($q) => $q->whereIn('user_id', $this->scopedUsers($context)));
        return [
            'video_minutes' => (int) round(((clone $ledger)->sum('seconds')) / 60),
            'video_calls' => (clone $ledger)->distinct()->count(DB::raw('COALESCE(user_call_room_id, channel_name)')),
            'announcement_read_rate' => $readRate('notice'),
            'event_read_rate' => $readRate('event'),
            'update_completion' => $allUpdates ? round((clone $updates)->where('status', 'published')->count() / $allUpdates * 100) : null,
            'classes' => DB::table('classes')->whereIn('school_id', $schools)->whereNull('deleted_at')->count(),
        ];
    }
}
