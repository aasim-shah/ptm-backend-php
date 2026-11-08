<?php

namespace App\Http\Controllers\Workspace;

use App\Services\WorkspaceAccess;
use App\Services\WorkspaceAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Parents / Guardians, Teachers, Staff and Students directories. Each role has
 * its own page with search, filters, paging and a person detail view; adding
 * people happens from a dialog on the page instead of a shared form page.
 */
class DirectoryController extends AdminPage
{
    public const ROLES = ['parent' => 'Parents / Guardians', 'teacher' => 'Teachers', 'staff' => 'Staff'];
    public const STUDENT_STATUS = ['ACCEPTED' => 'Enrolled', 'PENDING' => 'Pending approval', 'REJECTED' => 'Not enrolled', 'INACTIVE' => 'Inactive'];

    private function activeMemberships()
    {
        return DB::table('workspace_memberships as am')->where('am.status', 'active')
            ->where(fn ($q) => $q->whereNull('am.starts_at')->orWhere('am.starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('am.ends_at')->orWhere('am.ends_at', '>', now()));
    }

    public function index(Request $request, string $role)
    {
        abort_unless(isset(self::ROLES[$role]), 404);
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'school_id' => ['nullable', 'integer', Rule::in(array_keys($context['schools']))],
            'status' => 'nullable|in:active,revoked,unverified,disabled,unconnected']);
        $platform = WorkspaceAccess::platformWide($context) && empty($filters['school_id']);
        $schools = !empty($filters['school_id']) ? [(int) $filters['school_id']] : array_keys($context['schools']);

        $connected = DB::table('workspace_memberships')->where('role', $role)->whereIn('school_id', $schools)->select('user_id');
        $legacy = isset(WorkspaceAnalytics::LEGACY_ROLES[$role]) ? DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('r.name', WorkspaceAnalytics::LEGACY_ROLES[$role])->where('mr.model_type', \App\Models\User::class)->select('mr.model_id') : null;
        $base = DB::table('users as u')->whereNull('u.deleted_at')->where(function ($q) use ($connected, $legacy, $platform) {
            $q->whereIn('u.id', $connected);
            if ($platform && $legacy) { $q->orWhereIn('u.id', $legacy); }
        });
        $total = (clone $base)->count();
        if ($term = $this->like($filters['q'] ?? null)) {
            $base->where(fn ($q) => $q->where('u.first_name', 'like', $term)->orWhere('u.last_name', 'like', $term)->orWhere('u.email', 'like', $term)
                ->orWhereRaw("CONCAT(u.first_name, ' ', u.last_name) LIKE ?", [$term]));
        }
        $active = (clone $this->activeMemberships())->where('am.role', $role)->whereIn('am.school_id', $schools)->select('am.user_id');
        match ($filters['status'] ?? null) {
            'active' => $base->where('u.status', 1)->whereIn('u.id', $active),
            'revoked' => $base->whereIn('u.id', DB::table('workspace_memberships')->where('role', $role)->whereIn('school_id', $schools)->where('status', 'revoked')->select('user_id'))->whereNotIn('u.id', $active),
            'unverified' => $base->whereNull('u.email_verified_at'),
            'disabled' => $base->where('u.status', '!=', 1),
            'unconnected' => $base->whereNotIn('u.id', DB::table('workspace_memberships')->where('role', $role)->whereNotNull('school_id')->select('user_id')),
            default => null,
        };
        $people = $base->select('u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.mobile', 'u.status', 'u.email_verified_at', 'u.created_at')
            ->orderBy('u.first_name')->orderBy('u.last_name')->paginate(25)->withQueryString();
        $ids = $people->pluck('id');
        $memberships = DB::table('workspace_memberships as m')->leftJoin('schools as s', 's.id', '=', 'm.school_id')->whereIn('m.user_id', $ids)
            ->where('m.role', $role)->whereIn('m.school_id', array_keys($context['schools']))->select('m.id', 'm.user_id', 'm.status', 'm.school_id', 'm.permissions', 's.school_name')->get()->groupBy('user_id');
        $extra = match ($role) {
            'parent' => DB::table('workspace_guardians')->whereIn('guardian_id', $ids)->where('status', 'verified')->whereIn('school_id', array_keys($context['schools']))
                ->selectRaw('guardian_id as id, COUNT(*) as total')->groupBy('guardian_id')->pluck('total', 'id'),
            'teacher' => DB::table('class_sections')->whereIn('class_teacher_id', $ids)->whereNull('deleted_at')->selectRaw('class_teacher_id as id, COUNT(*) as total')->groupBy('class_teacher_id')->pluck('total', 'id'),
            default => collect(),
        };
        $invitations = DB::table('workspace_invitations as i')->leftJoin('schools as s', 's.id', '=', 'i.school_id')->whereIn('i.school_id', $schools)
            ->where('i.role', $role)->whereNull('i.accepted_at')->whereNull('i.revoked_at')->where('i.expires_at', '>', now())
            ->select('i.id', 'i.email', 'i.expires_at', 'i.created_at', 's.school_name')->orderByDesc('i.id')->limit(50)->get();
        $guardianRequests = $role === 'parent' ? DB::table('workspace_guardians as g')->join('users as u', 'u.id', '=', 'g.guardian_id')
            ->join('students as st', 'st.id', '=', 'g.student_id')->join('users as su', 'su.id', '=', 'st.user_id')->leftJoin('schools as s', 's.id', '=', 'g.school_id')
            ->whereIn('g.school_id', $schools)->where('g.status', 'pending')
            ->select('g.id', 'g.created_at', 'u.first_name', 'u.last_name', 'u.email', 'su.first_name as student_first', 'su.last_name as student_last', 's.school_name')
            ->orderBy('g.created_at')->limit(50)->get() : collect();
        $stats = [
            ['label' => 'Total', 'value' => $total, 'icon' => 'users'],
            ['label' => 'Active', 'value' => app(WorkspaceAnalytics::class)->members($context, $role), 'icon' => 'check-circle'],
            ['label' => 'Pending invitations', 'value' => $invitations->count(), 'icon' => 'envelope-o'],
            $role === 'parent' ? ['label' => 'Awaiting verification', 'value' => $guardianRequests->count(), 'icon' => 'hourglass-half']
                : ['label' => 'Unverified email', 'value' => (clone $base)->whereNull('u.email_verified_at')->count(), 'icon' => 'exclamation-circle'],
        ];
        return $this->render($request, $context, ['parent' => 'parents', 'teacher' => 'teachers', 'staff' => 'staff'][$role], 'workspace.admin.directory', compact('role', 'people', 'memberships', 'extra', 'invitations', 'guardianRequests', 'stats', 'filters', 'platform') + ['title' => self::ROLES[$role]]);
    }

    /** One person's account, school connections, students or classes, and recent activity. */
    public function show(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        $person = DB::table('users')->whereNull('deleted_at')->find($id);
        abort_unless($person && $this->inScope($context, $id), 404);
        $memberships = DB::table('workspace_memberships as m')->leftJoin('schools as s', 's.id', '=', 'm.school_id')->leftJoin('workspace_districts as d', 'd.id', '=', 'm.district_id')
            ->where('m.user_id', $id)->when(!WorkspaceAccess::platformWide($context), fn ($q) => $q->whereIn('m.school_id', array_keys($context['schools'])))
            ->select('m.*', 's.school_name', 'd.name as district_name')->orderByDesc('m.status')->get();
        $children = DB::table('workspace_guardians as g')->join('students as st', 'st.id', '=', 'g.student_id')->join('users as su', 'su.id', '=', 'st.user_id')
            ->leftJoin('schools as s', 's.id', '=', 'g.school_id')->where('g.guardian_id', $id)->whereIn('g.school_id', array_keys($context['schools']))
            ->select('g.id', 'g.status', 'g.verified_at', 'su.first_name', 'su.last_name', 's.school_name')->get();
        $classes = DB::table('class_sections as cs')->join('classes as c', 'c.id', '=', 'cs.class_id')->leftJoin('sections as sec', 'sec.id', '=', 'cs.section_id')
            ->leftJoin('schools as s', 's.id', '=', 'c.school_id')->where('cs.class_teacher_id', $id)->whereNull('cs.deleted_at')->whereIn('c.school_id', array_keys($context['schools']))
            ->select('c.name as class_name', 'sec.name as section_name', 's.school_name', DB::raw('(SELECT COUNT(*) FROM students WHERE students.class_section_id = cs.id AND students.deleted_at IS NULL) as students'))->get();
        $activity = DB::table('workspace_audit as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->where(fn ($q) => $q->where('a.actor_id', $id)->orWhere('a.subject', (string) $id)->orWhere('a.subject', 'like', $id.':%'))
            ->when(!WorkspaceAccess::platformWide($context), fn ($q) => $q->whereIn('a.school_id', array_keys($context['schools'])))
            ->select('a.*', 'u.first_name', 'u.last_name')->orderByDesc('a.id')->limit(15)->get();
        $roles = DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->where('mr.model_id', $id)->where('mr.model_type', \App\Models\User::class)->pluck('r.name');
        $isSuperAdmin = $roles->contains('Super Admin');
        return $this->render($request, $context, $this->pageFor($memberships, $roles), 'workspace.admin.person', compact('person', 'memberships', 'children', 'classes', 'activity', 'roles', 'isSuperAdmin'));
    }

    private function pageFor($memberships, $roles): string
    {
        $role = $memberships->firstWhere('status', 'active')->role ?? $memberships->first()->role ?? null;
        $role ??= $roles->contains('Teacher') ? 'teacher' : 'parent';
        return ['parent' => 'parents', 'teacher' => 'teachers', 'staff' => 'staff'][$role] ?? 'administrators';
    }

    private function inScope(array $context, int $id): bool
    {
        if (WorkspaceAccess::platformWide($context)) { return true; }
        return DB::table('workspace_memberships')->where('user_id', $id)->whereIn('school_id', array_keys($context['schools']))->exists();
    }

    /** Suspends or reactivates a whole account. Only the Super Admin, never on itself or another Super Admin. */
    public function account(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['status' => 'required|in:suspend,reactivate', 'reason' => 'required_if:status,suspend|nullable|string|max:300']);
        abort_if($id === (int) $request->user()->id, 422, 'You cannot suspend your own account.');
        $user = \App\Models\User::whereNull('deleted_at')->findOrFail($id);
        abort_if($user->hasRole('Super Admin'), 403, 'Another Super Admin account cannot be suspended here.');
        DB::transaction(function () use ($request, $user, $data) {
            $user->forceFill(['status' => $data['status'] === 'suspend' ? 0 : 1, 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
            if ($data['status'] === 'suspend') {
                $user->tokens()->delete();
                if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) { DB::table('sessions')->where('user_id', $user->id)->delete(); }
            }
            $this->access->audit($request->user(), null, 'account.'.($data['status'] === 'suspend' ? 'suspended' : 'reactivated'), $user->id.($data['reason'] ?? '' ? ':'.$data['reason'] : ''));
        });
        return $this->done($request, $data['status'] === 'suspend' ? 'Account suspended. The person is signed out everywhere.' : 'Account reactivated.');
    }

    /** Emails the person a password reset link. Administrators never see or set passwords. */
    public function passwordReset(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage') && $this->inScope($context, $id), 404);
        $user = \App\Models\User::whereNull('deleted_at')->where('status', 1)->findOrFail($id);
        abort_unless($user->email, 422, 'This account has no email address.');
        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        $this->access->audit($request->user(), null, 'account.password-reset.sent', $user->id);
        return $this->done($request, $status === Password::RESET_LINK_SENT ? 'Password reset link sent to '.$user->email.'.' : 'A reset link was sent recently. Ask the person to check their inbox.');
    }

    /** Restores a revoked parent, teacher or staff membership. */
    public function restore(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        DB::transaction(function () use ($request, $context, $id) {
            $m = DB::table('workspace_memberships')->whereIn('school_id', array_keys($context['schools']))->lockForUpdate()->find($id);
            abort_unless($m, 404);
            abort_unless(in_array($m->role, ['parent', 'teacher', 'staff'], true), 403, 'Administrator access is managed from Administrators.');
            DB::table('workspace_memberships')->where('id', $id)->update(['status' => 'active', 'starts_at' => now(), 'ends_at' => null, 'approved_by' => $request->user()->id, 'updated_at' => now()]);
            $this->access->audit($request->user(), $m->school_id, 'membership.restored', $id);
        });
        return $this->done($request, 'Access restored.');
    }

    public function students(Request $request)
    {
        $context = $this->context($request, [...WorkspaceAnalytics::ADMIN_ROLES, 'staff']);
        abort_unless($this->access->can($context, 'students.view'), 403);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'school_id' => ['nullable', 'integer', Rule::in(array_keys($context['schools']))],
            'class_id' => 'nullable|integer', 'guardians' => 'nullable|in:verified,pending,none']);
        $verified = DB::table('workspace_guardians')->selectRaw('COUNT(*)')->whereColumn('student_id', 'students.id')->where('status', 'verified');
        $pending = DB::table('workspace_guardians')->selectRaw('COUNT(*)')->whereColumn('student_id', 'students.id')->where('status', 'pending');
        $query = $this->access->students($request->user(), $context)->with('user:id,first_name,last_name', 'class_section.class', 'class_section.section')
            ->select('students.*')->selectSub($verified, 'verified_guardians')->selectSub($pending, 'pending_guardians');
        if ($term = $this->like($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('students.admission_no', 'like', $term)
                ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term])));
        }
        if (!empty($filters['school_id'])) { $query->whereHas('class_section.class', fn ($q) => $q->where('school_id', $filters['school_id'])); }
        if (!empty($filters['class_id'])) { $query->whereHas('class_section', fn ($q) => $q->where('class_id', $filters['class_id'])); }
        match ($filters['guardians'] ?? null) {
            'verified' => $query->whereExists(fn ($q) => $q->from('workspace_guardians')->whereColumn('student_id', 'students.id')->where('status', 'verified')),
            'pending' => $query->whereExists(fn ($q) => $q->from('workspace_guardians')->whereColumn('student_id', 'students.id')->where('status', 'pending')),
            'none' => $query->whereNotExists(fn ($q) => $q->from('workspace_guardians')->whereColumn('student_id', 'students.id')->where('status', 'verified')),
            default => null,
        };
        $students = $query->orderBy('students.id')->paginate(25)->withQueryString();
        $classes = DB::table('classes')->whereIn('school_id', !empty($filters['school_id']) ? [$filters['school_id']] : array_keys($context['schools']))
            ->whereNull('deleted_at')->orderBy('name')->limit(300)->get(['id', 'name', 'school_id']);
        $canWrite = $this->access->can($context, 'updates.write');
        return $this->render($request, $context, 'students', 'workspace.admin.students', compact('students', 'classes', 'filters', 'canWrite'));
    }
}
