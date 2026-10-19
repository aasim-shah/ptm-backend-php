<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\Schools;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Models\UserSchools;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Role contexts: User -> user_roles -> organization (school or district) -> permissions.
 *
 * This is the single place that decides which contexts a user has, which one is active,
 * what each context may do and which schools it can see.
 */
class RoleContextService
{
    public const HEADER = 'X-PTM-Context';
    public const REQUEST_ATTRIBUTE = 'ptm_context';

    /** users.type values that predate role contexts and keep working without one. */
    public const LEGACY_TYPES = ['parent', 'teacher', 'principal'];

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    // ---------------------------------------------------------------------------------
    // Reading contexts
    // ---------------------------------------------------------------------------------

    /**
     * Keeps user_roles in step with links made outside the role flow (admin panel teacher
     * creation, principal linking, older endpoints). Only adds missing rows and ends
     * auto-created rows whose link is gone; it never touches rows an admin decided on.
     */
    public function syncLegacyLinks(User $user): void
    {
        $now = now();
        $type = strtolower((string) $user->type);
        $links = [];

        if (in_array($type, ['parent', 'teacher'], true)) {
            foreach (UserSchools::where('user_id', $user->id)->pluck('school_id')->unique() as $schoolId) {
                $links[] = [$type, (int) $schoolId];
            }
        }
        foreach (Schools::where('principal_id', $user->id)->pluck('id') as $schoolId) {
            $links[] = ['school_admin', (int) $schoolId];
        }

        foreach ($links as [$role, $schoolId]) {
            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $user->id,
                'role' => $role,
                'organization_type' => 'school',
                'organization_id' => $schoolId,
                'status' => 'active',
                'source' => 'legacy_link',
                'requested_at' => $now,
                'decided_at' => $now,
                'effective_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $linked = array_map(fn ($link) => $link[0] . ':' . $link[1], $links);
        UserRole::where('user_id', $user->id)
            ->where('source', 'legacy_link')
            ->where('organization_type', 'school')
            ->current()
            ->get()
            ->reject(fn (UserRole $context) => in_array($context->role . ':' . $context->organization_id, $linked, true))
            ->each(function (UserRole $context) use ($now) {
                $context->effective_to = $now;
                $context->save();
            });
    }

    /** All contexts that have not ended, oldest first. */
    public function contextsFor(User $user): Collection
    {
        return UserRole::where('user_id', $user->id)->current()->orderBy('id')->get();
    }

    public function find(User $user, $contextId): ?UserRole
    {
        if (!is_numeric($contextId)) {
            return null;
        }
        return UserRole::where('id', (int) $contextId)->where('user_id', $user->id)->first();
    }

    /**
     * The context the server treats as active when the request names none: the last one
     * the user switched to, else the first active one matching their signup type, else the
     * first active one.
     */
    public function defaultContext(User $user): ?UserRole
    {
        $active = $this->contextsFor($user)->filter->isUsable()->values();
        if ($active->isEmpty()) {
            return null;
        }
        if ($user->last_context_id && ($last = $active->firstWhere('id', $user->last_context_id))) {
            return $last;
        }
        return $active->firstWhere('role', strtolower((string) $user->type)) ?? $active->first();
    }

    /**
     * Resolves the context for an API request (X-PTM-Context, Part A5.6).
     *
     * @return array{0: ?UserRole, 1: ?string} [context, error message when the header is not allowed]
     */
    public function resolve(Request $request, User $user): array
    {
        $header = $request->header(self::HEADER);
        if ($header === null || $header === '' || $header === 'null') {
            return [$this->defaultContext($user), null];
        }

        $context = $this->find($user, $header);
        if (!$context) {
            return [null, 'This role context does not belong to your account.'];
        }
        if (!$context->isUsable()) {
            return [null, $context->hasEnded()
                ? 'This role has ended.'
                : "This role is {$context->status}. You can't use it yet."];
        }
        return [$context, null];
    }

    /** True when the user is linked to any organization, including through a pending request. */
    public function hasSchool(User $user): bool
    {
        return UserSchools::where('user_id', $user->id)->exists()
            || UserRole::where('user_id', $user->id)->current()->exists();
    }

    // ---------------------------------------------------------------------------------
    // Permissions and scope
    // ---------------------------------------------------------------------------------

    /** Effective permission keys: role defaults plus this context's grants and revocations. */
    public function effectivePermissions(UserRole $context): array
    {
        if (!$context->isUsable()) {
            return [];
        }
        $keys = DB::table('context_role_permissions')->where('role', $context->role)->pluck('permission_key')->all();
        foreach (UserRolePermission::where('user_role_id', $context->id)->get() as $override) {
            if ($override->granted) {
                $keys[] = $override->permission_key;
            } else {
                $keys = array_diff($keys, [$override->permission_key]);
            }
        }
        return array_values(array_unique($keys));
    }

    public function contextCan(UserRole $context, string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions($context), true);
    }

    /** School ids a context may see: its school, or every school of its district. */
    public function schoolIds(UserRole $context): array
    {
        if ($context->isDistrict()) {
            return Schools::where('district_id', $context->organization_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return [(int) $context->organization_id];
    }

    /** User ids that belong to any of the given schools (linked users, principals and active contexts). */
    public function userIdsInSchools(array $schoolIds): array
    {
        if (!$schoolIds) {
            return [];
        }
        $districtIds = Schools::whereIn('id', $schoolIds)->whereNotNull('district_id')->pluck('district_id')->all();

        return collect()
            ->merge(UserSchools::whereIn('school_id', $schoolIds)->pluck('user_id'))
            ->merge(Schools::whereIn('id', $schoolIds)->whereNotNull('principal_id')->pluck('principal_id'))
            ->merge(UserRole::where('status', 'active')->current()->where(function ($query) use ($schoolIds, $districtIds) {
                $query->where(function ($query) use ($schoolIds) {
                    $query->where('organization_type', 'school')->whereIn('organization_id', $schoolIds);
                });
                if ($districtIds) {
                    $query->orWhere(function ($query) use ($districtIds) {
                        $query->where('organization_type', 'district')->whereIn('organization_id', $districtIds);
                    });
                }
            })->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Users on the institution side of the given schools: linked teachers, principals and
     * active teacher/staff/admin contexts (school-level or of the schools' district).
     */
    public function institutionUserIds(array $schoolIds): array
    {
        if (!$schoolIds) {
            return [];
        }
        $districtIds = Schools::whereIn('id', $schoolIds)->whereNotNull('district_id')->pluck('district_id')->all();

        return collect()
            ->merge(UserSchools::whereIn('school_id', $schoolIds)
                ->whereHas('user', fn ($query) => $query->where('type', 'teacher'))->pluck('user_id'))
            ->merge(Schools::whereIn('id', $schoolIds)->whereNotNull('principal_id')->pluck('principal_id'))
            ->merge(UserRole::whereIn('role', UserRole::INSTITUTION_ROLES)->where('status', 'active')->current()
                ->where(function ($query) use ($schoolIds, $districtIds) {
                    $query->where(function ($query) use ($schoolIds) {
                        $query->where('organization_type', 'school')->whereIn('organization_id', $schoolIds);
                    });
                    if ($districtIds) {
                        $query->orWhere(function ($query) use ($districtIds) {
                            $query->where('organization_type', 'district')->whereIn('organization_id', $districtIds);
                        });
                    }
                })->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ---------------------------------------------------------------------------------
    // LoginResponse (Part A4)
    // ---------------------------------------------------------------------------------

    public function serialize(UserRole $context, array $organizations = []): array
    {
        $organization = $organizations[$context->organization_type . ':' . $context->organization_id]
            ?? $this->organizationSummary($context->organization_type, $context->organization_id);

        return [
            'id' => $context->id,
            'role' => $context->role,
            'status' => $context->status,
            'organization' => $organization,
            'permissions' => $this->effectivePermissions($context),
        ];
    }

    /**
     * The role fields every LoginResponse carries: has_school, active_context_id, contexts.
     */
    public function loginFields(User $user, ?UserRole $active = null): array
    {
        $this->syncLegacyLinks($user);
        $contexts = $this->contextsFor($user);
        $active = ($active && $active->isUsable()) ? $active : $this->defaultContext($user);

        $schools = Schools::whereIn('id', $contexts->where('organization_type', 'school')->pluck('organization_id'))
            ->get(['id', 'school_name'])->keyBy('id');
        $districts = District::whereIn('id', $contexts->where('organization_type', 'district')->pluck('organization_id'))
            ->get(['id', 'name'])->keyBy('id');
        $organizations = [];
        foreach ($schools as $school) {
            $organizations['school:' . $school->id] = ['id' => $school->id, 'type' => 'school', 'name' => $school->school_name];
        }
        foreach ($districts as $district) {
            $organizations['district:' . $district->id] = ['id' => $district->id, 'type' => 'district', 'name' => $district->name];
        }

        return [
            'has_school' => $this->hasSchool($user),
            'active_context_id' => $active ? $active->id : null,
            'contexts' => $contexts->map(fn (UserRole $context) => $this->serialize($context, $organizations))->values()->all(),
        ];
    }

    public function organizationSummary(string $type, $id): ?array
    {
        $organization = $type === 'district' ? District::find($id) : Schools::find($id);
        if (!$organization) {
            return null;
        }
        return [
            'id' => $organization->id,
            'type' => $type,
            'name' => $type === 'district' ? $organization->name : $organization->school_name,
        ];
    }

    // ---------------------------------------------------------------------------------
    // Creating and deciding contexts
    // ---------------------------------------------------------------------------------

    /**
     * Parent/teacher joining a school through POST /user/schools (Part A5.3).
     * Creates the context, or reopens one that had ended. Suspended or rejected contexts
     * stay as the admin left them.
     */
    public function linkSchool(User $user, string $role, int $schoolId, string $status = 'active'): UserRole
    {
        $context = UserRole::firstOrNew([
            'user_id' => $user->id,
            'role' => $role,
            'organization_type' => 'school',
            'organization_id' => $schoolId,
        ]);
        if ($context->exists && !$context->hasEnded()) {
            return $context;
        }

        $now = now();
        $context->fill([
            'status' => $status,
            'source' => 'legacy_link',
            'requested_at' => $now,
            'decided_at' => $status === 'active' ? $now : null,
            'effective_from' => $status === 'active' ? $now : null,
            'effective_to' => null,
        ])->save();

        AuditLog::record($user->id, 'context.linked', $context, $context->id, ['role' => $role, 'status' => $status]);
        return $context;
    }

    /**
     * Staff / admin access request (Part A5.4). Idempotent: an existing request for the same
     * user, role and organization is returned unchanged.
     *
     * @return array{0: UserRole, 1: bool} [context, created]
     */
    public function requestRole(User $user, string $role, string $organizationType, int $organizationId): array
    {
        $existing = UserRole::where([
            'user_id' => $user->id,
            'role' => $role,
            'organization_type' => $organizationType,
            'organization_id' => $organizationId,
        ])->first();
        if ($existing && !$existing->hasEnded()) {
            return [$existing, false];
        }

        $context = $existing ?? new UserRole([
            'user_id' => $user->id,
            'role' => $role,
            'organization_type' => $organizationType,
            'organization_id' => $organizationId,
        ]);
        $context->fill([
            'status' => 'pending',
            'source' => 'request',
            'requested_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'effective_from' => null,
            'effective_to' => null,
        ])->save();

        AuditLog::record($user->id, 'context.requested', $context, $context->id, [
            'role' => $role, 'organization_type' => $organizationType, 'organization_id' => $organizationId,
        ]);
        $this->notifyApprovers($user, $context);

        return [$context, true];
    }

    /**
     * Approve, reject, suspend or reactivate a context (web panel, Part A7).
     */
    public function decide(User $actor, UserRole $context, string $status): UserRole
    {
        $previous = $context->status;
        $now = now();
        $context->status = $status;
        $context->decided_by = $actor->id;
        $context->decided_at = $now;
        if ($status === 'active') {
            $context->effective_from = $context->effective_from ?? $now;
            $context->effective_to = null;
            $this->ensureRoleProfile($context);
        }
        $context->save();

        AuditLog::record($actor->id, 'context.' . $status, $context, $context->id, ['from' => $previous]);
        if ($previous !== $status) {
            $this->notifyDecision($context);
        }
        return $context;
    }

    /** Ends a relationship (Doc 12 J5). Access stops on the next request. */
    public function end(User $actor, UserRole $context): UserRole
    {
        $context->effective_to = now();
        $context->decided_by = $actor->id;
        $context->save();
        AuditLog::record($actor->id, 'context.ended', $context, $context->id);
        return $context;
    }

    /**
     * Replaces a context's permission changes. $permissions maps key => true (grant) /
     * false (revoke); keys equal to the role default are stored as no change.
     */
    public function setPermissions(User $actor, UserRole $context, array $permissions): array
    {
        $known = DB::table('context_permissions')->pluck('key')->all();
        $defaults = DB::table('context_role_permissions')->where('role', $context->role)->pluck('permission_key')->all();

        DB::transaction(function () use ($context, $permissions, $known, $defaults) {
            UserRolePermission::where('user_role_id', $context->id)->delete();
            foreach ($permissions as $key => $granted) {
                $granted = filter_var($granted, FILTER_VALIDATE_BOOLEAN);
                if (!in_array($key, $known, true) || $granted === in_array($key, $defaults, true)) {
                    continue;
                }
                UserRolePermission::create(['user_role_id' => $context->id, 'permission_key' => $key, 'granted' => $granted]);
            }
        });

        $effective = $this->effectivePermissions($context);
        AuditLog::record($actor->id, 'context.permissions_changed', $context, $context->id, ['permissions' => $effective]);
        return $effective;
    }

    public function recordSwitch(User $user, UserRole $context): void
    {
        $from = $user->last_context_id;
        $user->last_context_id = $context->id;
        $user->save();
        AuditLog::record($user->id, 'context.switched', $context, $context->id, ['from' => $from]);
    }

    // ---------------------------------------------------------------------------------
    // Who approves what
    // ---------------------------------------------------------------------------------

    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /** Active admin contexts of a user, used to decide what the user may administer. */
    private function adminContexts(User $user): Collection
    {
        return UserRole::where('user_id', $user->id)
            ->whereIn('role', ['school_admin', 'district_admin'])
            ->where('status', 'active')->current()->get();
    }

    /**
     * Whether $actor may approve/reject/suspend $context.
     * Staff and teachers: their school's admins and the district's admins.
     * School admins: the district's admins (standalone school: Super Admin).
     * District admins: Super Admin.
     */
    public function canAdminister(User $actor, UserRole $context): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }
        if ((int) $context->user_id === (int) $actor->id || $context->isDistrict() || $context->role === 'district_admin') {
            return false;
        }

        $districtId = Schools::where('id', $context->organization_id)->value('district_id');
        foreach ($this->adminContexts($actor) as $admin) {
            if ($admin->role === 'district_admin' && $districtId && (int) $admin->organization_id === (int) $districtId) {
                return true;
            }
            if ($admin->role === 'school_admin' && $context->role !== 'school_admin'
                && $admin->isSchool() && (int) $admin->organization_id === (int) $context->organization_id) {
                return true;
            }
        }
        return false;
    }

    /** Contexts (of other users) that $actor may administer, for the panel lists. */
    public function administrableQuery(User $actor)
    {
        $query = UserRole::query()->current();
        if ($this->isSuperAdmin($actor)) {
            return $query;
        }

        $schoolAdminSchools = [];
        $districtSchools = [];
        foreach ($this->adminContexts($actor) as $admin) {
            if ($admin->role === 'school_admin' && $admin->isSchool()) {
                $schoolAdminSchools[] = (int) $admin->organization_id;
            }
            if ($admin->role === 'district_admin' && $admin->isDistrict()) {
                $districtSchools = array_merge($districtSchools, $this->schoolIds($admin));
            }
        }

        return $query->where('user_id', '!=', $actor->id)
            ->where('organization_type', 'school')
            ->where(function ($query) use ($schoolAdminSchools, $districtSchools) {
                $query->where(function ($query) use ($schoolAdminSchools) {
                    $query->whereIn('organization_id', $schoolAdminSchools ?: [0])
                        ->whereIn('role', ['parent', 'teacher', 'staff']);
                })->orWhere(function ($query) use ($districtSchools) {
                    $query->whereIn('organization_id', $districtSchools ?: [0])
                        ->whereIn('role', ['parent', 'teacher', 'staff', 'school_admin']);
                });
            });
    }

    /** User ids to notify about a new request. */
    public function approverIds(UserRole $context): array
    {
        $superAdmins = fn () => User::role('Super Admin')->pluck('id')->all();

        if ($context->role === 'district_admin' || $context->isDistrict()) {
            return $superAdmins();
        }

        $schoolAdmins = UserRole::where('role', 'school_admin')->where('status', 'active')->current()
            ->where('organization_type', 'school')->where('organization_id', $context->organization_id)
            ->pluck('user_id')->all();
        $districtId = Schools::where('id', $context->organization_id)->value('district_id');
        $districtAdmins = $districtId
            ? UserRole::where('role', 'district_admin')->where('status', 'active')->current()
                ->where('organization_type', 'district')->where('organization_id', $districtId)
                ->pluck('user_id')->all()
            : [];

        if ($context->role === 'school_admin') {
            return $districtAdmins ?: $superAdmins();
        }
        return $schoolAdmins ?: ($districtAdmins ?: $superAdmins());
    }

    // ---------------------------------------------------------------------------------
    // Side effects
    // ---------------------------------------------------------------------------------

    /** Teacher and parent contexts need the profile rows the existing endpoints read. */
    private function ensureRoleProfile(UserRole $context): void
    {
        $user = $context->user;
        if (!$user) {
            return;
        }
        if ($context->role === 'teacher' && !$user->teacher) {
            \App\Models\Teacher::create(['user_id' => $user->id]);
        }
        if ($context->role === 'parent' && !$user->parent) {
            \App\Models\Parents::create([
                'user_id' => $user->id, 'first_name' => $user->first_name,
                'last_name' => $user->last_name, 'email' => $user->email,
            ]);
        }
        if (in_array($context->role, ['parent', 'teacher'], true) && $context->isSchool()
            && !UserSchools::where('user_id', $user->id)->where('school_id', $context->organization_id)->exists()) {
            UserSchools::insert([
                'id' => \App\Helpers\Utility::getUUID(),
                'user_id' => $user->id,
                'school_id' => $context->organization_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function notifyApprovers(User $requester, UserRole $context): void
    {
        try {
            $ids = $this->approverIds($context);
            if (!$ids) {
                return;
            }
            $organization = $this->organizationSummary($context->organization_type, $context->organization_id);
            $title = 'New access request';
            $message = trim($requester->first_name . ' ' . $requester->last_name) . ' asked for '
                . str_replace('_', ' ', $context->role) . ' access to ' . ($organization['name'] ?? 'your organization') . '.';
            $this->notificationSender->sendNotification($title, $message, $ids, 'ROLE_REQUEST',
                json_encode(['context_id' => (string) $context->id, 'status' => $context->status]));
            foreach ($ids as $id) {
                $this->notificationSender->saveLogs($id, $requester->id, $message, 'ROLE_REQUEST', $title);
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    private function notifyDecision(UserRole $context): void
    {
        try {
            $organization = $this->organizationSummary($context->organization_type, $context->organization_id);
            $name = $organization['name'] ?? 'your organization';
            [$title, $message] = match ($context->status) {
                'active' => ['Access approved', "You can now use PTM at {$name}."],
                'rejected' => ['Access request declined', "Your access request for {$name} was declined."],
                'suspended' => ['Access suspended', "Your access at {$name} has been suspended."],
                default => ['Access updated', "Your access at {$name} changed to {$context->status}."],
            };
            $this->notificationSender->sendNotification($title, $message, [$context->user_id], 'ROLE_REQUEST_STATUS',
                json_encode(['context_id' => (string) $context->id, 'status' => $context->status]));
            $this->notificationSender->saveLogs($context->user_id, $context->decided_by, $message, 'ROLE_REQUEST_STATUS', $title);
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }
}
