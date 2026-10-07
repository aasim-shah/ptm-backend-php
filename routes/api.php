<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ParentApiController;
use App\Http\Controllers\Api\StudentApiController;
use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('logout', [ApiController::class, 'logout']);
});


Route::any('/auth/register', [AuthController::class, 'createUser'])->middleware('throttle:10,1');
Route::any('/auth/signout', [AuthController::class, 'signout'])->middleware('auth:sanctum');
Route::any('/auth/fcm', [AuthController::class, 'fcm'])->middleware('auth:sanctum');
Route::any('/auth/details', [AuthController::class, 'details'])->middleware('auth:sanctum');
Route::any('/auth/update', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::any('/auth/settings', [AuthController::class, 'settings'])->middleware('auth:sanctum');
Route::any('/auth/update/profile', [AuthController::class, 'updateUser'])->middleware('auth:sanctum');
Route::any('/auth/get/profile', [AuthController::class, 'getProfile'])->middleware('auth:sanctum');
Route::any('/auth/login', [AuthController::class, 'loginUser'])->middleware('throttle:10,1');
Route::any('add/school', [ApiController::class, 'postSchool'])->middleware('auth:sanctum');
Route::get('get/teachers', [ApiController::class, 'getTeachers'])->middleware('auth:sanctum');
Route::any('search/user', [ApiController::class, 'searchjParentTeacher'])->middleware('auth:sanctum');
Route::get('get/parents', [ApiController::class, 'getParents'])->middleware('auth:sanctum');
Route::any('add/meetings', [ApiController::class, 'postMeeting'])->middleware('auth:sanctum');
Route::any('get/meetings', [ApiController::class, 'getMeetings'])->middleware('auth:sanctum');
Route::any('add/meetings-v2', [ApiController::class, 'postMeetingV2'])->middleware('auth:sanctum');
Route::any('get/meetings-v2', [ApiController::class, 'getMeetingsV2'])->middleware('auth:sanctum');
Route::any('send/pincode', [ApiController::class, 'sendPincode'])->middleware('auth:sanctum');
Route::any('forgot/password', [ApiController::class, 'forgotPassword1'])->middleware('throttle:5,1');
Route::any('reset/password', [ApiController::class, 'resetpassword'])->middleware('auth:sanctum');
Route::any('verify/pincode', [ApiController::class, 'verifyPincode'])->middleware('auth:sanctum');
Route::any('get/status/meetings', [ApiController::class, 'getMeeting'])->middleware('auth:sanctum');
Route::any('search/meetings', [ApiController::class, 'searchMeetings'])->middleware('auth:sanctum');
Route::any('update/meetings', [ApiController::class, 'updateMeeting'])->middleware('auth:sanctum');
Route::any('get/schools', [ApiController::class, 'getSchools'])->middleware('auth:sanctum');
Route::any('get/countries', [ApiController::class, 'getCountries'])->middleware('auth:sanctum');

Route::post('user/schools', [AuthController::class, 'userSchools'])->middleware('auth:sanctum');
Route::get('mediums', [ApiController::class, 'medium']);
Route::get('sections', [ApiController::class, 'sections'])->middleware('auth:sanctum');
Route::get('states/cities/country/{id}', [\App\Http\Controllers\Api\v2\CountryController::class, 'statesAndCities']);
Route::get('get/teacher/schools', [ApiController::class, 'getTeacherSchools'])->middleware('auth:sanctum');
Route::get('subjects', [\App\Http\Controllers\Api\v2\SubjectController::class, 'subjects'])->middleware('auth:sanctum');

Route::post('student/assign/class', [\App\Http\Controllers\Api\v2\StudentController::class, 'assignStudentToClassSection'])->middleware('auth:sanctum');
Route::delete('student/remove/class', [\App\Http\Controllers\Api\v2\StudentController::class, 'removeStudentFromClassSection'])->middleware('auth:sanctum');
Route::get('students', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentList'])->middleware('auth:sanctum');

Route::delete('auth/delete', [\App\Http\Controllers\Api\AuthController::class, 'deleteUser'])->middleware('auth:sanctum');
Route::post('auth/rate', [\App\Http\Controllers\Api\AuthController::class, 'store'])->middleware('auth:sanctum');
Route::get('auth/announcement', [\App\Http\Controllers\Api\v2\AnnouncementController::class, 'announcement'])->middleware('auth:sanctum');

Route::get('get/schools-details', [\App\Http\Controllers\Api\ApiController::class, 'getSchool'])->middleware('auth:sanctum');


Route::get('students/find', [\App\Http\Controllers\Api\v2\StudentController::class, 'findStudent'])->middleware('auth:sanctum');
Route::delete('parent/student', [\App\Http\Controllers\Api\v2\StudentController::class, 'deleteStudent'])->middleware('auth:sanctum');

Route::get('notifications', [\App\Http\Controllers\Api\v2\NotificationController::class, 'notificationLogs'])->middleware('auth:sanctum');
Route::post('notifications-read', [\App\Http\Controllers\Api\v2\NotificationController::class, 'notificationRead'])->middleware('auth:sanctum');
Route::post('chat/send', [\App\Http\Controllers\Api\v2\ChatController::class, 'createChat'])->middleware('auth:sanctum');
Route::post('chat/read', [\App\Http\Controllers\Api\v2\ChatController::class, 'chatRead'])->middleware('auth:sanctum');

Route::get('student-attendance', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentAttendance'])->middleware('auth:sanctum');


Route::post('plan/{id}/agreement/create', [\App\Http\Controllers\Payment\SubscriptionController::class, 'createAgreement'])->middleware('auth:sanctum');
Route::post('subscription-cancel/{subscription_id}', [\App\Http\Controllers\Payment\SubscriptionController::class, 'cancelSubscription'])->middleware('auth:sanctum');
Route::get('plans', [\App\Http\Controllers\Payment\SubscriptionController::class, 'listPlan']);
Route::get('active/subscription', [\App\Http\Controllers\Payment\SubscriptionController::class, 'activeSubscription'])->middleware('auth:sanctum');
Route::post('plan/trail', [\App\Http\Controllers\Payment\SubscriptionController::class, 'activateTrail'])->middleware('auth:sanctum');


Route::post('join/call', [\App\Http\Controllers\MeetingController::class, 'makeCallRoom'])->middleware('auth:sanctum');
Route::post('left/call', [\App\Http\Controllers\MeetingController::class, 'leftFormCallRoom'])->middleware('auth:sanctum');
Route::post('channel/token', [\App\Http\Controllers\MeetingController::class, 'channelToken'])->middleware('auth:sanctum');



/**
 * STUDENT APIs
 **/
Route::group(['prefix' => 'student'], function () {

    //Non Authenticated APIs
    Route::post('login', [StudentApiController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [StudentApiController::class, 'forgotPassword'])->middleware('throttle:5,1');

    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:student']], function () {
        Route::get('subjects', [StudentApiController::class, 'subjects']);
        Route::get('class-subjects', [StudentApiController::class, 'classSubjects']);
        Route::post('select-subjects', [StudentApiController::class, 'selectSubjects']);
        Route::get('parent-details', [StudentApiController::class, 'getParentDetails']);
        Route::get('timetable', [StudentApiController::class, 'getTimetable']);
        Route::get('lessons', [StudentApiController::class, 'getLessons']);
        Route::get('lesson-topics', [StudentApiController::class, 'getLessonTopics']);
        Route::get('assignments', [StudentApiController::class, 'getAssignments']);
        Route::post('submit-assignment', [StudentApiController::class, 'submitAssignment']);
        Route::post('delete-assignment-submission', [StudentApiController::class, 'deleteAssignmentSubmission']);
        Route::get('attendance', [StudentApiController::class, 'getAttendance']);
        Route::get('announcements', [StudentApiController::class, 'getAnnouncements']);
        Route::get('get-exam-list', [StudentApiController::class, 'getExamList']); // Exam list Route
        Route::get('get-exam-details', [StudentApiController::class, 'getExamDetails']); // Exam Details Route
        Route::get('exam-marks', [StudentApiController::class, 'getExamMarks']); // Exam Details Route

        // online exam routes
        Route::get('get-online-exam-list', [StudentApiController::class, 'getOnlineExamList']); // Get Online Exam List Route
        Route::get('get-online-exam-questions', [StudentApiController::class, 'getOnlineExamQuestions']); // Get Online Exam Questions Route
        Route::post('submit-online-exam-answers', [StudentApiController::class, 'submitOnlineExamAnswers']); // Submit Online Exam Answers Details Route
        Route::get('get-online-exam-result-list', [StudentApiController::class, 'getOnlineExamResultList']); // Online exam result list Route
        Route::get('get-online-exam-result', [StudentApiController::class, 'getOnlineExamResult']); // Online exam result  Route

        //reports
        Route::get('get-online-exam-report', [StudentApiController::class, 'getOnlineExamReport']); // Online Exam Report Route
        Route::get('get-assignments-report', [StudentApiController::class, 'getAssignmentReport']); // Assignment Report Route

        // profile data
        Route::get('get-profile-data', [StudentApiController::class, 'getProfileDetails']); // Get Profile Data

    });
});

/**
 * PARENT APIs
 **/
Route::group(['prefix' => 'parent'], function () {
    //Non Authenticated APIs
    Route::post('login', [ParentApiController::class, 'login'])->middleware('throttle:10,1');
    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:parent']], function () {

        //APIS Without Child ID
        Route::get('announcements', [ParentApiController::class, 'getAnnouncements']); //Get Announcementes
        Route::get('fees-paid-receipt-pdf', [ParentApiController::class, 'feesPaidReceiptPDF']); //Fees Receipt
        Route::get('fees-transactions-list', [ParentApiController::class, 'getFeesPaymentTransactions']); //Fees Payment Transaction Details
        Route::get('get-profile-data', [ParentApiController::class, 'getProfileDetails']); // Get Profile Data
        Route::post('fail-payment-transaction', [ParentApiController::class, 'failPaymentTransactionStatus']); // Make Payment Transaction Fail API
        Route::post('student/create', [\App\Http\Controllers\Api\v2\StudentController::class, 'createStudent']);
        Route::post('student/update', [\App\Http\Controllers\Api\v2\StudentController::class, 'updateStudent']);
        Route::get('schools', [\App\Http\Controllers\Api\v2\StudentController::class, 'parentSchools']);

        
        Route::get('children-list', [ParentApiController::class, 'noOfChildren']);

        Route::get('school/teachers', [\App\Http\Controllers\Api\v2\StudentController::class, 'schoolTeacher']);
        Route::get('classes/teacher/fetch', [\App\Http\Controllers\Api\v2\ClassController::class, 'getTeacherClasses']);
        Route::get('student/report', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentReport']);
        Route::get('student/teachers', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentTeachers']);

        Route::get('class/subjects', [\App\Http\Controllers\Api\v2\ClassController::class, 'subjects2']);


        Route::group(['middleware' => ['auth:sanctum', 'checkChild']], function () {

            Route::get('subjects', [ParentApiController::class, 'subjects']);
            Route::get('class-subjects', [ParentApiController::class, 'classSubjects']);
            Route::get('timetable', [ParentApiController::class, 'getTimetable']);
            Route::get('lessons', [ParentApiController::class, 'getLessons']);
            Route::get('lesson-topics', [ParentApiController::class, 'getLessonTopics']);
            Route::get('assignments', [ParentApiController::class, 'getAssignments']);
            Route::get('attendance', [ParentApiController::class, 'getAttendance']);
            // Route::get('announcements', [ParentApiController::class, 'getAnnouncements']);
            Route::get('teachers', [ParentApiController::class, 'getTeachers']);
            Route::get('get-exam-list', [ParentApiController::class, 'getExamList']); // Exam list Route
            Route::get('get-exam-details', [ParentApiController::class, 'getExamDetails']); // Exam Details Route
            Route::get('exam-marks', [ParentApiController::class, 'getExamMarks']); //Exam Marks

            //fees
            Route::get('fees-details', [ParentApiController::class, 'getFeesDetails']); //Fees Details
            Route::post('add-fees-transaction', [ParentApiController::class, 'storeFeesTransaction']); //Fees Details
            Route::post('store-fees', [ParentApiController::class, 'storeFees']); //Store Fees
            Route::get('fees-paid-list', [ParentApiController::class, 'feesPaidList']); //Fees Details

            // online exam routes
            Route::get('get-online-exam-list', [ParentApiController::class, 'getOnlineExamList']); // Get Online Exam List Route
            Route::get('get-online-exam-result-list', [ParentApiController::class, 'getOnlineExamResultList']); // Online exam result list Route
            Route::get('get-online-exam-result', [ParentApiController::class, 'getOnlineExamResult']); // Online exam result  Route

            //reports
            Route::get('get-online-exam-report', [ParentApiController::class, 'getOnlineExamReport']); // Online Exam Report Route
            Route::get('get-assignments-report', [ParentApiController::class, 'getAssignmentReport']); // Assignment Report Route

        });
        Route::get('chat/{to_user?}', [\App\Http\Controllers\Api\v2\ChatController::class, 'teacherChat']);
        Route::post('class/students/requests/cancel', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudentsRequestsCancel']);

    });
});

/**
 * TEACHER APIs
 **/
Route::group(['prefix' => 'teacher'], function () {
    //Non Authenticated APIs
    Route::post('login', [TeacherApiController::class, 'login'])->middleware('throttle:10,1');
    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:teacher,principal']], function () {
        Route::get('classes', [TeacherApiController::class, 'classes']);

        Route::get('class/subjects', [\App\Http\Controllers\Api\v2\ClassController::class, 'subjects']);

        //Assignment
        Route::get('get-assignment', [TeacherApiController::class, 'getAssignment']);
        Route::post('create-assignment', [TeacherApiController::class, 'createAssignment']);
        Route::post('update-assignment', [TeacherApiController::class, 'updateAssignment']);
        Route::post('delete-assignment', [TeacherApiController::class, 'deleteAssignment']);

        //Assignment Submission
        Route::get('get-assignment-submission', [TeacherApiController::class, 'getAssignmentSubmission']);
        Route::post('update-assignment-submission', [TeacherApiController::class, 'updateAssignmentSubmission']);

        //File
        Route::post('delete-file', [TeacherApiController::class, 'deleteFile']);
        Route::post('update-file', [TeacherApiController::class, 'updateFile']);

        //Lesson
        Route::get('get-lesson', [TeacherApiController::class, 'getLesson']);
        Route::post('create-lesson', [TeacherApiController::class, 'createLesson']);
        Route::post('update-lesson', [TeacherApiController::class, 'updateLesson']);
        Route::post('delete-lesson', [TeacherApiController::class, 'deleteLesson']);

        //Topic
        Route::get('get-topic', [TeacherApiController::class, 'getTopic']);
        Route::post('create-topic', [TeacherApiController::class, 'createTopic']);
        Route::post('update-topic', [TeacherApiController::class, 'updateTopic']);
        Route::post('delete-topic', [TeacherApiController::class, 'deleteTopic']);

        //Announcement
        Route::get('get-announcement', [TeacherApiController::class, 'getAnnouncement']);
        Route::post('send-announcement', [TeacherApiController::class, 'sendAnnouncement']);
        Route::post('update-announcement', [TeacherApiController::class, 'updateAnnouncement']);
        Route::post('delete-announcement', [TeacherApiController::class, 'deleteAnnouncement']);

        Route::get('get-attendance', [TeacherApiController::class, 'getAttendance']);
        Route::post('submit-attendance', [TeacherApiController::class, 'submitAttendance']);


        //Exam
        Route::get('get-exam-list', [TeacherApiController::class, 'getExamList']); // Exam list Route
        Route::get('get-exam-details', [TeacherApiController::class, 'getExamDetails']); // Exam Details Route
        Route::post('submit-exam-marks/subject', [TeacherApiController::class, 'submitExamMarksBySubjects']); // Submit Exam Marks By Subjects Route
        Route::post('submit-exam-marks/student', [TeacherApiController::class, 'submitExamMarksByStudent']); // Submit Exam Marks By Students Route

        Route::group(['middleware' => ['auth:sanctum', 'checkStudent']], function () {
            Route::get('get-student-result', [TeacherApiController::class, 'GetStudentExamResult']); // Student Exam Result
            Route::get('get-student-marks', [TeacherApiController::class, 'GetStudentExamMarks']); // Student Exam Marks
        });

        //Student List
        Route::get('student-list', [TeacherApiController::class, 'getStudentList']);
        Route::get('student-details', [TeacherApiController::class, 'getStudentDetails']);

        //Schedule List
        Route::get('teacher_timetable', [TeacherApiController::class, 'getTeacherTimetable']);

        //Profile Detials
        Route::get('get-profile-details', [TeacherApiController::class, 'getProfileDetails']);

        Route::post('class/create', [ApiController::class, 'createClass']);
        Route::get('class/students', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudents']);
        Route::post('school-sections', [\App\Http\Controllers\Api\v2\SchoolController::class, 'schoolSection']);
        Route::get('classes/fetch', [\App\Http\Controllers\Api\v2\ClassController::class, 'getTeacherClassList']);
        Route::post('subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'create']);
        Route::post('assign/subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'assign']);
        Route::post('update/subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'update']);
        Route::post('mark-attendance', [\App\Http\Controllers\Api\v2\AttendanceController::class,'attendance']);
        Route::post('edit-attendance', [\App\Http\Controllers\Api\v2\AttendanceController::class,'editAttendance']);
        Route::get('view-attendance/class', [\App\Http\Controllers\Api\v2\AttendanceController::class,'viewAttendance']);

        Route::post('create/exam', [\App\Http\Controllers\Api\v2\TeacherController::class,'createExam']);
        Route::post('create/exam/timetable', [\App\Http\Controllers\Api\v2\TeacherController::class,'timetable']);
        Route::post('create/student-report', [\App\Http\Controllers\Api\v2\TeacherController::class,'addStudentReport']);
        Route::delete('delete/student-report/{id}', [\App\Http\Controllers\Api\v2\TeacherController::class,'deleteStudentReport']);
        Route::post('update/student-report', [\App\Http\Controllers\Api\v2\TeacherController::class,'editStudentReport']);


        Route::get('fetch-student-class', [TeacherApiController::class, 'getStudentsListForClass']);
        Route::get('student/parents', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentParents']);
        Route::get('class/parents', [\App\Http\Controllers\Api\ParentApiController::class, 'classParents']);
        Route::get('reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'reportCount']);
        Route::get('class/reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'ClassReportCount']);
        Route::get('class/subject/reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'ClassSubjectReportCount']);


        Route::get('chat/{to_user?}', [\App\Http\Controllers\Api\v2\ChatController::class, 'parentChat']);

        Route::get('class/students/requests', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudentsRequests']);
        Route::post('student/accept', [\App\Http\Controllers\Api\v2\StudentController::class, 'acceptOrRejectStudentFromClassSection'])->middleware('auth:sanctum');
        Route::delete('class/delete', [\App\Http\Controllers\Api\ApiController::class, 'deleteClass'])->middleware('auth:sanctum');
        Route::delete('subject/delete', [\App\Http\Controllers\Api\ApiController::class, 'subjectDelete'])->middleware('auth:sanctum');

    });

});

/**
 * GENERAL APIs
 **/
Route::get('holidays', [ApiController::class, 'getHolidays']);
Route::get('quarters', [\App\Http\Controllers\Api\v2\TeacherController::class, 'quarters']);
Route::get('sliders', [ApiController::class, 'getSliders']);
Route::get('current-session-year', [ApiController::class, 'getSessionYear']);
Route::get('settings', [ApiController::class, 'getSettings']);
Route::post('forgot-password', [ApiController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('verify-otp', [ApiController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('set-password', [ApiController::class, 'changePasswordAfterOtp'])->middleware('throttle:10,1');

Route::group(['middleware' => ['auth:sanctum',]], function () {
    Route::post('change-password', [ApiController::class, 'changePassword']);
});
