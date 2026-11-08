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
use App\Models\SubjectTeacher;
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
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 403);
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
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 403);
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
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 403);
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
                ->with('user.student.guardian')
                ->groupBy('quarter_id','student_id','subject_id','session_year')->paginate(10);
        }else {
            $stuAllRepoCardsClassWise = StudentReportCards::where('class_id', $class_id)
                ->where('subject_id', $subject_id)
                ->where('quarter_id', $quarter_id)
                ->where('session_year_id',$session_year_id)
                ->with('user.student.father')
                ->with('user.student.mother')
                ->with('user.student.guardian')
                ->groupBy('quarter_id', 'student_id', 'subject_id', 'session_year')->paginate(10);
        }
        foreach ($stuAllRepoCardsClassWise as $reportCard) {
            $reportCard->user?->setAttribute('primary_parent', $reportCard->user->student?->primaryParentSummary());
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
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 403);
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
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 403);
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
        if (\App\Support\Access::role($request->user()) != 'teacher') {
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
        return $this->saveStudentReports($request);
    }

    public function editStudentReport(Request $request)
    {
        return $this->saveStudentReports($request);
    }

    /**
     * Publishes the class quarter by hand: every student with at least one subject filled in
     * (points or a missing cause) is published, even if other subjects are still empty.
     * Running it again only publishes students who were not published before.
     */
    public function publishStudentReport(Request $request)
    {
        if ($request->class_id && !Access::principalOwnsClass($request->user(), $request->class_id)
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        if (\App\Support\Access::role($request->user()) != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|numeric',
            'quarter_id' => 'required',
            'session_year_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => null
            );
            return response()->json($response);
        }

        $classQuarter = ClassQuarter::where('quarter_id', $request->quarter_id)
            ->where('session_year_id', $request->session_year_id)
            ->where('class_id', $request->class_id)
            ->first();
        $hasMarks = StudentReportCards::where('class_id', $request->class_id)
            ->where('quarter_id', $request->quarter_id)
            ->where('session_year_id', $request->session_year_id)
            ->where(function ($query) {
                $query->whereNotNull('points')->orWhereNotNull('missing_cause');
            })->exists();
        if (!$classQuarter || !$hasMarks) {
            $response = array(
                'error' => true,
                'message' => "No report marks found for this class and quarter.",
                'data' => null
            );
            return response()->json($response);
        }

        try {
            DB::beginTransaction();
            $published = $this->publishReadyStudents($classQuarter, $request->user(), true);
            DB::commit();
            $response = array(
                'error' => false,
                'message' => "Report published successfully",
                'data' => [
                    'class_quarter_id' => $classQuarter->id,
                    'status' => $classQuarter->status,
                    'published_students' => sizeof($published),
                ]
            );
        } catch (\Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => null
            );
        }
        return response()->json($response);
    }

    public function deleteStudentReport(Request $request, $id){

        $classQuarter = ClassQuarter::where('id',$id)->first();
        if ($classQuarter && !Access::principalOwnsClass($request->user(), $classQuarter->class_id)
            && !Access::teacherOwnsClass($request->user(), $classQuarter->class_id)) {
            return Access::forbidden();
        }
        if($classQuarter){
            // Older rows may only carry the session year name.
            $sessionYearId = $classQuarter->session_year_id
                ?? SessionYear::where('name', $classQuarter->session_year)->value('id');
            if (!$sessionYearId) {
                $response = array(
                    'error' => true,
                    'message' => "Failed to delete class quarter! Its session year could not be found.",
                    'data' => null
                );
                return response()->json($response);
            }

            try {
                $deleted = DB::transaction(function () use ($classQuarter, $sessionYearId, $id) {
                    StudentQuarter::where('quarter_id',$classQuarter->quarter_id)
                        ->where('class_id',$classQuarter->class_id)
                        ->where('session_year_id',$sessionYearId)
                        ->delete();

                    StudentReportCards::where('class_id', $classQuarter->class_id)
                        ->where('quarter_id', $classQuarter->quarter_id)
                        ->where('session_year_id', $sessionYearId)
                        ->delete();

                    return ClassQuarter::where('id',$id)->delete();
                });
            } catch (\Exception $exception) {
                Log::error($exception);
                $deleted = 0;
            }
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

    /**
     * Create and update share this: saves one row per student and subject, then publishes
     * every student whose report is now complete.
     */
    private function saveStudentReports(Request $request)
    {
        $user = $request->user();
        $class_id = $request->class_id;
        // Class teachers and principals may save any subject; subject teachers only their own.
        $ownsClass = $class_id && (Access::principalOwnsClass($user, $class_id) || Access::teacherOwnsClass($user, $class_id));
        $taughtSubjectIds = $class_id && !$ownsClass ? $this->taughtSubjectIds($user, $class_id) : collect();
        if ($class_id && !$ownsClass && $taughtSubjectIds->isEmpty()) {
            return Access::forbidden();
        }
        Log::info($request);
        if (\App\Support\Access::role($user) != 'teacher') {
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
            // The app sends subject_id on each report item; the top-level one is optional.
            'subject_id' => 'required_without:reports.0.subject_id|nullable|numeric',
            'reports' => 'required|array',
            'reports.*.subject_id' => 'nullable|numeric',
            'session_year_id' => 'required|numeric',
            'reports.*.student_id' => 'required',
            'reports.*.points' => 'nullable|numeric|between:0,100',
            'reports.*.review' => 'nullable',
            'reports.*.missing_cause' => 'nullable',
            'reports.*.grade' => 'nullable'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        $sessionYear = SessionYear::where('id', $request->session_year_id)->first();
        if (!$sessionYear) {
            $response = array(
                'error' => true,
                'message' => "Invalid session year detected.",
                'data' => null
            );
            return response()->json($response);
        }
        $quarter = Quarters::where('id', $request->quarter_id)->first();
        if (!$quarter) {
            $response = array(
                'error' => true,
                'message' => "Invalid quarter detected.",
                'data' => null
            );
            return response()->json($response);
        }
        $classSection = ClassSection::where('class_id', $class_id)->first();
        if (!$classSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }

        $subjectIds = collect($request->reports)
            ->map(fn ($report) => $report['subject_id'] ?? $request->subject_id)
            ->push($request->subject_id)->filter()->unique()->values();
        if ($subjectIds->isEmpty() || $subjectIds->diff($this->classSubjectIds($class_id))->isNotEmpty()) {
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected. Every subject must be assigned to this class.",
                'data' => null
            );
            return response()->json($response);
        }
        if (!$ownsClass && $subjectIds->diff($taughtSubjectIds)->isNotEmpty()) {
            $response = array(
                'error' => true,
                'message' => "You can only save marks for the subjects you teach in this class.",
                'data' => null
            );
            return response()->json($response);
        }

        // The same student appears once per subject.
        $studentIds = array_values(array_unique(array_column($request->reports, 'student_id')));
        $isClassStudents = User::where('type', 'student')->whereIn('id', $studentIds)
            ->whereHas('student', function ($query) use ($class_id) {
                $query->whereHas('class_section', function ($query) use ($class_id) {
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
                $classQuarter->class_id = $class_id;
                $classQuarter->status = "PENDING";
                $classQuarter->session_year = $sessionYear->name;
                $classQuarter->session_year_id = $sessionYear->id;
                $classQuarter->save();
                $classQuarter = $classQuarter->refresh();
            }

            $array = array();
            foreach ($request->reports as $report) {
                $subjectId = $report['subject_id'] ?? $request->subject_id;
                StudentReportCards::where('class_id', $class_id)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('subject_id', $subjectId)
                    ->where('session_year_id', $sessionYear->id)
                    ->where('student_id', $report['student_id'])
                    ->delete();

                // A blank row (no points and no missing cause) clears that subject instead of storing it.
                $key = $report['student_id'] . '|' . $subjectId;
                if (($report['points'] ?? null) === null && ($report['missing_cause'] ?? null) === null) {
                    unset($array[$key]);
                    continue;
                }
                $array[$key] = [
                    "id" => Utility::getUUID(),
                    "quarter_id" => $request->quarter_id,
                    "class_id" => $class_id,
                    // The server derives the grade so every app version agrees.
                    "grade" => self::gradeForPoints($report['points'] ?? null) ?? ($report['grade'] ?? null),
                    "session_year" => $sessionYear->name,
                    "session_year_id" => $sessionYear->id,
                    "subject_id" => $subjectId,
                    "student_id" => $report['student_id'],
                    "points" => $report['points'] ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    "review" => $report['review'] ?? null,
                    "missing_cause" => $report['missing_cause'] ?? null
                ];
            }

            $result = StudentReportCards::insert(array_values($array));

            $this->publishReadyStudents($classQuarter, $user, false, $studentIds);

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

    /**
     * Publishes the students of a class quarter whose report is ready and notifies their parents.
     *
     * A student is ready when every subject of the class has a row with points or a missing cause;
     * with $manual, one such row is enough. Students published earlier stay published, and the
     * parents of those in $updatedStudentIds are told the report changed. The class quarter turns
     * PUBLISHED once every student is published (or on a manual publish) and never goes back.
     *
     * @return array the student user ids published by this call
     */
    private function publishReadyStudents(ClassQuarter $classQuarter, User $actor, bool $manual = false, array $updatedStudentIds = []): array
    {
        $class_id = $classQuarter->class_id;
        $classSubjectIds = $this->classSubjectIds($class_id);
        $classStudentIds = User::where('type', 'student')
            ->whereHas('student', function ($query) use ($class_id) {
                $query->whereHas('class_section', function ($query) use ($class_id) {
                    $query->where('class_id', $class_id);
                });
            })->pluck('id')->map(fn ($id) => (int) $id)->all();

        $filledRows = StudentReportCards::where('class_id', $class_id)
            ->where('quarter_id', $classQuarter->quarter_id)
            ->where('session_year_id', $classQuarter->session_year_id)
            ->where(function ($query) {
                $query->whereNotNull('points')->orWhereNotNull('missing_cause');
            })
            ->get(['student_id', 'subject_id', 'points'])
            ->groupBy('student_id');

        $publishedStudents = StudentQuarter::where('quarter_id', $classQuarter->quarter_id)
            ->where('class_id', $class_id)
            ->where('session_year_id', $classQuarter->session_year_id)
            ->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        $newStudentQuarter = [];
        $newlyPublished = [];
        $notifyStudents = [];
        foreach (array_diff($classStudentIds, $publishedStudents) as $studentId) {
            $rows = $filledRows->get($studentId, collect());
            $ready = $manual
                ? $rows->isNotEmpty()
                : $classSubjectIds->isNotEmpty() && $classSubjectIds->diff($rows->pluck('subject_id'))->isEmpty();
            if (!$ready) {
                continue;
            }
            $newlyPublished[] = $studentId;
            $newStudentQuarter[] = [
                'id' => Utility::getUUID(),
                'quarter_id' => $classQuarter->quarter_id,
                'student_id' => $studentId,
                'class_id' => $class_id,
                'session_year_id' => $classQuarter->session_year_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'status' => 'PUBLISHED',
            ];
            // No notice when every filled subject is only a missing cause.
            if ($rows->whereNotNull('points')->isNotEmpty()) {
                $notifyStudents[] = $studentId;
            }
        }
        StudentQuarter::insert($newStudentQuarter);

        $allPublished = !empty($classStudentIds)
            && empty(array_diff($classStudentIds, array_merge($publishedStudents, $newlyPublished)));
        if ($classQuarter->status != 'PUBLISHED' && ($manual || $allPublished)) {
            $classQuarter->status = 'PUBLISHED';
            $classQuarter->save();
        }

        $quarterName = Quarters::where('id', $classQuarter->quarter_id)->value('name');
        $this->notifyParents($notifyStudents, $actor, "Report Published",
            "A new report has been published for " . $quarterName);
        $this->notifyParents(array_intersect(array_map('intval', $updatedStudentIds), $publishedStudents), $actor,
            "Report Updated", "The report for " . $quarterName . " has been updated");

        return $newlyPublished;
    }

    private function notifyParents(array $studentIds, User $actor, string $title, string $message): void
    {
        if (empty($studentIds)) {
            return;
        }
        $parentIds = Students::whereIn('user_id', $studentIds)->get()
            ->map(fn ($student) => $student->primaryParentId())
            ->filter()->unique()->values()->all();
        if (empty($parentIds)) {
            return;
        }
        $this->notificationSender->sendNotification($title, $message, $parentIds, "REPORT");
        foreach ($parentIds as $parentId) {
            $this->notificationSender->saveLogs($parentId, $actor->id, $message, 'REPORT', $title);
        }
    }

    /** Subject ids assigned to the class (live class subjects of subjects that are not deleted). */
    private function classSubjectIds($classId)
    {
        return ClassSubject::where('class_id', $classId)
            ->whereNull('deleted_at')
            ->whereHas('subject')
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** Subject ids the user teaches in any section of the class. */
    private function taughtSubjectIds(User $user, $classId)
    {
        return SubjectTeacher::where('teacher_id', $user->id)
            ->whereHas('class_section', function ($query) use ($classId) {
                $query->where('class_id', $classId);
            })
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * The grading scale shared with the app (Utils.gradeForMarks):
     * A >= 75, B 61-74, C 51-60, S 41-50, F <= 40. Null when there are no points.
     */
    public static function gradeForPoints($points): ?string
    {
        if ($points === null || $points === '' || !is_numeric($points)) {
            return null;
        }
        $p = (float) $points;
        if ($p >= 75) return 'A';
        if ($p > 60) return 'B';
        if ($p > 50) return 'C';
        if ($p > 40) return 'S';
        return 'F';
    }
}
