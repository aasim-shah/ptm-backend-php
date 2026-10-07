<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\ClassQuarter;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamClass;
use App\Models\ExamMarks;
use App\Models\ExamTimetable;
use App\Models\Quarters;
use App\Models\SessionYear;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use App\Services\NotificationSender;
use Carbon\Carbon;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TeacherController extends Controller
{
    private NotificationSender $notificationSender;

    /**
     * @param NotificationSender $notificationSender
     */
    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function reportCount(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        $class_id = $request->class_id;
        $student_id = $request->student_id;
        $quarter_id = $request->quarter_id;

//        $stuAllRepoCards = StudentReportCards::where('class_id',$class_id)
//            ->where('student_id',$student_id)
//            ->groupBy('quarter_id')->get();
//
//        $stuPublishedRepoCards = StudentQuarter::where('status',"PUBLISHED")
//            ->where('student_id',$student_id)
//            ->whereHas('quarter',function ($query) use($class_id,$student_id){
//                $query->whereHas('studentReportCards',function ($query) use($class_id,$student_id){
//                    $query->where('class_id', $class_id);
//                    $query->where('student_id',$student_id);
//                });
//            })
//            ->with(['quarter' => function ($query) use($class_id,$student_id) {
//            $query->withCount(['studentReportCards' => function ($query) use ($class_id,$student_id) {
//                $query->where('class_id', $class_id);
//                $query->where('student_id',$student_id);
//            }]);
//        }])->count();

        $stuAllRepoCardsClassWise = StudentReportCards::where('class_id',$class_id)
            ->where('student_id',$student_id)
            ->whereHas('quarter',function ($query) use ($quarter_id){
                if($quarter_id){
                    $query->where('quarter_id',$quarter_id);
                }
            })
            ->with('user','subject','quarter')->paginate(10);

        $response = array(
            'error' => false,
            'message' => "Report summery fetched",
            'data' => $stuAllRepoCardsClassWise,
            'other' => null,
        );

        return response()->json($response);

    }

    public function ClassReportCount(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        $class_id = $request->class_id;
        $quarter_id = $request->quarter_id;
        $session_year_id = $request->session_year_id;
        $status = $request->status;

        $classQuarters = ClassQuarter::where('class_id',$class_id)
            ->whereHas('quarter',function ($query) use($quarter_id){
                if($quarter_id){
                    $query->where('id',$quarter_id);
                }
            })
            ->where(function ($query) use ($session_year_id,$status){
                if($session_year_id){
                    $query->where('session_year_id',$session_year_id);
                }
                if($status && $status != "ALL"){
                    $query->where('status',$status);
                }
            })
            ->whereHas('quarter.studentReportCards.user')
            ->with('quarter')->paginate(10);


        $pendingClassQuarters = ClassQuarter::where('class_id',$class_id)
            ->whereHas('quarter',function ($query) use($quarter_id){
                if($quarter_id){
                    $query->where('id',$quarter_id);
                }
            })
            ->where(function ($query) use ($session_year_id,$status){
                if($session_year_id){
                    $query->where('session_year_id',$session_year_id);
                }
            })
            ->whereHas('quarter.studentReportCards.user')
            ->where('status','PENDING')->count();

        $publishedClassQuarters = ClassQuarter::where('class_id',$class_id)
            ->whereHas('quarter',function ($query) use($quarter_id){
                if($quarter_id){
                    $query->where('id',$quarter_id);
                }
            })
            ->where(function ($query) use ($session_year_id,$status){
                if($session_year_id){
                    $query->where('session_year_id',$session_year_id);
                }
            })
            ->whereHas('quarter.studentReportCards.user')
            ->where('status','PUBLISHED')->count();

        $response = array(
            'error' => false,
            'message' => "Report summery fetched",
            'data' => [
                'pending' => $pendingClassQuarters,
                'published' => $publishedClassQuarters,
                'data' => $classQuarters
            ]
        );

        return response()->json($response);

    }
    public function ClassSubjectReportCount(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        $class_id = $request->class_id;
        $subject_id = $request->subject_id;
        $quarter_id = $request->quarter_id;
        $student_name = $request->student_name;
        $session_year_id = $request->session_year_id;

        if($student_name){
            $stuAllRepoCardsClassWise = StudentReportCards::where('class_id',$class_id)
                ->where('subject_id',$subject_id)
                ->where('quarter_id',$quarter_id)
                ->where('session_year_id',$session_year_id)
                ->whereHas('user',function ($query) use ($student_name){
                    $query->where('name', 'LIKE', "%$student_name%");
                })
                ->with('user.student.father')
                ->with('user.student.mother')
                ->groupBy('quarter_id','student_id','subject_id','session_year')->paginate(10);
        }else {
            $stuAllRepoCardsClassWise = StudentReportCards::where('class_id', $class_id)
                ->where('subject_id', $subject_id)
                ->where('quarter_id', $quarter_id)
                ->where('session_year_id',$session_year_id)
                ->with('user.student.father')
                ->with('user.student.mother')
                ->groupBy('quarter_id', 'student_id', 'subject_id', 'session_year')->paginate(10);
        }

        $response = array(
            'error' => false,
            'message' => "Report summery fetched",
            'data' => $stuAllRepoCardsClassWise
        );

        return response()->json($response);

    }
    public function createExam(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        $validator = Validator::make($request->all(), [
            'class_id' => 'required',
            'name' => 'required',
            'session_year_id' => 'required',
            'description' => 'nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        try {
            $exam = new Exam();
            $exam->name = $request->name;
            $exam->description = $request->description;
            $exam->session_year_id = $request->session_year_id;
            $exam->save();

            if ($request->class_id) {
                $exam_classes = [];
                foreach ($request->class_id as $class_id) {
                    $exam_classes[] = array(
                        'exam_id' => $exam->id,
                        'class_id' => $class_id,
                    );
                }
                ExamClass::insert($exam_classes);
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function timetable(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required',
            'class_id' => 'required',
            'timetable.*.passing_marks' => 'required|lte:timetable.*.total_marks',
            'timetable.*.end_time' => 'required|after:timetable.*.start_time',
            'timetable.*.date' => 'required|date',
        ],
            [
                'timetable.*.passing_marks.lte' => trans('passing_marks_should_less_than_or_equal_to_total_marks'),
                'timetable.*.end_time.after' => trans('end_time_should_be_greater_than_start_time')
            ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $session_year_id = Exam::with('session_year')->where('id', $request->exam_id)->pluck('session_year_id')->first();

            foreach ($request->timetable as $timetable) {
                $date = date('Y-m-d', strtotime($timetable['date']));
                $exam_timetable[] = array(
                    'exam_id' => $request->exam_id,
                    'class_id' => $request->class_id,
                    'subject_id' => $timetable['subject_id'],
                    'total_marks' => $timetable['total_marks'],
                    'passing_marks' => $timetable['passing_marks'],
                    'start_time' => $timetable['start_time'],
                    'end_time' => $timetable['end_time'],
                    'date' => $date,
                    'session_year_id' => $session_year_id
                );
            }
            ExamTimetable::insert($exam_timetable);
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function submitMarks(Request $request)
    {
        if ($request->user()->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'exam_marks.*.student_id' => 'required|numeric',
            'exam_marks.*.obtained_marks' => 'required|numeric|lte:exam_marks.*.total_marks',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        try {
            $teacher_id = $request->user()->id;
            $class_id = ClassSection::where('class_teacher_id', $teacher_id)->pluck('class_id')->first();

            $exam_timetable = ExamTimetable::where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->where('subject_id', $request->subject_id)->firstOrFail();

            foreach ($request->exam_marks as $exam_marks) {
                $passing_marks = $exam_timetable->passing_marks;
                if ($exam_marks['obtained_marks'] >= $passing_marks) {
                    $status = 1;
                } else {
                    $status = 0;
                }
                $marks_percentage = ($exam_marks['obtained_marks'] / $exam_marks['total_marks']) * 100;
                $exam_grade = findExamGrade($marks_percentage);

                if ($exam_grade == null) {
                    $response = array(
                        'error' => true,
                        'message' => trans('grades_data_does_not_exists'),
                    );
                    return response()->json($response);
                }

                ExamMarks::updateOrInsert(
                    ['id' => isset($exam_marks['exam_marks_id']) ? $exam_marks['exam_marks_id'] : null],
                    ['exam_timetable_id' => $exam_timetable->id, 'student_id' => $exam_marks['student_id'], 'subject_id' => $request->subject_id, 'obtained_marks' => $exam_marks['obtained_marks'], 'passing_status' => $status, 'session_year_id' => $exam_timetable->session_year_id, 'grade' => $exam_grade,]
                );
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function quarters()
    {
        return response()->json(
            array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'quarters' => Quarters::orderBy('name', 'ASC')->get()
            )
        );

    }

    public function addStudentReport(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        if ($request->user()->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $user = $request->user();
        $userID = $user->id;
        $validator = Validator::make($request->all(), [
            'quarter_id' => 'required',
            'reports.*.grade' => 'nullable',
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'session_year_id' => 'required|numeric',
            'reports.*.student_id' => 'required',
            'reports.*.points' => 'nullable|numeric',
            'reports.*.review' => 'nullable',
            'reports.*.missing_cause' => 'nullable'
        ]);
        $class_id = $request->class_id;

        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if (!$classSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSubject = Subject::where('id', $request->subject_id)->first();
        if (!$isSubject) {
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => null
            );
            return response()->json($response);
        }
        $studentIds = array_column($request->reports, 'student_id');
        $isClassStudents = User::where('type', 'student')->whereIn('id', $studentIds)
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->count();

        if (sizeof($studentIds) != $isClassStudents) {
            $response = array(
                'error' => true,
                'message' => "Some of provided students are invalid."
            );
            return response()->json($response);
        }

        $sessionYear = SessionYear::where('id', $request->session_year_id)->first();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $array = array();
        $allStudents = [];
        try {
            DB::beginTransaction();

            $classQuarter = ClassQuarter::where('quarter_id',$request->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->where('class_id', $class_id)
                ->first();
            if(!$classQuarter){
                $classQuarter = new ClassQuarter();
                $classQuarter->id = Utility::getUUID();
                $classQuarter->quarter_id = $request->quarter_id;
                $classQuarter->class_id = $request->class_id;
                $classQuarter->status = "PENDING";
                $classQuarter->session_year = $sessionYear->name;
                $classQuarter->session_year_id = $sessionYear->id;
                $classQuarter->save();
                $classQuarter = $classQuarter->refresh();
            }

            $canPublish = false;
            foreach ($request->reports as $report) {
                $deleted =  StudentReportCards::where('class_id', $class_id)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('subject_id', $request->subject_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('student_id', $report['student_id'])
                    ->delete();

                $array [] = [
                    "id" => Utility::getUUID(),
                    "quarter_id" => $request->quarter_id,
                    "class_id" => $class_id,
                    "grade" => $report['grade'],
                    "session_year" => $sessionYear->name,
                    "session_year_id" => $request->session_year_id,
                    "subject_id" => $request->subject_id,
                    "student_id" => $report['student_id'],
                    "points" => $report['points'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    "review" => $report['review'] ?? null,
                    "missing_cause" => $report['missing_cause'] ?? null
                ];
            }

            $result = StudentReportCards::insert($array);

            $allStudents = User::where('type', 'student')
                ->whereHas('student', function ($query) use ($userID, $class_id) {
                    $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                        $query->where('class_id', $class_id);
                    });
                })->pluck('id')->toArray();

            $publishedStudents = StudentQuarter::where('quarter_id', $request->quarter_id)
                ->where('class_id',$class_id)
                ->where('session_year_id', $request->session_year_id)
                ->pluck('student_id')->toArray();
            $unPublishedStudents = array_diff($allStudents, $publishedStudents);
            $newStudentQuarter = array();

            $notificationUsers =[];
            $quarters = Quarters::where('id',$request->quarter_id)->first();

            $classSubjectsSql = ClassSubject::where('class_id', $request->class_id);
            $classSubjectsCount = $classSubjectsSql->count();
            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();

            $missing_cause_count = 0;
            foreach ($classSubjects as $classSubject){
                $subjectNotEmptyReports = StudentReportCards::where('session_year_id',$request->session_year_id)
                    ->where('quarter_id',$request->quarter_id)
                    ->where('subject_id',$classSubject)
                    ->where('class_id',$request->class_id)
                    ->where('missing_cause',null)
                    ->where('points',null)
                    ->first();

                if(!$subjectNotEmptyReports){
                    $missing_cause_count = $missing_cause_count+1;
                }
            }

            if($missing_cause_count == $classSubjectsCount){
                $canPublish = true;
            }
            foreach ($unPublishedStudents as $unPublishedStudent) {
                $existingReportCardCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('class_id', $class_id)
                    ->count();

                $missingReasonsCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('missing_cause', '!=',null)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('class_id', $class_id)
                    ->count();


                if ($existingReportCardCount == $classSubjectsCount &&  $canPublish) {

                    $newStudentQuarter [] = [
                        'id' => Utility::getUUID(),
                        'quarter_id' => $request->quarter_id,
                        'student_id' => $unPublishedStudent,
                        'class_id' => $class_id,
                        'session_year_id' => $request->session_year_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'status' => 'PUBLISHED',
                    ];

                    $student = Students::with('user')->where('user_id',$unPublishedStudent)->first();

//                    $studentUser [] = User::where('id',$unPublishedStudent)
//                        ->with('student.class_section.class.school')
//                        ->with(['student' => function($query){
//                            $query->withCount('student_subjects');
//                        }])
//                        ->first();

                    $logOwnId = $student->mother_id ?? $student->father_id;
                    if($missingReasonsCount!=$classSubjectsCount){
                        $notificationUsers[] = $logOwnId;
                    }
                    $classQuarter->status = 'PUBLISHED';
                    $classQuarter->save();
                }
            }
            if(sizeof($notificationUsers)>0){
                $message = "A new report has been published for ".$quarters->name;

                $notificationUsers = array_unique($notificationUsers);

                $this->notificationSender->sendNotification(
                    "Report Published",
                    $message,
                    $notificationUsers, "REPORT");
                foreach ($notificationUsers as $notificationUser){
                    $this->notificationSender->saveLogs($notificationUser,$user->id,$message,'REPORT','Report Published');
                }
                $notificationUsers = [];
            }
            StudentQuarter::insert($newStudentQuarter);

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
                'report' => $result
            );
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'report' => null
            );
        }

        return response()->json(
            $response
        );
    }

    public function deleteStudentReport(Request $request, $id){

        $classQuarter = ClassQuarter::where('id',$id)->first();
        if ($classQuarter && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $classQuarter->class_id)) {
            return Access::forbidden();
        }
        if($classQuarter){
            $sessionYear = SessionYear::where('name', $classQuarter->session_year)->first();

            StudentQuarter::where('quarter_id',$classQuarter->quarter_id)
                ->where('class_id',$classQuarter->class_id)
                ->where('session_year_id',$sessionYear->id)
                ->delete();

            StudentReportCards::where('class_id', $classQuarter->class_id)
                ->where('quarter_id', $classQuarter->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->delete();

            $deleted = ClassQuarter::where('id',$id)->delete();
            if($deleted){
                $response = array(
                    'error' => false,
                    'message' => "Class quarter successfully deleted",
                    'data' => $deleted
                );
            }else{
                $response = array(
                    'error' => true,
                    'message' => "Failed to delete class quarter!",
                    'data' => $deleted
                );
            }
            return response()->json($response);
        }
        $response = array(
            'error' => true,
            'message' => "Failed to delete class quarter!",
            'data' => null
        );
        return response()->json($response);
    }

    public function editStudentReport(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        if ($request->user()->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'quarter_id' => 'required',
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'session_year_id' => 'required|numeric',
            'reports.*.student_id' => 'required',
            'reports.*.points' => 'nullable|numeric',
            'reports.*.review' => 'nullable',
            'reports.*.missing_cause' => 'nullable',
            'reports.*.grade' => 'nullable'
        ]);
        $class_id = $request->class_id;
        $userID = $request->user()->id;

        $classSection = ClassSection::where('class_id', $class_id)->first();
        if (!$classSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSubject = Subject::where('id', $request->subject_id)->first();
        if (!$isSubject) {
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => null
            );
            return response()->json($response);
        }

        $studentIds = array_column($request->reports, 'student_id');
        $isClassStudents = User::where('type', 'student')->whereIn('id', $studentIds)
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->count();

        if (sizeof($studentIds) != $isClassStudents) {
            $response = array(
                'error' => true,
                'message' => "Some of provided students are invalid."
            );
            return response()->json($response);
        }

        $sessionYear = SessionYear::where('id', $request->session_year_id)->first();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $array = array();
        try {
            DB::beginTransaction();
            $canPublish = false;

            foreach ($request->reports as $report) {
               $deleted = StudentReportCards::where('class_id', $request->class_id)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('subject_id', $request->subject_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('student_id', $report['student_id'])
                    ->delete();

                $array [] = [
                    "id" => Utility::getUUID(),
                    "quarter_id" => $request->quarter_id,
                    "class_id" => $request->class_id,
                    "grade" => $report['grade'],
                    "session_year" => $sessionYear->name,
                    "session_year_id" => $request->session_year_id,
                    "subject_id" => $request->subject_id,
                    "student_id" => $report['student_id'],
                    "points" => $report['points'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    "review" => $report['review'] ?? null,
                    "missing_cause" => $report['missing_cause'] ?? null
                ];

            }
            $result = StudentReportCards::insert($array);

            $allStudents = User::where('type', 'student')
                ->whereHas('student', function ($query) use ($userID, $class_id) {
                    $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                        $query->where('class_id', $class_id);
                    });
                })->pluck('id')->toArray();

            $publishedStudents = StudentQuarter::where('quarter_id', $request->quarter_id)
                ->where('class_id',$class_id)
                ->where('session_year_id', $request->session_year_id)
                ->pluck('student_id')->toArray();

            $unPublishedStudents = array_diff($allStudents, $publishedStudents);
            $newStudentQuarter = array();

            $classQuarter = ClassQuarter::where('quarter_id',$request->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->where('class_id', $class_id)
                ->first();
            $notificationUsers = [];
            $quarters = Quarters::where('id',$request->quarter_id)->first();
            $classSubjectsSql = ClassSubject::where('class_id', $request->class_id);
            $classSubjectsCount = $classSubjectsSql->count();
            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();

            $missing_cause_count = 0;
            foreach ($classSubjects as $classSubject){
                $subjectNotEmptyReports = StudentReportCards::where('session_year_id',$request->session_year_id)
                    ->where('quarter_id',$request->quarter_id)
                    ->where('subject_id',$classSubject)
                    ->where('class_id',$request->class_id)
                     ->where('missing_cause',null)
                     ->where('points',null)
                    ->first();
                if(!$subjectNotEmptyReports){
                    $missing_cause_count = $missing_cause_count+1;
                }
            }

            if($missing_cause_count == $classSubjectsCount){
                $canPublish = true;
            }

            foreach ($unPublishedStudents as $unPublishedStudent) {
                $existingReportCardCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('class_id',$class_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('quarter_id', $request->quarter_id)->count();

                $missingReasonsCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('class_id',$class_id)
                    ->where('missing_cause', '!=',null)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('quarter_id', $request->quarter_id)->count();


                if ($existingReportCardCount == $classSubjectsCount && $canPublish) {
                    $newStudentQuarter [] = [
                        'id' => Utility::getUUID(),
                        'quarter_id' => $request->quarter_id,
                        'student_id' => $unPublishedStudent,
                        'created_at' => Carbon::now(),
                        'class_id' => $class_id,
                        'session_year_id' => $request->session_year_id,
                        'updated_at' => Carbon::now(),
                        'status' => 'PUBLISHED',
                    ];
                    $student = Students::with('user')->where('user_id',$unPublishedStudent)->first();
                    $message = "A new report has been published for ".$quarters->name;

                    $logOwnId = $student->mother_id ?? $student->father_id;
                    if($missingReasonsCount!=$classSubjectsCount){
                        $notificationUsers[] = $logOwnId;
                    }
                    $classQuarter->status = 'PUBLISHED';
                    $classQuarter->save();
                }
            }
            if(sizeof($notificationUsers)>0){
                $message = "A new report has been published for ".$quarters->name;

                $notificationUsers = array_unique($notificationUsers);
                $this->notificationSender->sendNotification(
                    "Report Published",
                    $message,
                    $notificationUsers, "REPORT");
                foreach ($notificationUsers as $notificationUser){
                    $this->notificationSender->saveLogs($notificationUser,$userID,$message,'REPORT','Report Published');
                }
                $notificationUsers = [];
            }
            StudentQuarter::insert($newStudentQuarter);

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
                'report' => $result
            );
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'report' => null
            );
        }

        return response()->json(
            $response
        );
    }
}