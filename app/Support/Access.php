<?php

namespace App\Support;

use App\Models\ClassSection;
use App\Models\Students;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserRole;
use App\Services\RoleContextService;

/**
 * Ownership checks shared by the mobile API controllers.
 */
class Access
{
    public static function isPrincipal(User $user): bool
    {
        return $user->hasRole('Principal');
    }

    public static function principalOwnsClass(User $user, $classId): bool
    {
        return self::isPrincipal($user) && ClassSection::where('class_id', $classId)
            ->whereHas('class.school', function ($query) use ($user) { $query->where('principal_id', $user->id); })->exists();
    }

    /**
     * True when the user is the class teacher of any section of the class.
     */
    public static function teacherOwnsClass(User $user, $classId): bool
    {
        return ClassSection::where('class_id', $classId)
            ->where('class_teacher_id', $user->id)
            ->exists();
    }

    /**
     * Whether the user may read data about a student.
     * $studentUserId is the student's users.id (what attendance and reports store).
     */
    public static function canViewStudent(User $user, $studentUserId): bool
    {
        $student = Students::where('user_id', $studentUserId)->first();
        if (!$student) {
            return false;
        }

        if (self::isPrincipal($user)) {
            return $student->class_section_id && ClassSection::where('id', $student->class_section_id)
                ->whereHas('class.school', function ($query) use ($user) { $query->where('principal_id', $user->id); })->exists();
        }

        if ((int) $student->user_id === (int) $user->id) {
            return true;
        }

        if (in_array((int) $user->id, array_map('intval', array_filter([
            $student->father_id, $student->mother_id, $student->guardian_id,
        ])), true)) {
            return true;
        }

        return $student->class_section_id
            && ClassSection::where('id', $student->class_section_id)
                ->where('class_teacher_id', $user->id)
                ->exists();
    }

    /**
     * The role context of the current API request (set by ResolvePtmContext), or null
     * for users that have none yet (legacy mode).
     */
    public static function context(): ?UserRole
    {
        $request = request();
        return $request ? $request->attributes->get(RoleContextService::REQUEST_ATTRIBUTE) : null;
    }

    /**
     * The role the user is acting as: the active context's role, else the legacy users.type
     * ('principal' for principals).
     */
    public static function role(User $user): string
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return $context->role;
        }
        return self::isPrincipal($user) ? 'principal' : strtolower((string) $user->type);
    }

    /** Parent path or institution path (teacher, staff, admins, principal). */
    public static function actsAsParent(User $user): bool
    {
        return self::role($user) === 'parent';
    }

    /**
     * Whether the current request may use a permission. With a context this is the
     * context's effective permissions; without one only legacy parent/teacher/principal
     * accounts pass, and the route's role middleware still applies.
     */
    public static function can(User $user, string $permission): bool
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return app(RoleContextService::class)->contextCan($context, $permission);
        }
        return in_array(self::role($user), RoleContextService::LEGACY_TYPES, true);
    }

    /** School ids the current request is limited to (null = legacy mode: the user's linked schools). */
    public static function schoolIds(User $user): ?array
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return app(RoleContextService::class)->schoolIds($context);
        }
        return null;
    }

    /**
     * Whether a user can sit in the institution side of a meeting: a teacher, or anyone
     * with an active teacher/staff/admin context.
     */
    public static function isInstitutionUser($userId): bool
    {
        return Teacher::where('user_id', $userId)->exists()
            || UserRole::where('user_id', $userId)
                ->whereIn('role', UserRole::INSTITUTION_ROLES)
                ->where('status', 'active')->current()->exists();
    }

    /**
     * How far the current request can see classes and students in the pickers (Part A6):
     * 'own'    classes the user is class teacher of (teachers; staff with students.view_assigned)
     * 'school' every class of the context's schools (school and district admins)
     * 'none'   nothing (staff without students.view_assigned)
     */
    public static function classScope(User $user): string
    {
        $role = self::role($user);
        if (in_array($role, ['school_admin', 'district_admin'], true)) {
            return 'school';
        }
        if ($role === 'staff') {
            return self::can($user, 'students.view_assigned') ? 'own' : 'none';
        }
        return 'own';
    }

    public static function forbidden()
    {
        return response()->json([
            'error' => true,
            'message' => trans('no_permission_message'),
            'code' => 403,
        ], 403);
    }
}
