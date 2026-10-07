<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserSchools;
use Throwable;
use Carbon\Carbon;
use App\Models\Exam;
use App\Models\Grade;
use Razorpay\Api\Api;
use App\Models\Lesson;
use App\Models\Holiday;
use App\Models\Parents;
use App\Models\FeesPaid;
use App\Models\Students;
use Stripe\StripeClient;
use App\Models\ExamClass;
use App\Models\ExamMarks;
use App\Models\FeesClass;
use App\Models\Timetable;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\OnlineExam;
use App\Models\ClassSchool;
use App\Models\LessonTopic;
use App\Models\SessionYear;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use Illuminate\Http\Request;
use App\Models\ExamTimetable;
use App\Models\FeesChoiceable;
use App\Models\InstallmentFee;
use App\Models\SubjectTeacher;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\PaidInstallmentFee;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\OnlineExamStudentAnswer;
use App\Models\StudentOnlineExamStatus;
use App\Models\OnlineExamQuestionAnswer;
use App\Models\OnlineExamQuestionChoice;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TimetableCollection;

class ParentApiController extends Controller
{
    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $auth = Auth::user();
            if ($request->fcm_id) {
                $auth->fcm_id = $request->fcm_id;
                $auth->save();
            }

            $token = $auth->createToken($auth->first_name)->plainTextToken;

            $user = $auth->load(['parent']);
            $children = Students::where('father_id', $user->parent->user_id)->orWhere('mother_id', $user->parent->user_id)->orWhere('guardian_id', $user->parent->user_id)->with('class_section')->get();
            $user = flattenMyModel($user);

            foreach ($children as $child) {
                $child->first_name = $child->user->first_name;
                $child->last_name = $child->user->last_name;
                $child->image = $child->user->image;
                unset($child->user);
                //Set Class Section name
                $child->class_section_name = $child->class_section->class->name . " " . $child->class_section->section->name;
                //Set Medium name
                $child->medium_name = null;
                unset($child->class_section);

                //Set Category name
                $child->category_name = $child->category->name;
                unset($child->category);
            }
            $data = array_merge($user, ['children' => $children->toArray()]);
            $response = array(
                'error' => false,
                'message' => 'User logged-in!',
                'token' => $token,
                'data' => $data,
                'code' => 100,
            );
            return response()->json($response, 200);
        } else {
            $response = array(
                'error' => true,
                'message' => 'Invalid Login Credentials',
                'code' => 101
            );
            return response()->json($response, 200);
        }
    }

    // public function getChildren(Request $request) {
    //     try {
    //         $user = $request->user();
    //         $children = $user->parent->children->load(['user:id,first_name,last_name']);
    //         $response = array(
    //             'error' => false,
    //             'message' => 'Children Fetched Successfully.',
    //             'data' => $children,
    //             'code' => 200,
    //         );
    //         return response()->json($response, 200);
    //     } catch (\Exception $e) {
    //         $response = array(
    //             'error' => true,
    //             'message' => trans('error_occurred'),
    //             'code' => 103,
    //         );
    //         return response()->json($response, 200);
    //     }
    // }

    public function subjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $subjects = $children->subjects();

            $response = array(
                'error' => false,
                'message' => 'Student Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }

    public function classSubjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $subjects = $children->classSubjects();
            $response = array(
                'error' => false,
                'message' => 'Class Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 103
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103
            );
            return response()->json($response, 200);
        }
    }

    public function getTimetable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $timetable = Timetable::where('class_section_id', $children->class_section_id)->with('subject_teacher')->orderBy('day', 'asc')->orderBy('start_time', 'asc')->get();
            $response = array(
                'error' => false,
                'message' => "Timetable Fetched Successfully",
                'data' => new TimetableCollection($timetable),
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    /**
     * @param
     * subject_id : 2
     * lesson_id : 1 //OPTIONAL
     */
    public function getLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'lesson_id' => 'nullable|numeric',
            'subject_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }

        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $data = Lesson::where('class_section_id', $children->class_section_id)->where('subject_id', $request->subject_id)->with('topic', 'file');
            if ($request->lesson_id) {
                $data->where('id', $request->lesson_id);
            }
            $data = $data->get();

            $response = array(
                'error' => false,
                'message' => "Lessons Fetched Successfully",
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    /**
     * @param
     * lesson_id : 1
     * topic_id : 1    //OPTIONAL
     */
    public function getLessonTopics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'lesson_id' => 'required|numeric',
            'topic_id' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }

        try {
            //Not Used Anywhere
            //            $user = $request->user();
            //            $children = $user->parent->children()->where('id',$request->child_id)->first();
            //            $subjects = $children->subjects();
            //

            $data = LessonTopic::where('lesson_id', $request->lesson_id)->with('file');
            if ($request->topic_id) {
                $data->where('id', $request->topic_id);
            }
            $data = $data->get();

            $response = array(
                'error' => false,
                'message' => "Topics Fetched Successfully",
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    /**
     * @param
     * assignment_id : 1    //OPTIONAL
     * subject_id : 1       //OPTIONAL
     */
    public function getAssignments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'assignment_id' => 'nullable|numeric',
            'subject_id' => 'nullable|numeric',
            'is_submitted' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }

        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();


            $data = Assignment::where('class_section_id', $children->class_section_id)->with('file', 'subject', 'submission.file');
            if ($request->assignment_id) {
                $data->where('id', $request->assignment_id);
            }
            if ($request->subject_id) {
                $data->where('subject_id', $request->subject_id);
            }
            if ($request->is_submitted) {
                if ($request->is_submitted == 1) {
                    $data->has('submission')->get();
                } else if ($request->is_submitted == 0) {
                    $data->has('submission', '<', 1)->get();
                }
            }

            $data = $data->orderBy('id', 'desc')->paginate();

            $response = array(
                'error' => false,
                'message' => "Assignments Fetched Successfully",
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    /**
     * @param
     * month : 4 //OPTIONAL
     * year : 2022 //OPTIONAL
     */
    public function getAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'month' => 'nullable|numeric',
            'year' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            $attendance = Attendance::where('student_id', $children->id)->where('session_year_id', $session_year_id);
            $holidays = new Holiday;
            $session_year_data = SessionYear::find($session_year_id);
            if (isset($request->month)) {
                $attendance = $attendance->whereMonth('date', $request->month);
                $holidays = $holidays->whereMonth('date', $request->month);
            }

            if (isset($request->year)) {
                $attendance = $attendance->whereYear('date', $request->year);
                $holidays = $holidays->whereYear('date', $request->year);
            }
            $attendance = $attendance->get();
            $holidays = $holidays->get();

            $response = array(
                'error' => false,
                'message' => "Attendance Details Fetched Successfully",
                'data' => ['attendance' => $attendance, 'holidays' => $holidays, 'session_year' => $session_year_data],
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getAnnouncements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:subject,noticeboard,class',
            'child_id' => 'required_if:type,subject,class|numeric',
            'subject_id' => 'required_if:type,subject|numeric'
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $children = null;
            if ($request->type !== "noticeboard") {
                $user = $request->user();
                $children = $user->parent->children()->where('id', $request->child_id)->first();
                if (empty($children)) {
                    $response = array(
                        'error' => true,
                        'message' => "Invalid Child  ID",
                        'code' => 106,
                    );
                    return response()->json($response);
                }
                $class_id = $children->class_section->class->id;
            }


            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];
            if (isset($request->type) && $request->type == "subject") {
                $table = SubjectTeacher::where('class_section_id', $children->class_section_id)->where('subject_id', $request->subject_id)->get()->pluck('id');
                if (empty($table)) {
                    $response = array(
                        'error' => true,
                        'message' => "Invalid Subject ID",
                        'code' => 106,
                    );
                    return response()->json($response);
                }
            }
            $data = Announcement::with('file')->where('session_year_id', $session_year_id);

            if (isset($request->type) && $request->type == "noticeboard") {
                $data = $data->where('table_type', "");
            }

            if (isset($request->type) && $request->type == "class") {
                $data = $data->where('table_type', "App\Models\ClassSchool")->where('table_id', $class_id);
            }

            if (isset($request->type) && $request->type == "subject") {
                $data = $data->where('table_type', "App\Models\SubjectTeacher")->whereIn('table_id', $table);
            }

            $data = $data->orderBy('id', 'desc')->paginate();
            $response = array(
                'error' => false,
                'message' => "Announcement Details Fetched Successfully",
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getTeachers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $user = $request->user();
            $children = $user->parent->children()->where('id', $request->child_id)->first();
            $subject_teachers = $children->class_section->subject_teachers->load(['subject:id,name', 'teacher.user']);
            $response = array(
                'error' => false,
                'message' => "Teacher Details Fetched Successfully",
                'data' => $subject_teachers,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getExamList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|nullable',
            'status' => 'nullable:digits:0,1,2,3'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::with('class_section')->where('id', $request->child_id)->first();
            $class_id = $student->class_section->class_id;
            $exam_data_db = ExamClass::with('exam.session_year:id,name')->where('class_id', $class_id)->get();

            foreach ($exam_data_db as $data) {

                // date status
                $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $data->exam->id, 'class_id' => $class_id])->first();
                $starting_date = $starting_date_db['min(date)'];
                $ending_date_db = ExamTimetable::select(DB::raw("max(date)"))->where(['exam_id' => $data->exam->id, 'class_id' => $class_id])->first();
                $ending_date = $ending_date_db['max(date)'];
                $currentTime = Carbon::now();
                $current_date = date($currentTime->toDateString());
                if ($current_date >= $starting_date && $current_date <= $ending_date) {
                    $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } elseif ($current_date < $starting_date) {
                    $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } else {
                    $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
                }

                // $request->status  =  0 :- all exams , 1 :- Upcoming , 2 :- On Going , 3 :- Completed

                if (isset($request->status)) {
                    if ($request->status == 0) {
                        $exam_data[] = array(
                            'id' => $data->exam->id,
                            'name' => $data->exam->name,
                            'description' => $data->exam->description,
                            'publish' => $data->exam->publish,
                            'session_year' => $data->exam->session_year->name,
                            'exam_starting_date' => $starting_date,
                            'exam_ending_date' => $ending_date,
                            'exam_status' => $exam_status,
                        );
                    } else if ($request->status == 1) {
                        if ($exam_status == 0) {
                            $exam_data[] = array(
                                'id' => $data->exam->id,
                                'name' => $data->exam->name,
                                'description' => $data->exam->description,
                                'publish' => $data->exam->publish,
                                'session_year' => $data->exam->session_year->name,
                                'exam_starting_date' => $starting_date,
                                'exam_ending_date' => $ending_date,
                                'exam_status' => $exam_status,
                            );
                        }
                    } else if ($request->status == 2) {
                        if ($exam_status == 1) {
                            $exam_data[] = array(
                                'id' => $data->exam->id,
                                'name' => $data->exam->name,
                                'description' => $data->exam->description,
                                'publish' => $data->exam->publish,
                                'session_year' => $data->exam->session_year->name,
                                'exam_starting_date' => $starting_date,
                                'exam_ending_date' => $ending_date,
                                'exam_status' => $exam_status,
                            );
                        }
                    } else {
                        if ($exam_status == 2) {
                            $exam_data[] = array(
                                'id' => $data->exam->id,
                                'name' => $data->exam->name,
                                'description' => $data->exam->description,
                                'publish' => $data->exam->publish,
                                'session_year' => $data->exam->session_year->name,
                                'exam_starting_date' => $starting_date,
                                'exam_ending_date' => $ending_date,
                                'exam_status' => $exam_status,
                            );
                        }
                    }
                } else {
                    $exam_data[] = array(
                        'id' => $data->exam->id,
                        'name' => $data->exam->name,
                        'description' => $data->exam->description,
                        'publish' => $data->exam->publish,
                        'session_year' => $data->exam->session_year->name,
                        'exam_starting_date' => $starting_date,
                        'exam_ending_date' => $ending_date,
                        'exam_status' => $exam_status,
                    );
                }
            }

            $response = array(
                'error' => false,
                'data' => isset($exam_data) ? $exam_data : [],
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getExamDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::with('class_section')->where('id', $request->child_id)->first();
            $class_id = $student->class_section->class_id;
            $exam_data = Exam::with(['timetable' => function ($q) use ($request, $class_id) {
                $q->where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->with('subject')->orderby('date');
            }])->where('id', $request->exam_id)->first();

            $data = isset($exam_data) ? $exam_data->timetable : [];

            $response = array(
                'error' => false,
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getExamMarks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $exam_result_db = ExamResult::with(['student' => function ($q) {
                $q->select('id', 'user_id', 'roll_number')->with('user:id,first_name,last_name');
            }])->with('exam', 'session_year:id,name')->where('student_id', $request->child_id)->get();

            if (sizeof($exam_result_db)) {
                foreach ($exam_result_db as $result) {
                    $exam_timetable_id = ExamTimetable::where('exam_id', $result->exam_id)->pluck('id');

                    $exam_marks_db = ExamMarks::whereIn('exam_timetable_id', $exam_timetable_id)->where('student_id', $result->student_id)->get();

                    $class_data = ClassSection::where('id', $result->class_section_id)->with('class.medium', 'section')->first();

                    $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $result->exam_id, 'class_id' => $class_data->class_id])->first();
                    $starting_date = $starting_date_db['min(date)'];

                    $exam_result = array();
                    $exam_result = array(
                        'result_id' => $result->id,
                        'exam_id' => $result->exam_id,
                        'exam_name' => $result->exam->name,
                        'class_name' => $class_data->class->name . '-' . $class_data->section->name,
                        'student_name' => $result->student->user->first_name . ' ' . $result->student->user->last_name,
                        'exam_date' => $starting_date,
                        'total_marks' => $result->total_marks,
                        'obtained_marks' => $result->obtained_marks,
                        'percentage' => $result->percentage,
                        'grade' => $result->grade,
                        'session_year' => $result->session_year->name,
                    );

                    $exam_marks = array();
                    foreach ($exam_marks_db as $marks) {
                        $exam_marks[] = array(
                            'marks_id' => $marks->id,
                            'subject_name' => $marks->subject->name,
                            'subject_type' => $marks->subject->type,
                            'total_marks' => $marks->timetable->total_marks,
                            'passing_marks' => $marks->timetable->passing_marks,
                            'obtained_marks' => $marks->obtained_marks,
                            'teacher_review' => $marks->teacher_review,
                            'grade' => $marks->grade,
                        );
                    }
                    $data[] = array(
                        'result' => $exam_result,
                        'exam_marks' => $exam_marks,
                    );
                }

                $response = array(
                    'error' => false,
                    'message' => "Exam Result Fetched Successfully",
                    'data' => $data,
                    'code' => 200,
                );
            } else {
                $response = array(
                    'error' => false,
                    'message' => "Exam Result Fetched Successfully",
                    'data' => [],
                    'code' => 200,
                );
            }
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    //Get Fees Details
    public function getFeesDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student_data = Students::where('id', $request->child_id)->with('class_section')->first();
            $class_id = $student_data->class_section->class_id;

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //Total optional fees amount
            $optional_fees_amount = FeesClass::select(DB::raw("SUM(amount) as optional_fees_amount"))->where(['class_id' => $class_id, 'choiceable' => 1])->first();
            $optional_fees_amount = $optional_fees_amount['optional_fees_amount'];

            //Total optional fees amount
            $compulsory_fees_amount = FeesClass::select(DB::raw("SUM(amount) as compulsory_fees_amount"))->where(['class_id' => $class_id, 'choiceable' => 0])->first();
            $compulsory_fees_amount = $compulsory_fees_amount['compulsory_fees_amount'];

            // Fees Class Data
            $fees_class = FeesClass::where('class_id', $class_id)->with('fees_type')->get();

            //arrays for data
            $compulsory_fees_data = array();
            $optional_fees_data = array();
            $installment_data = array();

            $payment_transaction_db = PaymentTransaction::where(['student_id' => $request->child_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->latest()->first();
            if ($payment_transaction_db) {
                $payment_transaction_status = $payment_transaction_db->payment_status;
            }

            // Get the Compulsory Fees Data and Optional Fees Data
            foreach ($fees_class as $data) {
                if ($data->choiceable == 1) {
                    $paid_optional_data = FeesChoiceable::where(['student_id' => $request->child_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id, 'fees_type_id' => $data['fees_type_id']]);
                    $optional_fees_data[] = array(
                        'id' => $data->fees_type_id,
                        'name' => $data->fees_type->name,
                        'amount' => $data->amount,
                        'is_paid' => !empty($paid_optional_data->count()) ? 1 : 0,
                        'paid_date' => !empty($paid_optional_data->count()) ? ((isset($paid_optional_data->first()->date) && !empty($paid_optional_data->first()->date)) ? date('Y-m-d', strtotime($paid_optional_data->first()->date)) : null) : ""
                    );
                } else {
                    $is_fully_paid_data = FeesPaid::where(['student_id' => $request->child_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id, 'is_fully_paid' => 1]);
                    $compulsory_fees_data[] = array(
                        'id' => $data->fees_type_id,
                        'name' => $data->fees_type->name,
                        'amount' => $data->amount,
                        'is_paid' => !empty($is_fully_paid_data->count()) ? 1 : 0,
                        'paid_on' => !empty($is_fully_paid_data->count()) ? ((isset($is_fully_paid_data->first()->date) && !empty($is_fully_paid_data->first()->date)) ? date('Y-m-d', strtotime($is_fully_paid_data->first()->date)) : null) : "",
                    );
                }
            }

            // Checking for Due Charges Paid with Fully Compulsory Amount and Add it to Compulsory Fees Data Array
            if (isset($compulsory_fees_data) && !empty($compulsory_fees_data)) {
                $paid_charges_due = FeesChoiceable::where(['student_id' => $request->child_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id, 'is_due_charges' => 1]);
                if ($paid_charges_due->count()) {
                    array_push($compulsory_fees_data, array(
                        'id' => "",
                        'name' => 'Due Charges',
                        'amount' => $paid_charges_due->first()->total_amount,
                        'is_paid' => 1
                    ));
                }
            }


            //check the installments data for current session year
            $installment_db = InstallmentFee::where('session_year_id', $session_year_id);
            if ($installment_db->count()) {
                $installment_db_data = $installment_db->get();
                foreach ($installment_db_data as $data) {
                    $paid_installment_data = PaidInstallmentFee::where(['student_id' => $request->child_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id, 'installment_fee_id' => $data['id']]);
                    $installment_data[] = array(
                        'id' => $data->id,
                        'name' => $data->name,
                        'due_date' => date('Y-m-d', strtotime($data->due_date)),
                        'due_charges' => $data->due_charges,
                        'is_paid' => !empty($paid_installment_data->count()) ? 1 : 0,
                        'paid_date' => !empty($paid_installment_data->count()) ? ((isset($paid_installment_data->first()->date) && !empty($paid_installment_data->first()->date)) ? date('Y-m-d', strtotime($paid_installment_data->first()->date)) : null) : "",
                        "paid_due_charges" => !empty($paid_installment_data->count()) ? number_format($paid_installment_data->first()->due_charges, 2) : ""
                    );
                }
            }

            //Due Date And Due Charges From Session Year For Fully pay Compulsory Amount (Without Installments)
            $session_year_data = SessionYear::where('id', $session_year_id)->first();
            $due_date = date('Y-m-d', strtotime($session_year_data->fee_due_date));
            $due_charges = $session_year_data->fee_due_charges;

            $response = array(
                'error' => false,
                'compulsory_fees_data' => $compulsory_fees_data ?? array(""),
                'optional_fees_data' => $optional_fees_data ?? array(""),
                'installment_data' => $installment_data ?? (object)null,
                'compulsory_fees_total' => $compulsory_fees_amount ?? 0,
                'optional_fees_total' => $optional_fees_amount ?? 0,
                'compulsory_due_date' => $due_date,
                'compulsory_due_charges' => $due_charges,
                'is_fee_pending' => (isset($payment_transaction_status) && $payment_transaction_status == 2) ? 1 : 0,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    //Store Fees Transaction
    public function storeFeesTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
            'amount' => 'required',
            'type_of_fee' => 'required|in:0,1,2',
            'is_fully_paid' => 'required|in:0,1'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $parent_id = Auth::user()->parent->user_id;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //variables for storing the data
            $payment_gateway_details = array();

            $class_id = Students::where('id', $request->child_id)->with('class_section')->first()->class_section->class_id;


            //variables for storing the data
            $optional_fees_store = array();
            $installment_fees_store = array();


            // If Optional Fees Passed
            if (isset($request->optional_fees_data) && !empty($request->optional_fees_data)) {
                foreach ($request->optional_fees_data as $data) {
                    $optional_fees_store[] = array(
                        'student_id' => $request->child_id,
                        'class_id' => $class_id,
                        'fees_type_id' => $data['id'],
                        'is_due_charges' => 0,
                        'total_amount' => $data['amount'],
                        'session_year_id' => $session_year_id,
                        'date' => date('Y-m-d'),
                        'payment_transaction_id' => $request->transaction_id
                    );
                }
            }

            // If Installment Fees Passed
            if (isset($request->installment_data) && !empty($request->installment_data)) {
                foreach ($request->installment_data as $data) {
                    $installment_fees_store[] = array(
                        'class_id' => $class_id,
                        'student_id' => $request->child_id,
                        'parent_id' => $parent_id,
                        'installment_fee_id' => $data['id'],
                        'session_year_id' => $session_year_id,
                        'amount' => $data['amount'],
                        'due_charges' => $data['due_charges'] ?? null,
                        'date' => date('Y-m-d'),
                        'payment_transaction_id' => $request->transaction_id
                    );
                }
            }

            $razorpay_status = getSettings('razorpay_status');
            $razorpay_status = $razorpay_status['razorpay_status'];

            // get currency code from settings
            $setting_currency_code = getSettings('currency_code');
            $currency_code = $setting_currency_code['currency_code'];
            if ($razorpay_status) {
                // get api key from settings
                $razorpay_setting_api_key = getSettings('razorpay_api_key');
                $razorpay_api_key = $razorpay_setting_api_key['razorpay_api_key'];

                // get secret key from settings
                $razorpay_setting_secret_key = getSettings('razorpay_secret_key');
                $razorpay_secret_key = $razorpay_setting_secret_key['razorpay_secret_key'];

                $currency_code = strtoupper($currency_code);

                // add the data to transaction table local
                $payment_transaction_db = new PaymentTransaction();
                $payment_transaction_db->student_id = $request->child_id;
                $payment_transaction_db->class_id = $class_id;
                $payment_transaction_db->parent_id = $parent_id;
                $payment_transaction_db->mode = 2;
                $payment_transaction_db->type_of_fee = $request->type_of_fee;
                $payment_transaction_db->payment_gateway = 1;
                $payment_transaction_db->payment_status = 2;
                $payment_transaction_db->total_amount = $request->amount;
                $payment_transaction_db->date = date('Y-m-d H:i:s');
                $payment_transaction_db->session_year_id = $session_year_id;
                $payment_transaction_db->save();

                $api = new Api($razorpay_api_key, $razorpay_secret_key);
                $order = $api->order->create(array('amount' => $request->amount * 100, 'currency' => $currency_code, 'notes' => array(
                    'student_id' => $request->child_id,
                    'parent_id' => $parent_id,
                    'class_id' => $class_id,
                    'session_year_id' => $session_year_id,
                    'payment_transaction_id' => $payment_transaction_db->id,
                    'is_fully_paid' => $request->is_fully_paid,
                    'type_of_fee' => $request->type_of_fee,
                    'is_due_charges' => (isset($request->due_charges) && $request->due_charges >= 0) ? 1 : 0,
                    'due_charges' => $request->due_charges,
                    'optional_fees_paid' => json_encode($optional_fees_store) ?? "",
                    'installment_fees_paid' => json_encode($installment_fees_store) ?? "",
                )));

                // update the order id in trasaction table local
                $payemnt_transaction_update = PaymentTransaction::find($payment_transaction_db->id);
                $payemnt_transaction_update->order_id = $order->id;
                $payemnt_transaction_update->save();

                $payment_gateway_details = array(
                    'order_id' => $order->id,
                    'amount' => $order->amount,
                    'payment_transaction_id' => $payment_transaction_db->id,
                );
            }

            $stripe_status = getSettings('stripe_status');
            $stripe_status = $stripe_status['stripe_status'];
            if ($stripe_status) {
                $stripe_setting_secret_key = getSettings('stripe_secret_key');
                $stripe_secret_key = $stripe_setting_secret_key['stripe_secret_key'];

                $currency_code = strtolower($currency_code);

                // add the data to transaction table local
                $payment_transaction_db = new PaymentTransaction();
                $payment_transaction_db->student_id = $request->child_id;
                $payment_transaction_db->class_id = $class_id;
                $payment_transaction_db->parent_id = $parent_id;
                $payment_transaction_db->mode = 2;
                $payment_transaction_db->type_of_fee = $request->type_of_fee;
                $payment_transaction_db->payment_gateway = 2;
                $payment_transaction_db->total_amount = $request->amount;
                $payment_transaction_db->date = date('Y-m-d H:i:s');
                $payment_transaction_db->session_year_id = $session_year_id;
                $payment_transaction_db->save();

                $stripe = new StripeClient($stripe_secret_key);
                $stripe_data = $stripe->paymentIntents->create(
                    [
                        'amount' => $request->amount * 100,
                        'currency' => $currency_code,
                        'metadata' => [
                            'student_id' => $request->child_id,
                            'parent_id' => $parent_id,
                            'class_id' => $class_id,
                            'session_year_id' => $session_year_id,
                            'payment_transaction_id' => $payment_transaction_db->id,
                            'is_fully_paid' => $request->is_fully_paid,
                            'type_of_fee' => $request->type_of_fee,
                            'is_due_charges' => (isset($request->due_charges) && $request->due_charges >= 0) ? 1 : 0,
                            'due_charges' => $request->due_charges,
                            'optional_fees_paid' => json_encode($optional_fees_store) ?? "",
                            'installment_fees_paid' => json_encode($installment_fees_store) ?? "",
                        ],
                    ]
                );

                // update the order id in trasaction table local
                $payemnt_transaction_update = PaymentTransaction::find($payment_transaction_db->id);
                $payemnt_transaction_update->order_id = $stripe_data->id;
                $payemnt_transaction_update->save();

                $payment_gateway_details = array(
                    'payment_intent_id' => $stripe_data->id,
                    'amount' => $stripe_data->amount,
                    'client_secret' => $stripe_data->client_secret,
                    'payment_transaction_id' => $payment_transaction_db->id,
                );
            }

            //validating the enable of gateways ..
            if ($razorpay_status == 0 && $stripe_status == 0) {
                $response = array(
                    'error' => true,
                    'message' => 'Please enable the payment gateway in panel',
                    'code' => 404,
                );
                return response()->json($response);
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
                'payment_gateway_details' => $payment_gateway_details,
                'code' => 200,
            );
        } catch (\Exception $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    // add the transaction data in transaction table
    public function storeFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required',
            'payment_id' => 'nullable',
            'payment_signature' => 'nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            // Updating the Payment Transaction
            $transaction_db = PaymentTransaction::findOrFail($request->transaction_id);
            if ($request->payment_id) {
                $transaction_db->payment_id = $request->payment_id;
            }
            if ($request->payment_signature) {
                $transaction_db->payment_signature = $request->payment_signature;
            }
            $transaction_db->save();
            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully'),
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    //get the fees paid list
    public function feesPaidList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $fees_paid = FeesPaid::where(['student_id' => $request->child_id])->with('session_year:id,name', 'class.medium')->get();

            $response = array(
                'error' => false,
                'data' => $fees_paid,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    //Generate The Reciept
    public function feesPaidReceiptPDF(Request $request)
    {
        try {
            $parent = Auth::user();
            $parent_name = $parent->first_name . ' ' . $parent->last_name;
            $logo = config('environment.LOGO2');
            $logo = public_path('/storage/' . $logo);
            $school_name = config('environment.APP_NAME');
            $school_address = getSettings('school_address');
            $school_address = $school_address['school_address'];

            $currency_symbol = getSettings('currency_symbol');
            if (isset($currency_symbol) && sizeof($currency_symbol)) {
                $currency_symbol = $currency_symbol['currency_symbol'];
            } else {
                $currency_symbol = null;
            }

            //Getting the Fees Paid Data
            $fees_paid = FeesPaid::where('id', $request->fees_paid_id)->with('student.user:id,first_name,last_name', 'class', 'session_year')->get()->first();

            // Check That Fees Paid Data Exists Or Not
            if (!$fees_paid) {
                $response = array(
                    'error' => true,
                    'message' => "No Fees Paid Found",
                );
                return response()->json($response);
            }

            // Variables
            $student_id = $fees_paid->student_id;
            $class_id = $fees_paid->class_id;
            $session_year_id = $fees_paid->session_year_id;


            // Paid Installment Data
            $paid_installment = PaidInstallmentFee::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->with('installment_fee')->get();

            //Fees Choiceable Data
            $optional_fees_type_id = FeesClass::where(['class_id' => $class_id, 'choiceable' => 1])->pluck('fees_type_id');
            $fees_choiceable = FeesChoiceable::whereIn('fees_type_id', $optional_fees_type_id)->where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->with('fees_type')->orderby('id', 'asc')->get();

            //Fees Class Data
            $fees_class = FeesClass::where(['class_id' => $class_id, 'choiceable' => 0])->with('fees_type')->get();

            //Session Year Data
            $session_year = SessionYear::where('id', $session_year_id)->first();

            //Load the HTML
            $pdf = Pdf::loadView('fees.fees_receipt', compact('logo', 'school_name', 'fees_paid', 'paid_installment', 'fees_choiceable', 'currency_symbol', 'school_address', 'fees_class', 'session_year'));

            //Get The Output Of PDF
            $output = $pdf->output();

            $response = array(
                'error' => false,
                'pdf' => base64_encode($output),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
            );
        }
        return response()->json($response);
    }

    public function getOnlineExamList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'subject_id' => 'nullable|numeric'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::where('id', $request->child_id)->first();
            $class_section_id = $student->class_section->id;
            $class_id = $student->class_section->class_id;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //get current
            $time_data = Carbon::now()->toArray();
            $current_date_time = $time_data['formatted'];

            // checks the subject id param is passed or not .
            // query meets the condition for both class section and class
            if (isset($request->subject_id) && !empty($request->subject_id)) {
                $exam_data_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['end_date', '>=', $current_date_time]])->has('question_choice')->with('subject')->whereDoesntHave('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })->orWhere(function ($query) use ($class_id, $session_year_id, $current_date_time, $student, $request) {
                    $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['end_date', '>=', $current_date_time]])->with('subject')->whereDoesntHave('student_attempt', function ($q) use ($student) {
                        $q->where('student_id', $student->user_id);
                    });
                })->orderby('start_date')->paginate(15)->toArray();
            } else {
                $exam_data_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'session_year_id' => $session_year_id, ['end_date', '>=', $current_date_time]])->has('question_choice')->with('subject')->whereDoesntHave('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })->orWhere(function ($query) use ($class_id, $session_year_id, $current_date_time, $student) {
                    $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'session_year_id' => $session_year_id, ['end_date', '>=', $current_date_time]])->with('subject')->whereDoesntHave('student_attempt', function ($q) use ($student) {
                        $q->where('student_id', $student->user_id);
                    });
                })->orderby('start_date')->paginate(15)->toArray();
            }
            if (isset($exam_data_db) && !empty($exam_data_db)) {

                $exam_data = array();
                $exam_list = array();
                // making the array of exam data
                foreach ($exam_data_db['data'] as $data) {

                    // total marks of exams
                    $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $data['id'])->first();
                    $total_marks = $total_marks['sum(marks)'];

                    if ($data['model_type'] == 'App\Models\ClassSection') {
                        $class_section_data = ClassSection::where('id', $data['model_id'])->with('class.medium', 'section')->first();
                        $class_name = $class_section_data->class->name . ' - ' . $class_section_data->section->name;
                    } else {
                        $class_data = ClassSchool::where('id', $data['model_id'])->with('medium')->first();
                        $class_name = $class_data->name;
                    }

                    $exam_list[] = array(
                        'exam_id' => $data['id'],
                        'class' => array(
                            'id' => $data['model_id'],
                            'name' => $class_name
                        ),
                        'subject' => array(
                            'id' => $data['subject_id'],
                            'name' => $data['subject']['name'] . ' - ' . $data['subject']['type']
                        ),
                        'title' => $data['title'],
                        'exam_key' => $data['exam_key'],
                        'duration' => $data['duration'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'total_marks' => $total_marks,
                    );
                }

                //adding the exam data with pagination data
                $exam_data = array(
                    'current_page' => $exam_data_db['current_page'],
                    'data' => $exam_list,
                    'from' => $exam_data_db['from'],
                    'last_page' => $exam_data_db['last_page'],
                    'per_page' => $exam_data_db['per_page'],
                    'to' => $exam_data_db['to'],
                    'total' => $exam_data_db['total'],
                );
            } else {
                //if no data found
                $exam_data = null;
            }
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $exam_data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getOnlineExamResultList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'subject_id' => 'nullable|numeric'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::where('id', $request->child_id)->first();
            $class_section_id = $student->class_section_id;
            $class_id = $student->class_section->class_id;

            // current session year id
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // get the class subject id on the basis of subject id passed
            // query meets the condition for both class section and class
            if (isset($request->subject_id) && !empty($request->subject_id)) {
                $online_exam_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id])->whereHas('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })->orWhere(function ($query) use ($class_id, $session_year_id, $request, $student) {
                    $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'session_year_id' => $session_year_id, 'subject_id' => $request->subject_id])->with('subject')->whereHas('student_attempt', function ($q) use ($student) {
                        $q->where('student_id', $student->user_id);
                    });
                })->with('subject')->paginate(10)->toArray();
            } else {
                $online_exam_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'session_year_id' => $session_year_id])->whereHas('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })->orWhere(function ($query) use ($class_id, $session_year_id, $student) {
                    $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'session_year_id' => $session_year_id])->with('subject')->whereHas('student_attempt', function ($q) use ($student) {
                        $q->where('student_id', $student->user_id);
                    });
                })->with('subject')->paginate(10)->toArray();
            }
            $exam_list_data = array();
            foreach ($online_exam_db['data'] as $data) {
                //get the choice question id
                $exam_submitted_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('question_id');
                $exam_submitted_date = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('submitted_date')->first();

                $question_ids = OnlineExamQuestionChoice::whereIn('id', $exam_submitted_question_ids)->pluck('question_id');


                $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('option_id');

                //removes the question id of the question if one of the answer of particular question is wrong
                foreach ($question_ids as $question_id) {
                    $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id', $question_id)->whereNotIn('answer', $exam_attempted_answers)->count();
                    if ($check_questions_answers_exists) {
                        unset($question_ids[array_search($question_id, $question_ids->toArray())]);
                    }
                }

                $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id', $question_ids)->whereIn('answer', $exam_attempted_answers)->pluck('question_id');

                // get the data of only attempted data
                $total_obtained_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $data['id'])->whereIn('question_id', $exam_correct_answers_question_id)->first();
                $total_obtained_marks_exam = $total_obtained_marks_exam['sum(marks)'];
                $total_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $data['id'])->first();
                $total_marks_exam = $total_marks_exam['sum(marks)'];

                $exam_list_data[] = array(
                    'online_exam_id' => $data['id'],
                    'subject' => array(
                        'id' => $data['subject_id'],
                        'name' => $data['subject']['name'] . ' - ' . $data['subject']['type'],
                    ),
                    'title' => $data['title'],
                    'obtained_marks' => $total_obtained_marks_exam ?? "0",
                    'total_marks' => $total_marks_exam ?? "0",
                    'exam_submitted_date' => $exam_submitted_date ?? date('Y-m-d', strtotime($data['end_date']))
                );
            }
            $exam_list = array(
                'current_page' => $online_exam_db['current_page'],
                'data' => $exam_list_data ?? '',
                'from' => $online_exam_db['from'],
                'last_page' => $online_exam_db['last_page'],
                'per_page' => $online_exam_db['per_page'],
                'to' => $online_exam_db['to'],
                'total' => $online_exam_db['total'],
            );
            $response = array(
                'error' => false,
                'data' => $exam_list ?? '',
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getOnlineExamResult(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required_|numeric',
            'online_exam_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::where('id', $request->child_id)->first();

            //get the total questions count
            $total_questions = OnlineExamQuestionChoice::where('online_exam_id', $request->online_exam_id)->count();

            //get the exam's choiced question id
            $exam_choiced_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $request->online_exam_id])->pluck('question_id');

            //get the questions id
            $question_ids = OnlineExamQuestionChoice::whereIn('id', $exam_choiced_question_ids)->pluck('question_id');

            //get the options submitted by student
            $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $request->online_exam_id])->pluck('option_id');

            //removes the question id of the question if one of the answer of particular question is wrong
            foreach ($question_ids as $question_id) {
                $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id', $question_id)->whereNotIn('answer', $exam_attempted_answers)->count();
                if ($check_questions_answers_exists) {
                    unset($question_ids[array_search($question_id, $question_ids->toArray())]);
                }
            }

            // get the correct answers counter
            $exam_correct_answers = OnlineExamQuestionAnswer::whereIn('question_id', $question_ids)->whereIn('answer', $exam_attempted_answers)->groupby('question_id')->pluck('question_id')->count();

            // question id of correct answers
            $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id', $question_ids)->whereIn('answer', $exam_attempted_answers)->pluck('question_id');

            //data of correct answers
            $exam_correct_answers_data = OnlineExamQuestionAnswer::whereIn('question_id', $question_ids)->whereIn('answer', $exam_attempted_answers)->groupby('question_id')->get();

            // array of correct answer with choiced exam id and marks
            $correct_answers_data = array();
            foreach ($exam_correct_answers_data as $correct_data) {
                $choice_questions = OnlineExamQuestionChoice::where(['online_exam_id' => $request->online_exam_id, 'question_id' => $correct_data->question_id])->first();
                $correct_answers_data[] = array(
                    'question_id' => $choice_questions->id,
                    'marks' => $choice_questions->marks
                );
            }

            // get questions ids
            $all_questions_ids = OnlineExamQuestionChoice::whereNotIn('question_id', $question_ids)->where('online_exam_id', $request->online_exam_id)->pluck('question_id');

            // get the incorrect answers && unattempted counter
            $exam_in_correct_answers = OnlineExamQuestionAnswer::whereIn('question_id', $all_questions_ids)->whereNotIn('answer', $exam_attempted_answers)->groupby('question_id')->pluck('question_id')->count();

            // data of in correct && unattempted answers
            $exam_in_correct_answers_data = OnlineExamQuestionAnswer::whereIn('question_id', $all_questions_ids)->whereNotIn('answer', $exam_attempted_answers)->groupby('question_id')->get();

            // array of in correct answer && unattempted with choiced exam id and marks
            $in_correct_answers_data = array();
            foreach ($exam_in_correct_answers_data as $in_correct_data) {
                $choice_questions = OnlineExamQuestionChoice::where(['online_exam_id' => $request->online_exam_id, 'question_id' => $in_correct_data->question_id])->first();
                if (isset($choice_questions) && !empty($choice_questions)) {
                    $in_correct_answers_data[] = array(
                        'question_id' => $choice_questions->id,
                        'marks' => $choice_questions->marks
                    );
                }
            }

            // total obtained and total marks
            $total_obtained_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $request->online_exam_id)->whereIn('question_id', $exam_correct_answers_question_id)->first();
            $total_obtained_marks = $total_obtained_marks['sum(marks)'];
            $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $request->online_exam_id)->first();
            $total_marks = $total_marks['sum(marks)'];

            // final array data
            $exam_result = array(
                'total_questions' => $total_questions,
                'correct_answers' => array(
                    'total_questions' => $exam_correct_answers,
                    'question_data' => $correct_answers_data ?? ''
                ),
                'in_correct_answers' => array(
                    'total_questions' => $exam_in_correct_answers,
                    'question_data' => $in_correct_answers_data ?? ''
                ),
                'total_obtained_marks' => $total_obtained_marks ?? '0',
                'total_marks' => $total_marks
            );
            $response = array(
                'error' => false,
                'data' => $exam_result ?? '',
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getOnlineExamReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'subject_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::where('id', $request->child_id)->first();
            $class_section_id = $student->class_section_id;
            $class_id = $student->class_section->class_id;


            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //get current
            $time_data = Carbon::now()->toArray();
            $current_date_time = $time_data['formatted'];

            $exam_query = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['start_date', '<=', $current_date_time]])->orWhere(function ($query) use ($class_id, $session_year_id, $request, $current_date_time) {
                $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['start_date', '<=', $current_date_time]]);
            });
            $exam_exists = $exam_query->count();
            $exam_query_without_session_year = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'subject_id' => $request->subject_id, ['start_date', '<=', $current_date_time]])->orWhere(function ($query) use ($class_id, $session_year_id, $request, $current_date_time) {
                $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'subject_id' => $request->subject_id, ['start_date', '<=', $current_date_time]]);
            });

            // checks the exams exists
            if (isset($exam_exists) && !empty($exam_exists)) {
                //total online exams id and counts
                $total_exam_ids = $exam_query->pluck('id');
                //online exam ids attempted
                $attempted_online_exam_ids = StudentOnlineExamStatus::where('student_id', $student->user_id)->whereIn('online_exam_id', $total_exam_ids)->pluck('online_exam_id');

                //get the submitted answers (i.e. option id)
                $online_exams_attempted_answers = OnlineExamStudentAnswer::where('student_id', $student->user_id)->whereIn('online_exam_id', $total_exam_ids)->pluck('option_id');

                //get the submitted choiced question id
                $online_exams_submitted_question_ids = OnlineExamStudentAnswer::where('student_id', $student->user_id)->whereIn('online_exam_id', $total_exam_ids)->pluck('question_id');

                //get the questions id
                $get_question_ids = OnlineExamQuestionChoice::whereIn('id', $online_exams_submitted_question_ids)->pluck('question_id');

                //removes the question id of the question if one of the answer of particular question is wrong
                foreach ($get_question_ids as $question_id) {
                    $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id', $question_id)->whereNotIn('answer', $online_exams_attempted_answers)->count();
                    if ($check_questions_answers_exists) {
                        unset($get_question_ids[array_search($question_id, $get_question_ids->toArray())]);
                    }
                }
                //get the correct answers question id
                $correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id', $get_question_ids)->whereIn('answer', $online_exams_attempted_answers)->pluck('question_id');


                //total exams
                $total_exams = $exam_query_without_session_year->count();

                //total exam attempted
                $total_attempted_exams = StudentOnlineExamStatus::where('student_id', $student->user_id)->whereIn('online_exam_id', $total_exam_ids)->count();

                // total missed exams
                $total_missed_exams = $exam_query_without_session_year->whereNotIn('id', $attempted_online_exam_ids)->count();

                // get the correct choiced question id and marks
                $total_obtained_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->whereIn('online_exam_id', $total_exam_ids)->whereIn('question_id', $correct_answers_question_id)->first();
                $total_obtained_marks = $total_obtained_marks['sum(marks)'];

                //overall total marks
                $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->whereIn('online_exam_id', $total_exam_ids)->first();
                $total_marks = $total_marks['sum(marks)'];

                if ($total_obtained_marks) {
                    $percentage = number_format(($total_obtained_marks * 100) / $total_marks, 2);
                }


                // particular online exam data
                $online_exams_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection', 'model_id' => $class_section_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['start_date', '<=', $current_date_time]])->orWhere(function ($query) use ($class_id, $session_year_id, $request, $current_date_time) {
                    $query->where(['model_type' => 'App\Models\ClassSchool', 'model_id' => $class_id, 'subject_id' => $request->subject_id, 'session_year_id' => $session_year_id, ['start_date', '<=', $current_date_time]]);
                })->with(['student_attempt' => function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                }])->has('question_choice')->paginate(10)->toArray();


                $exam_list = array();
                $total_obtained_marks_exam = '';
                foreach ($online_exams_db['data'] as $data) {
                    $exam_submitted_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('question_id');
                    $get_exam_question_ids = OnlineExamQuestionChoice::whereIn('id', $exam_submitted_question_ids)->pluck('question_id');


                    $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('option_id');


                    //removes the question id of the question if one of the answer of particular question is wrong
                    foreach ($get_exam_question_ids as $question_id) {
                        $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id', $question_id)->whereNotIn('answer', $exam_attempted_answers)->count();
                        if ($check_questions_answers_exists) {
                            unset($get_exam_question_ids[array_search($question_id, $get_exam_question_ids->toArray())]);
                        }
                    }

                    $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id', $get_exam_question_ids)->whereIn('answer', $exam_attempted_answers)->pluck('question_id');

                    $total_obtained_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $data['id'])->whereIn('question_id', $exam_correct_answers_question_id)->first();
                    $total_obtained_marks_exam = $total_obtained_marks_exam['sum(marks)'];
                    $total_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id', $data['id'])->first();
                    $total_marks_exam = $total_marks_exam['sum(marks)'];

                    $exam_list[] = array(
                        'online_exam_id' => $data['id'],
                        'title' => $data['title'],
                        'obtained_marks' => $total_obtained_marks_exam ?? "0",
                        'total_marks' => $total_marks_exam ?? "0",
                    );
                }


                // array of final data
                $online_exam_report_data = array(
                    'total_exams' => $total_exams,
                    'attempted' => $total_attempted_exams,
                    'missed_exams' => $total_missed_exams,
                    'total_marks' => $total_marks ?? "0",
                    'total_obtained_marks' => $total_obtained_marks ?? "0",
                    'percentage' => $percentage ?? "0",
                    'exam_list' => array(
                        'current_page' => $online_exams_db['current_page'],
                        'data' => $exam_list,
                        'from' => $online_exams_db['from'],
                        'last_page' => $online_exams_db['last_page'],
                        'per_page' => $online_exams_db['per_page'],
                        'to' => $online_exams_db['to'],
                        'total' => $online_exams_db['total'],
                    )
                );
            }
            $response = array(
                'error' => false,
                'data' => $online_exam_report_data ?? [],
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getAssignmentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'subject_id' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        try {
            $student = Students::where('id', $request->child_id)->first();

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // get the assignments ids
            $assingment_ids = Assignment::where(['class_section_id' => $student->class_section_id, 'session_year_id' => $session_year_id, 'subject_id' => $request->subject_id])->pluck('id');

            //total assignments of class
            $total_assignments = Assignment::where(['class_section_id' => $student->class_section_id, 'session_year_id' => $session_year_id, 'subject_id' => $request->subject_id])->count();

            //total assignment submiited
            $total_submitted_assignments = AssignmentSubmission::where('student_id', $student->user_id)->whereIn('assignment_id', $assingment_ids)->count();

            // submitted assingment id
            $submitted_assignment_ids = AssignmentSubmission::where('student_id', $student->user_id)->whereIn('assignment_id', $assingment_ids)->pluck('assignment_id');

            //total assignment unsubmitted
            $total_assingment_unsubmitted = Assignment::where(['class_section_id' => $student->class_section_id, 'subject_id' => $request->subject_id])->whereNotIn('id', $submitted_assignment_ids)->count();

            //total points of assignment submitted
            $total_assignment_submitted_points = Assignment::select(DB::raw("sum(points)"))->where('class_section_id', $student->class_section_id)->whereIn('id', $submitted_assignment_ids)->whereNot('points', null)->first();
            $total_assignment_submitted_points = $total_assignment_submitted_points['sum(points)'];

            // total obtained assignment points
            $assingment_id_with_points = Assignment::where(['class_section_id' => $student->class_section_id, 'subject_id' => $request->subject_id])->whereIn('id', $submitted_assignment_ids)->whereNot('points', null)->pluck('id');
            $total_points_obtained = AssignmentSubmission::select(DB::raw("sum(points)"))->whereIn('assignment_id', $assingment_id_with_points)->first();
            $total_points_obtained = $total_points_obtained['sum(points)'];

            if ($total_points_obtained) {
                //percentage
                $percentage = number_format(($total_points_obtained * 100) / $total_assignment_submitted_points, 2);
            }


            $submitted_assignment_data_db = Assignment::with('submission')->where(['class_section_id' => $student->class_section_id, 'subject_id' => $request->subject_id])->whereIn('id', $submitted_assignment_ids)->whereNot('points', null);
            $submitted_assignment_data_with_points = $submitted_assignment_data_db->paginate(10)->toArray();

            $submitted_assingment_data = array();
            foreach ($submitted_assignment_data_with_points['data'] as $submitted_data) {
                $submitted_assingment_data[] = array(
                    'assignment_id' => $submitted_data['id'],
                    'assignment_name' => $submitted_data['name'],
                    'obtained_points' => $submitted_data['submission']['points'],
                    'total_points' => $submitted_data['points']
                );
            }
            $assingment_report = array(
                'assignments' => $total_assignments,
                'submitted_assignments' => $total_submitted_assignments,
                'unsubmitted_assignments' => $total_assingment_unsubmitted,
                'total_points' => $total_assignment_submitted_points ?? "0",
                'total_obtained_points' => $total_points_obtained ?? "0",
                'percentage' => $percentage ?? "0",
                'submitted_assignment_with_points_data' => array(
                    'current_page' => $submitted_assignment_data_with_points['current_page'],
                    'data' => $submitted_assingment_data,
                    'from' => $submitted_assignment_data_with_points['from'],
                    'last_page' => $submitted_assignment_data_with_points['last_page'],
                    'per_page' => $submitted_assignment_data_with_points['per_page'],
                    'to' => $submitted_assignment_data_with_points['to'],
                    'total' => $submitted_assignment_data_with_points['total'],
                )
            );
            $response = array(
                'error' => false,
                'data' => $assingment_report,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function getFeesPaymentTransactions(Request $request)
    {
        try {
            $parent_id = Auth::user()->parent->user_id;
            $fees_payment_trasactions = PaymentTransaction::where('parent_id', $parent_id)->with('session_year')->with(['student' => function ($q) {
                $q->select('id', 'user_id')->with('user:id,first_name,last_name');
            }])->orderBy('id', 'desc')->paginate(15)->toArray();
            $response = array(
                'error' => false,
                'data' => array(
                    'current_page' => $fees_payment_trasactions['current_page'],
                    'transaction-data' => $fees_payment_trasactions['data'],
                    'from' => $fees_payment_trasactions['from'],
                    'last_page' => $fees_payment_trasactions['last_page'],
                    'per_page' => $fees_payment_trasactions['per_page'],
                    'to' => $fees_payment_trasactions['to'],
                    'total' => $fees_payment_trasactions['total'],
                ),
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function getProfileDetails()
    {
        try {
            $user = Auth::user();
            $children = Students::where('father_id', $user->parent->user_id)->orWhere('mother_id', $user->parent->user_id)->orWhere('guardian_id', $user->parent->user_id)->with('class_section')->get();
            $user = flattenMyModel($user);

            foreach ($children as $child) {
                $child->first_name = $child->user->first_name;
                $child->last_name = $child->user->last_name;
                $child->image = $child->user->image;
                unset($child->user);
                //Set Class Section name
                $child->class_section_name = $child->class_section->class->name . " " . $child->class_section->section->name;
                //Set Medium name
                $child->medium_name = $child->class_section->class->medium->name;
                unset($child->class_section);

                //Set Category name
                $child->category_name = $child->category->name;
                unset($child->category);
            }
            $data = array_merge($user, ['children' => $children->toArray()]);
            $response = array(
                'error' => false,
                'message' => 'Data Fetched Successfully',
                'data' => $data,
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    // Make Transaction Fail API
    public function failPaymentTransactionStatus(Request $request)
    {
        try {
            $update_status = PaymentTransaction::findOrFail($request->payment_transaction_id);
            $update_status->payment_status = 0;
            $update_status->save();
            $response = array(
                'error' => false,
                'message' => 'Data Updated Successfully',
                'code' => 200,
            );
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
        }
        return response()->json($response);
    }

    public function noOfChildren(Request $request)
    {

        if ($request->user()->type == "parent") {
            $parent_id = $request->user()->parent->user_id;
            $students = Students::where('father_id', $parent_id)
                ->orWhere('mother_id', $parent_id)
                ->whereHas('user',function ($query) {
                    $query->whereNull('deleted_at');
                })
                ->with('user')->get();
            $studentsPluck = Students::where('father_id', $parent_id)
                ->orWhere('mother_id', $parent_id)
                ->whereHas('user',function ($query) {
                    $query->whereNull('deleted_at');
                })
                ->with('user')->pluck('user_id')->toArray();

            $studentCount = $students->count();

            $studentNames = $students->map(function ($student) {
                return $student->user->first_name . ' ' . $student->user->last_name;
            });

            $classSections = Students::whereIn('user_id',$studentsPluck)->pluck('class_section_id')->toArray();
            $teachers = User::where('type','teacher')
                ->whereHas('teacher',function ($query) use ($classSections){
                    $query->whereHas('class_section',function ($query) use ($classSections){
                        $query->whereIn('id',$classSections);
                    });
                })
                ->with('teacher.class_section.class')
                ->orderBy('first_name','ASC')
                ->distinct()
                ->count();

            $result = [
                'student_count' => $studentCount,
                'teacher_count' => $teachers,
                'student_list' => $studentNames->toArray(),
            ];
        } else {
            $result = ['error' => true,
                'message' => trans('no_permission_message'),
                'code' => 401
            ];
        }


        return response()->json($result);
    }

    public function classParents(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }

        $user = $request->user();
        if ($user && $user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
        $class_id = $request->class_id;
        $class = ClassSchool::where('id', $class_id)->first();
        if(!$class){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected",
                'data' => [
                    "class_id" => $class_id
                ],
            );
            return response()->json($response,400);
        }

        $fIds = Students::whereHas('class_section', function ($query) use ($user, $class_id) {
                $query->where('class_teacher_id', $user->id);
                $query->where('class_id', $class_id);
        })->pluck('father_id')->toArray();

        $mIds = Students::whereHas('class_section', function ($query) use ($user, $class_id) {
                $query->where('class_teacher_id', $user->id);
                $query->where('class_id', $class_id);
        })->pluck('mother_id')->toArray();

        $parentIds = array_unique(array_merge($fIds,$mIds));
        $parents = User::whereHas('parent')
            ->whereIn('id', $parentIds)->get();

        $students = Students::whereIn('mother_id',$parentIds)->orWhereIn('father_id',$parentIds)->get();

        foreach ($parents as $parent){
            $parentStudents = [];
//            $parent->parent_student = [];
            foreach ($students as $student){
                if($student->father_id == $parent->id || $student->mother_id == $parent->id){
                    $parentStudents[] = $student;
                }
            }
            $parent->parent_students = $parentStudents;
        }

        $response = array(
            'error' => false,
            'message' => "Parents of a class",
            'data' => $parents,
            'code' => 200,
        );
        return response()->json($response);
    }
}
