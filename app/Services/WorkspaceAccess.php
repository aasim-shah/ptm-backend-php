<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Organization context is re-resolved on every request, never trusted from the session. */
class WorkspaceAccess
{
    public const LABELS = ['parent' => 'Parent', 'teacher' => 'Teacher', 'staff' => 'Staff Member',
        'school_admin' => 'School Admin', 'district_admin' => 'District Admin', 'sub_super_admin' => 'Sub-Super Admin', 'super_admin' => 'Super Admin'];
    public const DELEGATED_PERMISSIONS = ['students.view', 'notices.publish', 'events.manage', 'memberships.manage', 'academics.manage', 'reports.export', 'audit.view'];
    public const STAFF_PERMISSIONS = ['web.access', 'students.view', 'notices.publish', 'events.manage'];

    public function memberships(User $user)
    {
        return DB::table('workspace_memberships')->where('user_id', $user->id)->where('status', 'active')
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); });
    }

    public function contexts(User $user): array
    {
        if (!(int) $user->status || !$user->email_verified_at) { return []; }
        $contexts = [];
        if ($user->hasRole('Super Admin')) {
            $contexts[] = ['id' => 'platform', 'role' => 'super_admin', 'label' => 'Super Admin',
                'organization' => 'Full platform', 'schools' => DB::table('schools')->pluck('school_name', 'id')->all(),
                'permissions' => [], 'membership_id' => null];
        }
        foreach ($this->memberships($user)->get() as $m) {
            if ($m->role === 'super_admin' || !isset(self::LABELS[$m->role])) { continue; }
            if ($m->role === 'district_admin' || ($m->role === 'sub_super_admin' && $m->district_id)) {
                if (!$m->district_id || $m->school_id) { continue; }
                $district = DB::table('workspace_districts')->find($m->district_id);
                if (!$district) { continue; }
                $schools = DB::table('schools')->join('workspace_district_schools as ds', 'ds.school_id', '=', 'schools.id')
                    ->where('ds.district_id', $m->district_id)->pluck('schools.school_name', 'schools.id')->all();
                $contexts[] = $this->context($m, 'm'.$m->id, $district->name, $schools);
                foreach ($schools as $id => $name) {
                    $contexts[] = $this->context($m, 'm'.$m->id.'s'.$id, $name, [$id => $name]);
                }
            } elseif ($m->school_id && !$m->district_id) {
                $school = DB::table('schools')->find($m->school_id);
                if ($school) { $contexts[] = $this->context($m, 'm'.$m->id, $school->school_name, [$school->id => $school->school_name]); }
            }
        }
        // Existing public roles can sign in, but without approved memberships no
        // school data or privileged actions become available.
        foreach (['Parent' => 'parent', 'Teacher' => 'teacher', 'Staff' => 'staff'] as $role => $key) {
            if ($user->hasRole($role) && !collect($contexts)->contains('role', $key)) {
                $contexts[] = ['id' => 'personal-'.$key, 'role' => $key, 'label' => self::LABELS[$key],
                    'organization' => 'Awaiting school connection', 'schools' => [], 'permissions' => [], 'membership_id' => null];
            }
        }
        return $contexts;
    }

    /** Mobile contexts remain available; the web surface is administrative only. */
    public function webContexts(User $user): array
    {
        return array_values(array_filter($this->contexts($user), fn ($c) =>
            in_array($c['role'], ['super_admin', 'sub_super_admin', 'district_admin', 'school_admin'], true)
            || ($c['role'] === 'staff' && in_array('web.access', $c['permissions'], true))));
    }

    private function context($m, string $id, string $name, array $schools): array
    {
        $permissions = json_decode($m->permissions ?? '[]', true);
        return ['id' => $id, 'role' => $m->role, 'label' => self::LABELS[$m->role], 'organization' => $name,
            'schools' => $schools, 'permissions' => is_array($permissions) ? $permissions : [], 'membership_id' => $m->id];
    }

    public function resolve(User $user, ?string $id): array
    {
        $contexts = $this->contexts($user);
        if ($id !== null) {
            foreach ($contexts as $context) { if ($context['id'] === $id) { return $context; } }
            abort(403, 'This workspace is no longer available.');
        }
        abort_unless(count($contexts), 403, 'No active workspace. Ask your school administrator to connect your account.');
        return $contexts[0];
    }

    public function can(array $context, string $permission): bool
    {
        if ($context['role'] === 'super_admin') { return true; }
        if ($context['role'] === 'sub_super_admin') { return in_array($permission, self::DELEGATED_PERMISSIONS, true) && in_array($permission, $context['permissions'], true); }
        if (!$context['membership_id'] || !$context['schools']) { return false; }
        if (in_array($context['role'], ['school_admin', 'district_admin'], true)) { return true; }
        if ($context['role'] === 'teacher') { return in_array($permission, ['students.view', 'updates.write'], true); }
        return $context['role'] === 'staff' && in_array($permission, self::STAFF_PERMISSIONS, true)
            && in_array($permission, $context['permissions'], true);
    }

    public function mayDelegate(array $context, array $permissions): bool
    {
        return $context['role'] !== 'sub_super_admin' || !array_diff($permissions, $context['permissions']);
    }

    public function sections(User $user, array $context)
    {
        $q = ClassSection::whereHas('class', function ($q) use ($context) { $q->whereIn('school_id', array_keys($context['schools'])); });
        if ($context['role'] === 'teacher') {
            $q->where(function ($q) use ($user) {
                $q->where('class_teacher_id', $user->id)->orWhereHas('subject_teachers', function ($q) use ($user) { $q->where('teacher_id', $user->id); });
            });
        }
        return $q;
    }

    public function students(User $user, array $context)
    {
        $q = Students::whereHas('user', function ($q) { $q->where('status', 1); })
            ->whereIn('class_section_id', $this->sections($user, $context)->select('class_sections.id'));
        if ($context['role'] === 'parent') {
            $q->whereIn('students.id', DB::table('workspace_guardians')->where('guardian_id', $user->id)
                ->whereIn('school_id', array_keys($context['schools']))->where('status', 'verified')
                ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })->select('student_id'));
        } elseif (!$this->can($context, 'students.view')) {
            $q->whereRaw('1 = 0');
        }
        return $q;
    }

    public function audit(User $user, ?int $schoolId, string $action, $subject): void
    {
        DB::table('workspace_audit')->insert(['actor_id' => $user->id, 'school_id' => $schoolId,
            'action' => $action, 'subject' => (string) $subject, 'created_at' => now()]);
    }

    /** Approved same-school contacts; parent-to-teacher contact also needs an active student assignment. */
    public function contacts(User $user, array $context)
    {
        $ids = $this->contactIds($user, $context);
        return User::whereIn('id', $ids)->where('status', 1)->whereNotNull('email_verified_at')
            ->where('id', '!=', $user->id)->select('id', 'first_name', 'last_name')->orderBy('first_name');
    }

    private function contactIds(User $user, array $context): array
    {
        $schoolIds = array_keys($context['schools']);
        $active = DB::table('workspace_memberships')->whereIn('school_id', $schoolIds)->where('status', 'active')
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); });
        if ($context['role'] === 'parent') {
            $sections = $this->students($user, $context)->pluck('class_section_id');
            $teachers = ClassSection::whereIn('id', $sections)->pluck('class_teacher_id')
                ->merge(DB::table('subject_teachers')->whereNull('deleted_at')->whereIn('class_section_id', $sections)->pluck('teacher_id'));
            $active->where(function ($q) use ($teachers) {
                $q->where('role', 'school_admin')->orWhere(function ($q) use ($teachers) { $q->where('role', 'teacher')->whereIn('user_id', $teachers); });
            });
        } elseif ($context['role'] === 'teacher') {
            $guardians = DB::table('workspace_guardians')->whereIn('student_id', $this->students($user, $context)->select('students.id'))
                ->where('status', 'verified')->whereIn('school_id', $schoolIds)
                ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })->pluck('guardian_id');
            $active->where(function ($q) use ($guardians) { $q->whereIn('role', ['teacher', 'school_admin', 'staff'])->orWhereIn('user_id', $guardians); });
        } elseif ($context['role'] === 'staff') {
            $active->whereIn('role', ['school_admin', 'teacher', 'staff']);
        }
        if ($context['role'] === 'district_admin') { $active->whereIn('role', ['teacher', 'staff', 'school_admin']); }
        $ids = $active->pluck('user_id');
        if ($context['role'] !== 'parent') {
            $districts = DB::table('workspace_district_schools')->whereIn('school_id', $schoolIds)->pluck('district_id');
            $ids = $ids->merge(DB::table('workspace_memberships')->whereIn('district_id', $districts)->where('role', 'district_admin')->where('status', 'active')
                ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
                ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })->pluck('user_id'));
        }
        return $ids->unique()->all();
    }
}
