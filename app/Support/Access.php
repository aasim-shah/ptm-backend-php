<?php

namespace App\Support;

use App\Models\ClassSection;
use App\Models\Students;
use App\Models\User;

/**
 * Ownership checks shared by the mobile API controllers.
 */
class Access
{
    public static function isPrincipal(User $user): bool
    {
        return $user->type === 'Principal' || $user->hasRole('Principal');
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
        if (self::isPrincipal($user)) {
            return true;
        }

        $student = Students::where('user_id', $studentUserId)->first();
        if (!$student) {
            return false;
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

    public static function forbidden()
    {
        return response()->json([
            'error' => true,
            'message' => trans('no_permission_message'),
            'code' => 403,
        ], 403);
    }
}
