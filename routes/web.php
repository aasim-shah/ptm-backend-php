<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\V2\PrincipalController;
use App\Models\Grade;
use App\Models\ExamTimetable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MediumController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ParentsController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeesTypeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassSchoolController;
use App\Http\Controllers\LessonTopicController;
use App\Http\Controllers\SessionYearController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ClassTeacherController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\OnlineExamController;
use App\Http\Controllers\OnlineExamQuestionController;
use App\Http\Controllers\StudentSessionController;
use App\Http\Controllers\SubjectTeacherController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Admin panel authentication. Only /login-web may sign users in: it checks the
// user's role. Laravel's default POST /login and /register are intentionally not registered.
Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::namespace('App\Http\Controllers')->group(function () {
    Route::resetPassword();
});
Route::get('/', [HomeController::class, 'login']);
Route::post('/login-web', [\App\Http\Controllers\Auth\LoginController::class, 'loginWeb'])->name('loginWeb')->middleware('throttle:10,1');

Route::group(['middleware' => ['Role', 'auth']], function () {
    Route::get('/agora-chat', 'App\Http\Controllers\MeetingController@index');
    Route::get('/chat-teacher/{to_user?}', 'App\Http\Controllers\MeetingController@teacherChat')->name("t_chat");
    Route::get('/chat-parent/{to_user?}', 'App\Http\Controllers\MeetingController@parentChat')->name("p_chat");
    Route::get('/calendar/{id?}', 'App\Http\Controllers\MeetingController@calendarIndex')->name("calendar");
    Route::post('/agora/token', 'App\Http\Controllers\MeetingController@token');
    Route::post('/agora/call-user', 'App\Http\Controllers\MeetingController@callUser');
    Route::any('get/meetings/{status?}', [\App\Http\Controllers\MeetingController::class, 'getMeetings'])->name('meetings');
    Route::post('add/meeting', [\App\Http\Controllers\MeetingController::class, 'createMeeting'])->name('create_meeting');
    Route::get('teacher/{id}/classes', [\App\Http\Controllers\StudentController::class, 'teacherClasses'])->name('teacher_classes');
    Route::get('class/{id}/parents', [\App\Http\Controllers\StudentController::class, 'classParents'])->name('class_parents');
    Route::get('class/{id}/students', [\App\Http\Controllers\StudentController::class, 'classStudents'])->name('class_students');
    Route::get('meeting-list/view', [\App\Http\Controllers\MeetingController::class, 'meetingListView'])->name('meeting_list_view');
    Route::get('meeting-list', [\App\Http\Controllers\MeetingController::class, 'meetingList'])->name('meeting_list');
    Route::any('update/meetings', [\App\Http\Controllers\MeetingController::class, 'updateMeeting'])->name('update_meeting');
    Route::get('meetingDetails/{id}', [\App\Http\Controllers\MeetingController::class, 'meetingDetails'])->name('meeting_details');


    Route::group(['middleware' => 'language'], function () {
        Route::get('/', [HomeController::class, 'index']);
        Route::get('home', [HomeController::class, 'index'])->name('home');
        Route::get('/logout', [HomeController::class, 'logout'])->name('logout');
        Route::post('/logout', [HomeController::class, 'logout']);
        Route::get('subject-by-class-section', [HomeController::class, 'getSubjectByClassSection'])->name('class-section.by.subject');
        Route::get('teacher-by-class-subject', [HomeController::class, 'getTeacherByClassSubject'])->name('teacher.by.class.subject');

        Route::get('home/reset_password', [HomeController::class, 'resetPasswordView']);


        Route::resource('roles', RoleController::class);
        Route::resource('users', UserController::class);

        Route::get('settings', [SettingController::class, 'index']);
        Route::post('settings', [SettingController::class, 'update']);

        Route::get('fcm-settings', [SettingController::class, 'fcm_index']);

        Route::resource('medium', MediumController::class)->except(['create', 'edit']);
        Route::get('medium_list', [MediumController::class, 'show']);

        Route::resource('teachers', TeacherController::class)->except(['create']);
        Route::get('teacher_list/{school_id?}', [TeacherController::class, 'show'])->name('teacher_list');

        Route::resource('section', SectionController::class)->except(['create', 'edit']);
        Route::get('section_list', [SectionController::class, 'show']);

        Route::get('class/subject', [ClassSchoolController::class, 'subject'])->name('class.subject');
        Route::put('class/subject/{class_id}', [ClassSchoolController::class, 'update_subjects'])->name('class.subject.update');
        Route::delete('class/subject/{class_subject_id}', [ClassSchoolController::class, 'subject_destroy'])->name('class.subject.delete');
        Route::delete('class/subject-group/{group_id}', [ClassSchoolController::class, 'subject_group_destroy'])->name('class.subject-group.delete');
        Route::get('class/subject/list', [ClassSchoolController::class, 'subject_list'])->name('class.subject.list');
        Route::get('class-list', [ClassSchoolController::class, 'show']);
        Route::resource('class', ClassSchoolController::class)->except(['create', 'edit']);

        Route::get('class-subject-list/{medium_id}', [ClassSchoolController::class, 'getSubjectsByMediumId']);

        Route::get('assign/class/teacher', [ClassTeacherController::class, 'teacher'])->name('class.teacher');
        Route::post('class/teacher/store', [ClassTeacherController::class, 'assign_teacher'])->name('class.teacher.store');
        Route::get('class-teacher-list', [ClassTeacherController::class, 'show']);
        Route::post('remove-class-teacher/{id}', [ClassTeacherController::class, 'removeClassTeacher']);

        Route::resource('subject', SubjectController::class)->except(['create', 'edit']);
        Route::get('subject-list', [SubjectController::class, 'show']);

        Route::get('/parent/search', [ParentsController::class, 'search']);
        Route::resource('parents', ParentsController::class)->only(['index', 'show', 'update']);
        Route::get('parents-details/{id}', [ParentsController::class,'details_view'])->name('parent_details_view');
        Route::get('teacher-details/{id}', [TeacherController::class,'details_view'])->name('teacher_details_view');

        Route::get('parents_list/{school_id?}', [ParentsController::class, 'show'])->name('parent.show');

        Route::resource('session-years', SessionYearController::class)->except(['create']);
        Route::get('session_years_list', [SessionYearController::class, 'show']);
        Route::delete('remove-installment-data/{id}',[SessionYearController::class, 'deleteInstallmentData']);

        Route::get('students-list', [StudentController::class, 'show'])->name('students.list');
        Route::get('students/assign-class', [StudentController::class, 'assignClass'])->name('students.assign-class');
        Route::post('students/assign-class', [StudentController::class, 'assignClass_store'])->name('students.assign-class.store');
        Route::get('students/new-student-list', [StudentController::class, 'newStudentList'])->name('students.new-student-list');
        Route::get('students/create_bulk', [StudentController::class, 'createBulkData'])->name('students.create-bulk-data');
        Route::post('students/store_bulk', [StudentController::class, 'storeBulkData'])->name('students.store-bulk-data');
        Route::resource('students', StudentController::class)->except(['edit']);

        //student generate roll number
        Route::get('student/assign-roll-number',[StudentController::class, 'indexStudentRollNumber'])->name('students.index-students-roll-number');
        Route::get('student/list-assign-roll-number',[StudentController::class, 'listStudentRollNumber'])->name('students.list-students-roll-number');
        Route::post('student/store-roll-number',[StudentController::class, 'storeStudentRollNumber'])->name('students.store-roll-number');

        Route::resource('category', CategoryController::class)->except(['create']);
        Route::get('category_list', [CategoryController::class, 'show']);

        Route::resource('subject-teachers', SubjectTeacherController::class)->except(['create']);
        Route::get('subject-teachers-list', [SubjectTeacherController::class, 'show']);

        Route::resource('timetable', TimetableController::class)->only(['index', 'store', 'destroy']);
        Route::get('checkTimetable', [TimetableController::class, 'checkTimetable']);

        Route::get('get-subject-by-class-section', [TimetableController::class, 'getSubjectByClassSection']);
        Route::get('getteacherbysubject', [TimetableController::class, 'getteacherbysubject']);

        Route::get('gettimetablebyclass', [TimetableController::class, 'gettimetablebyclass'])->name('get.timetable.class');
        Route::get('gettimetablebyteacher', [TimetableController::class, 'gettimetablebyteacher']);
        Route::get('get-timetable-by-subject-teacher-class', [TimetableController::class, 'getTimetableBySubjectTeacherClass']);

        Route::get('class-timetable', [TimetableController::class, 'class_timetable']);
        Route::get('teacher-timetable', [TimetableController::class, 'teacher_timetable']);

        Route::resource('attendance', AttendanceController::class)->only(['index', 'store', 'show']);
        Route::get('view-attendance', [AttendanceController::class, 'view'])->name("attendance.view");
        Route::get('student-attendance-list', [AttendanceController::class, 'attendance_show']);
        Route::get('getAttendanceData', [AttendanceController::class, 'getAttendanceData']);

        Route::get('student-list', [AttendanceController::class, 'show']);

        Route::resource('lesson', LessonController::class)->except(['create', 'edit']);
        Route::get('search-lesson', [LessonController::class, 'search']);
        Route::delete('file/delete/{id}', [LessonController::class, 'deleteFile'])->name('file.delete');
        Route::resource('lesson-topic', LessonTopicController::class)->except(['create', 'edit']);

        Route::resource('announcement', AnnouncementController::class)->except(['create', 'edit']);
        Route::get('announcement-list', [AnnouncementController::class, 'show']);
        Route::get('getAssignData', [AnnouncementController::class, 'getAssignData']);

        Route::resource('holiday', HolidayController::class)->except(['create', 'edit']);
        Route::get('holiday-list', [HolidayController::class, 'show']);
        Route::get('holiday-view', [HolidayController::class, 'holiday_view']);

        Route::resource('assignment', AssignmentController::class)->except(['create', 'edit']);
        Route::get('assignment-submission', [AssignmentController::class, 'viewAssignmentSubmission'])->name('assignment.submission');
        Route::put('assignment-submission/{id}', [AssignmentController::class, 'updateAssignmentSubmission'])->name('assignment.submission.update');
        Route::get('assignment-submission-list', [AssignmentController::class, 'assignmentSubmissionList'])->name('assignment.submission.list');

        Route::resource('sliders', SliderController::class)->except(['create', 'edit']);

        Route::get('exams/exam-result', [ExamController::class, 'getExamResultIndex'])->name('exams.get-result');
        Route::get('exams/show-result', [ExamController::class, 'showExamResult'])->name('exams.show-result');
        Route::post('exams/update-result-marks', [ExamController::class, 'updateExamResultMarks'])->name('exams.update-result-marks');

        Route::post('exams/submit-marks', [ExamController::class, 'submitMarks'])->name('exams.submit-marks');

        Route::get('exams/upload-marks', [ExamController::class, 'uploadMarks'])->name('exams.upload-marks');
        Route::get('exams/marks-list', [ExamController::class, 'marksList'])->name('exams.marks-list');

        Route::get('exams/get-subjects/{exam_id}', [ExamController::class, 'getSubjectByExam'])->name('exams.subject');
        Route::post('exams/publish/{id}', [ExamController::class, 'publishExamResult'])->name('exams.publish');
        Route::resource('exams', ExamController::class)->except(['create', 'edit']);

        Route::post('exams/update-timetable', [ExamTimetableController::class, 'updateTimetable'])->name('exams.update-timetable');
        Route::delete('exams/delete-timetable/{id}', [ExamTimetableController::class, 'deleteTimetable'])->name('exams.delete-timetable');
        Route::get('grades', [ExamController::class, 'indexGrades'])->name('grades');

        Route::get('exams/get-exam-subjects/{exam_id}', [ExamController::class, 'getExamSubjects'])->name('exams.subjects');

        Route::post('create-grades', [ExamController::class, 'createGrades'])->name('create-grades');
        Route::delete('destroy-grades/{grade_id}', [ExamController::class, 'destroyGrades'])->name('destroy-grades');

        Route::resource('exam-timetable', ExamTimetableController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::get('exam/get-classes/{exam_id}', [ExamTimetableController::class, 'getClassesByExam'])->name('exams.classes');
        Route::get('exam/get-subjects/{class_id}', [ExamTimetableController::class, 'getSubjectsByClass'])->name('exams.class-subjects');

        Route::get('email-settings', [SettingController::class, 'email_index'])->name('setting.email-config-index');
        Route::post('email-settings', [SettingController::class, 'email_update']);
        Route::post('verify-email-settings', [SettingController::class, 'verifyEmailConfigration'])->name('setting.varify-email-config');

        Route::get('privacy-policy', [SettingController::class, 'privacy_policy_index']);
        Route::get('terms-condition', [SettingController::class, 'terms_condition_index']);
        Route::get('contact-us', [SettingController::class, 'contact_us_index']);
        Route::get('about-us', [SettingController::class, 'about_us_index']);

        Route::post('setting-update', [SettingController::class, 'setting_page_update']);

        Route::get('reset-password', function () {
            return view('students.reset_password');
        })->name('students.reset_password');
        Route::get('reset-password-list', [StudentController::class, 'reset_password']);
        Route::post('student-change-password', [StudentController::class, 'change_password']);

        Route::resource('promote-student', StudentSessionController::class)->only(['index', 'store', 'show']);
        Route::get('getPromoteData', [StudentSessionController::class, 'getPromoteData']);
        Route::get('promote-student-list', [StudentSessionController::class, 'show']);

        Route::get('resetpassword', [HomeController::class, 'resetpassword'])->name('resetpassword');
        Route::get('checkPassword', [HomeController::class, 'checkPassword']);
        Route::post('changePassword', [HomeController::class, 'changePassword'])->name('changePassword');

        Route::get('edit-profile', [HomeController::class, 'editProfile'])->name('edit-profile');
        Route::post('update-profile', [HomeController::class, 'updateProfile'])->name('update-profile');

        Route::resource('language', LanguageController::class)->except(['create', 'edit']);
        Route::get('language-sample', [LanguageController::class, 'language_sample']);
        Route::get('language-list', [LanguageController::class, 'show']);

        Route::get('set-language/{lang}', [LanguageController::class, 'set_language']);

        // fees
        Route::resource('fees-type', FeesTypeController::class)->except(['create', 'edit']);

        Route::get('fees/classes', [FeesTypeController::class, 'feesClassListIndex'])->name('fees.class.index');
        // Route::post('fees/classes/update', [FeesTypeController::class, 'updateFeesClass'])->name('fees.class.update');
        Route::get('fees/classes/list', [FeesTypeController::class, 'feesClassList'])->name('fees.class.list');


        Route::post('class/fees-type', [FeesTypeController::class, 'updateFeesClass'])->name('class.fees.type.update');
        Route::delete('class/fees-type/{fees_class_id}', [FeesTypeController::class, 'removeFeesClass'])->name('class.fees.type.delete');

        Route::get('fees/paid', [FeesTypeController::class, 'feesPaidListIndex'])->name('fees.paid.index');
        Route::get('fees/paid/list', [FeesTypeController::class, 'feesPaidList'])->name('fees.paid.list');

        Route::get('fees-config', [FeesTypeController::class, 'feesConfigIndex'])->name('fees.config.index');
        Route::post('fees-config/update', [FeesTypeController::class, 'feesConfigUpdate'])->name('fees.config.udpate');
        Route::delete('fees/paid/remove-choiceable-fees/{id}', [FeesTypeController::class, 'feesPaidRemoveChoiceableFees'])->name('fees.paid.remove.choiceable.fees');
        Route::delete('fees/paid/remove-installment-fees/{id}', [FeesTypeController::class, 'feesPaidRemoveInstallmentFees'])->name('fees.paid.remove.installment.fees');
        Route::delete('fees/paid/clear-data/{id}', [FeesTypeController::class, 'clearFeesPaidData'])->name('fees.paid.clear.data');

        Route::post('fees/optional-paid/store', [FeesTypeController::class, 'optionalFeesPaidStore'])->name('fees.optional-paid.store');
        Route::post('fees/compulsory-paid/store', [FeesTypeController::class, 'compulsoryFeesPaidStore'])->name('fees.compulsory-paid.store');

        Route::get('fees/transaction-logs', [FeesTypeController::class, 'feesTransactionsLogsIndex'])->name('fees.transactions.log.index');
        Route::get('fees/transaction-logs/list', [FeesTypeController::class, 'feesTransactionsLogsList'])->name('fees.transactions.log.list');

        Route::get('fees/paid/receipt-pdf/{id}', [FeesTypeController::class, 'feesPaidReceiptPDF'])->name('fees.paid.receipt.pdf');
        Route::get('fees/fees-receipt', function () {
            return view('fees.fees_receipt');
        })->name('fees.receipt');

        // //Pending Fees
        // Route::get('fees/fees-pending',[FeesTypeController::class,'feesPendingIndex'])->name('fees.pending.index');
        // Route::get('fees/fees-pending/list', [FeesTypeController::class, 'feesPendingList'])->name('fees.pending.list');

        // Online Exam
        Route::get('online-exam/terms-conditions',[OnlineExamController::class ,'onlineExamTermsConditionIndex'])->name('online-exam.terms-conditions');
        Route::post('online-exam/store-terms-conditions',[OnlineExamController::class ,'storeOnlineExamTermsCondition'])->name('online-exam.store-terms-conditions');

        Route::resource('online-exam', OnlineExamController::class)->except(['create', 'edit']);
        Route::post('online-exam/add-new-question',[OnlineExamController::class ,'storeExamQuestionChoices'])->name('online-exam.add-new-question');
        Route::get('online-exam/get-class-subject-questions/{online_exam_id}',[OnlineExamController::class ,'getClassSubjectQuestions'])->name('online-exam-question.get-class-subject-questions');
        Route::get('get-subject-online-exam',[OnlineExamController::class ,'getSubjects']);
        Route::get('get-exam-question-index',[OnlineExamController::class ,'examQuestionsIndex'])->name('exam.questions.index');
        Route::post('online-exam/store-questions-choices',[OnlineExamController::class ,'storeQuestionsChoices'])->name('online-exam.store-choice-question');
        Route::delete('online-exam/remove-choiced-question/{id}',[OnlineExamController::class ,'removeQuestionsChoices'])->name('online-exam.remove-choice-question');
        Route::get('online-exam/result/{id}', [OnlineExamController::class, 'onlineExamResultIndex'])->name('online-exam.result.index');
        Route::get('online-exam/result-show/{id}', [OnlineExamController::class, 'showOnlineExamResult'])->name('online-exam.result.show');

        Route::resource('online-exam-question', OnlineExamQuestionController::class)->except(['create', 'edit']);
        Route::delete('online-exam-question/remove-option/{id}', [OnlineExamQuestionController::class , 'removeOptions']);
        Route::delete('online-exam-question/remove-answer/{id}', [OnlineExamQuestionController::class , 'removeAnswers']);
        // End Online Exam Routes

        Route::get('app-settings', [SettingController::class, 'app_index']);
        Route::post('app-settings', [SettingController::class, 'app_update']);
        // Uploading a zip that overwrites application code is disabled unless explicitly enabled.
        Route::group(['middleware' => 'systemUpdate'], function () {
            Route::get('system-update', [SystemUpdateController::class, 'index'])->name('system-update.index');
            Route::post('system-update', [SystemUpdateController::class, 'update'])->name('system-update.update');
        });

        Route::get('update-warning-modal',[HomeController::class, 'updateWarningModal'])->name('update-warning-modal');
        Route::get('get-principal',[\App\Http\Controllers\V2\PrincipalController::class, 'index'])->name('principal.index');
        Route::post('principal/store', [PrincipalController::class, 'store'])->name('principal.store');
        Route::delete('principal-unlink/{user_id}/school/{school_id?}', [PrincipalController::class, 'unlink'])->name('principal.unlink');
        Route::delete('principal/{user_id}', [PrincipalController::class, 'remove'])->name('principal.remove');
        Route::get('principals', [PrincipalController::class, 'get'])->name('principals');
        Route::get('class-sections/school/{school_id}/{type?}', [ClassSchoolController::class, 'classSectionsBySchool'])->name('class-section-school');
        Route::get('schools-view', [\App\Http\Controllers\SchoolsController::class, 'schoolsView'])->name('school.index');
        Route::post('schools', [\App\Http\Controllers\SchoolsController::class, 'add'])->name('create-school');
        Route::get('schools-list', [\App\Http\Controllers\SchoolsController::class, 'schoolsList'])->name('schools');
        Route::put('update-schools/{id?}', [\App\Http\Controllers\SchoolsController::class, 'update'])->name('update-school');

        Route::post('save-token', [\App\Http\Controllers\FcmController::class, 'saveToken'])->name('save-token');
        Route::post('chat', [\App\Http\Controllers\FcmController::class, 'createChat'])->name('create-chat');
        Route::get('excel/teachers', [\App\Http\Controllers\V2\ExcelController::class, 'exportTeachers'])->name('export_teachers');
        Route::get('excel/parents', [\App\Http\Controllers\V2\ExcelController::class, 'exportParents'])->name('export_parents');
        Route::get('excel/students', [\App\Http\Controllers\V2\ExcelController::class, 'exportStudents'])->name('export_students');
        Route::get('excel/announcements', [\App\Http\Controllers\V2\ExcelController::class, 'exportAnnouncement'])->name('export_announcements');
        Route::get('excel/schools', [\App\Http\Controllers\V2\ExcelController::class, 'exportSchools'])->name('export_schools');
        Route::get('excel/principals', [\App\Http\Controllers\V2\ExcelController::class, 'exportPrincipals'])->name('export_principals');
        Route::get('push-notifications', [\App\Http\Controllers\V2\PushNotificationController::class, 'pushNotificationView'])->name('push-notifications');
        Route::post('send-notification', [\App\Http\Controllers\V2\PushNotificationController::class, 'sendNotification'])->name('send-notification');
        Route::get('class-users/{class_id}/{type}/{school_id}', [\App\Http\Controllers\V2\PushNotificationController::class, 'classUsers'])->name('class-users');
        Route::post('send-meeting-notification', [\App\Http\Controllers\V2\PushNotificationController::class, 'sendMeetingNotification'])->name('send-meeting-notification');

    });
});

// webhooks
Route::post('webhook/razorpay', [WebhookController::class, 'razorpay']);
Route::post('webhook/stripe', [WebhookController::class, 'stripe']);
Route::post('webhook/agora', [WebhookController::class, 'agora']);
Route::post('webhook/paypal/subscription-activated', [WebhookController::class, 'subscriptionActivated']);

Route::get('payment/success', [\App\Http\Controllers\Payment\SubscriptionController::class, 'paymentSuccess'])->name('payment-success');
Route::get('payment/failed', [\App\Http\Controllers\Payment\SubscriptionController::class, 'paymentFailed'])->name('payment-failed');
Route::get('plans', [\App\Http\Controllers\Payment\SubscriptionController::class, 'listPlan']);
Route::get('execute-agreement/{status}', [\App\Http\Controllers\Payment\SubscriptionController::class, 'executeAgreement'])->name('execute-agreement');

// PayPal plan administration: Super Admin only.
Route::group(['middleware' => ['auth', 'role:Super Admin']], function () {
    Route::get('plan/create', [\App\Http\Controllers\Payment\SubscriptionController::class, 'createPlan']);
    Route::get('plan/{id}', [\App\Http\Controllers\Payment\SubscriptionController::class, 'planDetails']);
    Route::get('plan/{id}/activate', [\App\Http\Controllers\Payment\SubscriptionController::class, 'activatePlan']);
    Route::get('subscription-list/{subscription_id}', [\App\Http\Controllers\Payment\SubscriptionController::class, 'subscriptionList'])->name('subscription-list');
});

Route::get('page/privacy-policy', function () {
    $settings = getSettings('privacy_policy');
    echo $settings['privacy_policy'] ?? '';
});
