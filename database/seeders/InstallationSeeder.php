<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Settings;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class InstallationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        //Add Permissions
        $permissions = [
            ['id' => 1, 'name' => 'role-list'],
            ['id' => 2, 'name' => 'role-create'],
            ['id' => 3, 'name' => 'role-edit'],
            ['id' => 4, 'name' => 'role-delete'],

            ['id' => 5, 'name' => 'medium-list'],
            ['id' => 6, 'name' => 'medium-create'],
            ['id' => 7, 'name' => 'medium-edit'],
            ['id' => 8, 'name' => 'medium-delete'],

            ['id' => 9, 'name' => 'section-list'],
            ['id' => 10, 'name' => 'section-create'],
            ['id' => 11, 'name' => 'section-edit'],
            ['id' => 12, 'name' => 'section-delete'],

            ['id' => 13, 'name' => 'class-list'],
            ['id' => 14, 'name' => 'class-create'],
            ['id' => 15, 'name' => 'class-edit'],
            ['id' => 16, 'name' => 'class-delete'],

            ['id' => 17, 'name' => 'subject-list'],
            ['id' => 18, 'name' => 'subject-create'],
            ['id' => 19, 'name' => 'subject-edit'],
            ['id' => 20, 'name' => 'subject-delete'],

            ['id' => 21, 'name' => 'teacher-list'],
            ['id' => 22, 'name' => 'teacher-create'],
            ['id' => 23, 'name' => 'teacher-edit'],
            ['id' => 24, 'name' => 'teacher-delete'],

            ['id' => 25, 'name' => 'class-teacher-list'],
            ['id' => 26, 'name' => 'class-teacher-create'],
            ['id' => 27, 'name' => 'class-teacher-edit'],
            ['id' => 28, 'name' => 'class-teacher-delete'],

            ['id' => 29, 'name' => 'parents-list'],
            ['id' => 30, 'name' => 'parents-create'],
            ['id' => 31, 'name' => 'parents-edit'],
            ['id' => 32, 'name' => 'parents-delete'],

            ['id' => 33, 'name' => 'session-year-list'],
            ['id' => 34, 'name' => 'session-year-create'],
            ['id' => 35, 'name' => 'session-year-edit'],
            ['id' => 36, 'name' => 'session-year-delete'],

            ['id' => 37, 'name' => 'student-list'],
            ['id' => 38, 'name' => 'student-create'],
            ['id' => 39, 'name' => 'student-edit'],
            ['id' => 40, 'name' => 'student-delete'],

            ['id' => 41, 'name' => 'category-list'],
            ['id' => 42, 'name' => 'category-create'],
            ['id' => 43, 'name' => 'category-edit'],
            ['id' => 44, 'name' => 'category-delete'],

            ['id' => 45, 'name' => 'subject-teacher-list'],
            ['id' => 46, 'name' => 'subject-teacher-create'],
            ['id' => 47, 'name' => 'subject-teacher-edit'],
            ['id' => 48, 'name' => 'subject-teacher-delete'],

            ['id' => 49, 'name' => 'timetable-list'],
            ['id' => 50, 'name' => 'timetable-create'],
            ['id' => 51, 'name' => 'timetable-edit'],
            ['id' => 52, 'name' => 'timetable-delete'],

            ['id' => 53, 'name' => 'attendance-list'],
            ['id' => 54, 'name' => 'attendance-create'],
            ['id' => 55, 'name' => 'attendance-edit'],
            ['id' => 56, 'name' => 'attendance-delete'],

            ['id' => 57, 'name' => 'holiday-list'],
            ['id' => 58, 'name' => 'holiday-create'],
            ['id' => 59, 'name' => 'holiday-edit'],
            ['id' => 60, 'name' => 'holiday-delete'],

            ['id' => 61, 'name' => 'announcement-list'],
            ['id' => 62, 'name' => 'announcement-create'],
            ['id' => 63, 'name' => 'announcement-edit'],
            ['id' => 64, 'name' => 'announcement-delete'],

            ['id' => 65, 'name' => 'slider-list'],
            ['id' => 66, 'name' => 'slider-create'],
            ['id' => 67, 'name' => 'slider-edit'],
            ['id' => 68, 'name' => 'slider-delete'],

            ['id' => 69, 'name' => 'class-timetable'],
            ['id' => 70, 'name' => 'teacher-timetable'],
            ['id' => 71, 'name' => 'student-assignment'],
            ['id' => 72, 'name' => 'subject-lesson'],
            ['id' => 73, 'name' => 'class-attendance'],

            ['id' => 74, 'name' => 'exam-create'],
            ['id' => 75, 'name' => 'exam-list'],
            ['id' => 76, 'name' => 'exam-edit'],
            ['id' => 77, 'name' => 'exam-delete'],
            ['id' => 78, 'name' => 'exam-upload-marks'],

            ['id' => 79, 'name' => 'setting-create'],
            ['id' => 80, 'name' => 'fcm-setting-create'],

            ['id' => 81, 'name' => 'assignment-create'],
            ['id' => 82, 'name' => 'assignment-list'],
            ['id' => 83, 'name' => 'assignment-edit'],
            ['id' => 84, 'name' => 'assignment-delete'],
            ['id' => 85, 'name' => 'assignment-submission'],

            ['id' => 86, 'name' => 'email-setting-create'],
            ['id' => 87, 'name' => 'privacy-policy'],
            ['id' => 88, 'name' => 'contact-us'],
            ['id' => 89, 'name' => 'about-us'],

            ['id' => 90, 'name' => 'student-reset-password'],
            ['id' => 91, 'name' => 'reset-password-list'],
            ['id' => 92, 'name' => 'student-change-password'],

            ['id' => 93, 'name' => 'promote-student-list'],
            ['id' => 94, 'name' => 'promote-student-create'],
            ['id' => 95, 'name' => 'promote-student-edit'],
            ['id' => 96, 'name' => 'promote-student-delete'],

            ['id' => 97, 'name' => 'language-list'],
            ['id' => 98, 'name' => 'language-create'],
            ['id' => 99, 'name' => 'language-edit'],
            ['id' => 100, 'name' => 'language-delete'],

            ['id' => 101, 'name' => 'lesson-list'],
            ['id' => 102, 'name' => 'lesson-create'],
            ['id' => 103, 'name' => 'lesson-edit'],
            ['id' => 104, 'name' => 'lesson-delete'],

            ['id' => 105, 'name' => 'topic-list'],
            ['id' => 106, 'name' => 'topic-create'],
            ['id' => 107, 'name' => 'topic-edit'],
            ['id' => 108, 'name' => 'topic-delete'],

            ['id' => 109, 'name' => 'class-teacher'],
            ['id' => 110, 'name' => 'terms-condition'],

            ['id' => 111, 'name' => 'assign-class-to-new-student'],
            ['id' => 112, 'name' => 'exam-timetable-create'],
            ['id' => 113, 'name' => 'grade-create'],
            ['id' => 114, 'name' => 'update-admin-profile'],
            ['id' => 115, 'name' => 'exam-result'],

            ['id' => 116, 'name' => 'fees-type'],
            ['id' => 117, 'name' => 'fees-classes'],
            ['id' => 118, 'name' => 'fees-paid'],
            ['id' => 119, 'name' => 'fees-config'],

            ['id' => 120, 'name' => 'manage-online-exam'],

            ['id' => 121, 'name' => 'create-principal'],
            ['id' => 122, 'name' => 'principal-list'],
            ['id' => 123, 'name' => 'create-school'],
            ['id' => 124, 'name' => 'school-list'],

        ];
        foreach ($permissions as $permission) {
            Permission::UpdateOrCreate(['id' => $permission['id']], $permission);
        }

        $role = Role::updateOrCreate(['name' => 'Super Admin']);
        $superadmin_permission_list = [
            'medium-list',
            'section-list',

            'class-list',

            'subject-list',

            'teacher-list',
            'teacher-create',
            'teacher-edit',
            'teacher-delete',

            'class-teacher-list',
            'class-teacher-create',
            'class-teacher-edit',
            'class-teacher-delete',

            'parents-list',
            'parents-create',
            'parents-edit',
            'parents-delete',

            'session-year-list',
            'session-year-create',
            'session-year-edit',
            'session-year-delete',

            'student-list',
            'student-create',
            'student-edit',
            'student-delete',

            'category-list',
            'category-create',
            'category-edit',
            'category-delete',

            'subject-teacher-list',
            'subject-teacher-create',
            'subject-teacher-edit',
            'subject-teacher-delete',

            'timetable-list',
            'timetable-create',
            'timetable-edit',
            'timetable-delete',

            'attendance-list',

            'holiday-list',
            'holiday-create',
            'holiday-edit',
            'holiday-delete',

            'announcement-list',
            'announcement-create',
            'announcement-edit',
            'announcement-delete',

            'slider-list',
            'slider-create',
            'slider-edit',
            'slider-delete',

            'class-timetable',
            'teacher-timetable',
            'student-assignment',
            'subject-lesson',
            'class-attendance',

            'exam-create',
            'exam-list',
            'exam-edit',
            'exam-delete',
            'exam-timetable-create',
            'grade-create',

            'setting-create',
            'fcm-setting-create',

            'assignment-submission',

            'email-setting-create',
            'privacy-policy',
            'terms-condition',
            'contact-us',
            'about-us',

            'student-reset-password',
            'reset-password-list',
            'student-change-password',

            'promote-student-list',
            'promote-student-create',
            'promote-student-edit',
            'promote-student-delete',

            'assign-class-to-new-student',

            'language-list',
            'language-create',
            'language-edit',
            'language-delete',

            'update-admin-profile',

            'fees-type',
            'fees-classes',
            'fees-paid',
            'fees-config',

            'create-principal',
            'principal-list',
            'create-school',
            'school-list'
        ];
        $role->syncPermissions($superadmin_permission_list);


        // Principal Role
        $role = Role::updateOrCreate(['name' => 'Principal']);
        $principal_permission_set = [
            'medium-list',
            'medium-create',
            'medium-edit',
            'medium-delete',

            'section-list',
            'section-create',
            'section-edit',
            'section-delete',

            'class-list',
            'class-create',
            'class-edit',
            'class-delete',

            'subject-list',
            'subject-create',
            'subject-edit',
            'subject-delete',

            'teacher-list',
            'teacher-create',
            'teacher-edit',
            'teacher-delete',

            'class-teacher-list',
            'class-teacher-create',
            'class-teacher-edit',
            'class-teacher-delete',

            'parents-list',
            'parents-create',
            'parents-edit',
            'parents-delete',

            'session-year-list',
            'session-year-create',
            'session-year-edit',
            'session-year-delete',

            'student-list',
            'student-create',
            'student-edit',
            'student-delete',

            'category-list',
            'category-create',
            'category-edit',
            'category-delete',

            'subject-teacher-list',
            'subject-teacher-create',
            'subject-teacher-edit',
            'subject-teacher-delete',

            'timetable-list',
            'timetable-create',
            'timetable-edit',
            'timetable-delete',

            'attendance-list',

            'holiday-list',
            'holiday-create',
            'holiday-edit',
            'holiday-delete',

            'announcement-list',
            'announcement-create',
            'announcement-edit',
            'announcement-delete',

            'slider-list',
            'slider-create',
            'slider-edit',
            'slider-delete',

            'class-timetable',
            'teacher-timetable',
            'student-assignment',
            'subject-lesson',
            'class-attendance',

            'exam-create',
            'exam-list',
            'exam-edit',
            'exam-delete',
            'exam-timetable-create',
            'grade-create',

            'setting-create',
            'fcm-setting-create',

            'assignment-submission',

            'email-setting-create',
            'privacy-policy',
            'terms-condition',
            'contact-us',
            'about-us',

            'student-reset-password',
            'reset-password-list',
            'student-change-password',

            'promote-student-list',
            'promote-student-create',
            'promote-student-edit',
            'promote-student-delete',

            'assign-class-to-new-student',

            'language-list',
            'language-create',
            'language-edit',
            'language-delete',

            'update-admin-profile',

            'fees-type',
            'fees-classes',
            'fees-paid',
            'fees-config',
            'create-school',
            'school-list'
        ];
        $role->syncPermissions($principal_permission_set);


        //Add Teacher Role
        $teacher_role = Role::updateOrCreate(['name' => 'Teacher']);
        $teacher_permissions_list = [
            'student-list',
            'subject-teacher-list',
            'timetable-list',
            'attendance-list',
            'attendance-create',
            'attendance-edit',
            'attendance-delete',
            'holiday-list',
            'announcement-list',
            'announcement-create',
            'announcement-edit',
            'announcement-delete',
            'class-timetable',
            'teacher-timetable',
            'student-assignment',
            'subject-lesson',
            'class-attendance',
            'assignment-create',
            'assignment-list',
            'assignment-edit',
            'assignment-delete',
            'assignment-submission',
            'lesson-list',
            'lesson-create',
            'lesson-edit',
            'lesson-delete',
            'topic-list',
            'topic-create',
            'topic-edit',
            'topic-delete',
            'subject-teacher-list',
            'exam-upload-marks',
            'exam-result',
            'manage-online-exam'
        ];
        $teacher_role->syncPermissions($teacher_permissions_list);

        // Add Parent and Student Role
        Role::updateOrCreate(['name' => 'Parent']);
        Role::updateOrCreate(['name' => 'Student']);

        //Change system version here
        Settings::updateOrCreate(['type' => 'system_version'],['message'=>'1.0.7']);

        //clear cache
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            // Cache folders may not exist yet on a fresh install; nothing to clear.
        }
    }
}
