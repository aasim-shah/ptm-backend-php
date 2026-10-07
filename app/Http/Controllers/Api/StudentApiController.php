<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Exam;
use App\Models\File;
use App\Models\User;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Holiday;
use App\Models\Settings;
use App\Models\Students;
use App\Models\ExamClass;
use App\Models\ExamMarks;
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
use App\Models\StudentSubject;
use App\Models\SubjectTeacher;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use Illuminate\Support\Facades\Auth;
use App\Models\OnlineExamStudentAnswer;
use App\Models\StudentOnlineExamStatus;
use Illuminate\Support\Facades\Storage;
use App\Models\OnlineExamQuestionAnswer;
use App\Models\OnlineExamQuestionChoice;
use App\Models\OnlineExamQuestionOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TimetableCollection;

class StudentApiController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gr_number' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        if (Auth::attempt(['email' => $request->gr_number, 'password' => $request->password])) {
            //        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            //Here Email Field is referenced as a GR Number for Student
            $auth = Auth::user();
            if (!$auth->hasRole('Student')) {
                $response = array(
                    'error' => true,
                    'message' => 'Invalid Login Credentials',
                    'code' => 101
                );
                return response()->json($response, 200);
            }
            $token = $auth->createToken($auth->first_name)->plainTextToken;
            $user = $auth->load(['student.class_section', 'student.category']);

            if ($request->fcm_id) {
                $auth->fcm_id = $request->fcm_id;
                $auth->save();
            }
            //Set Class Section name
            $user->class_section_name = $user->student->class_section->class->name . " " . $user->student->class_section->section->name;
            //Set Medium name
            $user->medium_name = $user->student->class_section->class->medium->name;
            unset($user->student->class_section);

            //Set Category name
            $user->category_name = $user->student->category->name;
            unset($user->student->category);
            $response = array(
                'error' => false,
                'message' => 'User logged-in!',
                'token' => $token,
                'data' => flattenMyModel($user),
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


    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gr_no' => 'required',
            'dob' => 'required|date',
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
            $get_id = Students::select('user_id')->where('admission_no', $request->gr_no)->pluck('user_id')->first();
            if (isset($get_id) && !empty($get_id)) {

                $user = User::where('id', $get_id)->whereDate('dob', '=', date('Y-m-d', strtotime($request->dob)))->first();
                if ($user) {
                    $user->reset_request = 1;
                    $user->save();
                    $response = array(
                        'error' => false,
                        'message' => "Request Send Successfully",
                        'code' => 200,
                    );
                } else {
                    $response = array(
                        'error' => true,
                        'message' => "Invalid user Details",
                        'code' => 107,
                    );
                }
            } else {
                $response = array(
                    'error' => true,
                    'message' => "Invalid user Details",
                    'code' => 107,
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

    public function subjects(Request $request)
    {
        try {
            $user = $request->user();
            $subjects = $user->student->subjects();
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
        try {
            $user = $request->user();
            $subjects = $user->student->classSubjects();
            $response = array(
                'error' => false,
                'message' => 'Class Subject Fetched Successfully.',
                //                'data' => new ClassSubjectCollection($subjects),
                'data' => $subjects,
                'code' => 200
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

    public function selectSubjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_group.*.id' => 'required',
            'subject_group.*.subject_id' => 'required|array',
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
            $student = $request->user()->student;
            $class_section = $student->class_section;
            $student_subject = array();
            $session_year_id = Settings::select('message')->where('type', 'session_year')->pluck('message')->first();
            foreach ($request->subject_group as $key => $subject_group) {
                $subject_group_id = $subject_group['id'];
                foreach ($subject_group['subject_id'] as $subject_id) {

                    $if_subject_already_selected = StudentSubject::where([
                        'student_id' => $student->user_id,
                        'subject_id' => $subject_id,
                        'class_section_id' => $class_section->id,
                        'session_year_id' => intval($session_year_id)
                    ])->first();
                    if (!$if_subject_already_selected) {
                        $student_subject[] = array(
                            'student_id' => $student->user_id,
                            'subject_id' => $subject_id,
                            'class_section_id' => $class_section->id,
                            'session_year_id' => intval($session_year_id)
                        );
                    }
                }
            }
            StudentSubject::insert($student_subject);

            $response = array(
                'error' => false,
                'message' => "Subject Selected Successfully",
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

    public function getParentDetails(Request $request)
    {
        try {
            $student = $request->user()->student->load(['father', 'mother', 'guardian']);
            $data = array(
                'father' => (!empty($student->father)) ? $student->father : (object)[],
                'mother' => (!empty($student->mother)) ? $student->mother : (object)[],
                'guardian' => (!empty($student->guardian)) ? $student->guardian : (object)[]
            );
            $response = array(
                'error' => false,
                'message' => "Parent Details Fetched Successfully",
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

    public function getTimetable(Request $request)
    {
        try {
            $student = $request->user()->student;
            $timetable = Timetable::where('class_section_id', $student->class_section_id)->with('subject_teacher')->orderBy('day', 'asc')->orderBy('start_time', 'asc')->get();
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
            $student = $request->user()->student;
            $data = Lesson::where('class_section_id', $student->class_section_id)->where('subject_id', $request->subject_id)->with('topic', 'file');
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
            //$student = $request->user()->student;
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
            $student = $request->user()->student;
            $data = Assignment::where('class_section_id', $student->class_section_id)->with('file', 'subject', 'submission.file');
            if ($request->assignment_id) {
                $data->where('id', $request->assignment_id);
            }
            if ($request->subject_id) {
                $data->where('subject_id', $request->subject_id);
            }
            if(isset($request->is_submitted)){
                if ($request->is_submitted) {
                    $data->whereHas('submission',function($q)use($student){
                        $q->where('student_id',$student->user_id);
                    });
                } else {
                    $data->has('submission', '<', 1);
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
                // 'message' => trans('error_occurred'),
                'message' => trans($e->getMessage()),
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
    public function submitAssignment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|numeric',
            'subject_id' => 'nullable|numeric',
            'files' => 'required|array',
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
            $student = $request->user()->student;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            $assignment = Assignment::where('id', $request->assignment_id)->where('class_section_id', $student->class_section_id)->firstOrFail();
            $assignment_submission = AssignmentSubmission::where('assignment_id', $request->assignment_id)->where('student_id', $student->user_id)->first();
            if (empty($assignment_submission)) {
                $assignment_submission = new AssignmentSubmission();
                $assignment_submission->assignment_id = $request->assignment_id;
                $assignment_submission->student_id = $student->user_id;
                $assignment_submission->session_year_id = $session_year_id;
            } else if ($assignment_submission->status == 2 && $assignment->resubmission) {
                // if assignment submission is rejected and
                // Assignment has resubmission allowed then change the status to resubmitted
                $assignment_submission->status = 3;
                if ($assignment_submission->file) {
                    foreach ($assignment_submission->file as $file) {
                        if (Storage::disk('public')->exists($file->file_url)) {
                            Storage::disk('public')->delete($file->file_url);
                        }
                    }
                }
                $assignment_submission->file()->delete();
            } else {
                $response = array(
                    'error' => true,
                    'message' => "You already have submitted your assignment.",
                    'code' => 104
                );
                return response()->json($response);
            }

            $assignment_submission->save();
            foreach ($request->file('files') as $key => $image) {
                $file = new File();
                $file->file_name = $image->getClientOriginalName();
                $file->modal()->associate($assignment_submission);
                $file->type = 1;
                $file->file_url = $image->store('assignment', 'public');
                $file->save();
            }
            $submitted_assignment = AssignmentSubmission::where('id', $assignment_submission->id)->with('file')->get();
            $response = array(
                'error' => false,
                'message' => "Assignments Submitted Successfully",
                'data' => $submitted_assignment,
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
    public function deleteAssignmentSubmission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_submission_id' => 'required|numeric',
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
            $student = $request->user()->student;
            $assignment_submission = AssignmentSubmission::where('id', $request->assignment_submission_id)->where('student_id', $student->user_id)->with('file')->first();

            if (!empty($assignment_submission) && $assignment_submission->status == 0) {
                foreach ($assignment_submission->file as $file) {
                    if (Storage::disk('public')->exists($file->file_url)) {
                        Storage::disk('public')->delete($file->file_url);
                    }
                }
                $assignment_submission->file()->delete();
                $assignment_submission->delete();
                $response = array(
                    'error' => false,
                    'message' => "Assignments Deleted Successfully",
                    'code' => 200,
                );
            } else {
                $response = array(
                    'error' => true,
                    'message' => "You can not delete assignment",
                    'code' => 110,
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

    /**
     * @param
     * month : 4 //OPTIONAL
     * year : 2022 //OPTIONAL
     */
    public function getAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            $student = $request->user()->student;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            $attendance = Attendance::where('student_id', $student->user_id)->where('session_year_id', $session_year_id);
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
            $student = $request->user()->student;
            $class_id = $student->class_section->class->id;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];
            $table = null;
            if (isset($request->type) && $request->type == "subject") {
                $table = SubjectTeacher::where('class_section_id', $student->class_section_id)->where('subject_id', $request->subject_id)->get()->pluck('id');
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

    public function getExamList(Request $request)
    {
        try {
            $student_id = Auth::user()->student->user_id;
            $student = Students::with('class_section')->where('id', $student_id)->first();
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
            $student_id = Auth::user()->student->user_id;
            $student = Students::with('class_section')->where('id', $student_id)->first();
            $class_id = $student->class_section->class_id;
            $exam_data_db = Exam::with(['timetable' => function ($q) use ($request, $class_id) {
                $q->where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->with(['subject'])->orderby('date');
            }])->where('id', $request->exam_id)->first();


            if (!$exam_data_db) {
                $response = array(
                    'error' => false,
                    'data' => [],
                    'code' => 200,
                );
                return response()->json($response);
            }


            foreach ($exam_data_db->timetable as $data) {
                $exam_data[] = array(
                    'id' => $data->id,
                    'total_marks' => $data->total_marks,
                    'passing_marks' => $data->passing_marks,
                    'date' => $data->date,
                    'starting_time' => $data->start_time,
                    'ending_time' => $data->end_time,
                    'subject' => array(
                        'id' => $data->subject->id,
                        'name' => $data->subject->name,
                        'bg_color' => $data->subject->bg_color,
                        'image' => $data->subject->image,
                        'type' => $data->subject->type,
                    )
                );
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
    public function getExamMarks(Request $request)
    {
        try {
            $student = $request->user()->student;
            $class_data = Students::where('id', $student->user_id)->with('class_section.class.medium', 'class_section.section')->get()->first();

            $exam_result_db = ExamResult::with(['student' => function ($q) {
                $q->select('id', 'user_id', 'roll_number')->with('user:id,first_name,last_name');
            }])->with('exam', 'session_year')->with(['exam.marks' => function ($q) use ($student) {
                $q->where('student_id', $student->user_id);
            }])->where('student_id', $student->user_id)->get();



            if (sizeof($exam_result_db)) {
                foreach ($exam_result_db as $exam_result_data) {
                    $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $exam_result_data->exam_id, 'class_id' => $class_data->class_section->class_id])->first();
                    $starting_date = $starting_date_db['min(date)'];

                    $exam_result = array(
                        'result_id' => $exam_result_data->id,
                        'exam_id' => $exam_result_data->exam_id,
                        'exam_name' => $exam_result_data->exam->name,
                        'class_name' => $class_data->class_section->class->name . '-' . $class_data->class_section->section->name,
                        'student_name' => $exam_result_data->student->user->first_name . ' ' . $exam_result_data->student->user->last_name,
                        'exam_date' => $starting_date,
                        'total_marks' => $exam_result_data->total_marks,
                        'obtained_marks' => $exam_result_data->obtained_marks,
                        'percentage' => $exam_result_data->percentage,
                        'grade' => $exam_result_data->grade,
                        'session_year' => $exam_result_data->session_year->name,
                    );
                    $exam_marks = array();
                    foreach ($exam_result_data->exam->marks as $marks) {
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
    public function getOnlineExamList(Request $request){
        $validator = Validator::make($request->all(), [
            'subject_id' => 'nullable|numeric',
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
            $student = $request->user()->student;
            $class_section_id = $student->class_section->id;
            $class_id = $student->class_section->class_id;
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //get current
            $time_data = Carbon::now()->toArray();
            $current_date_time = $time_data['formatted'];

            // checks the subject id param is passed or not .
            // query meets the condition for both class section and class
            if(isset($request->subject_id) && !empty($request->subject_id)){
                $exam_data_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'subject_id' => $request->subject_id ,'session_year_id' => $session_year_id ,  ['end_date', '>=', $current_date_time]])->has('question_choice')->with('subject')->whereDoesntHave('student_attempt' , function($q) use($student){
                    $q->where('student_id',$student->user_id);
                })->orWhere(function($query) use ($class_id,$session_year_id,$current_date_time,$student,$request) {
                    $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'subject_id' => $request->subject_id ,'session_year_id' => $session_year_id ,  ['end_date', '>=', $current_date_time]])->with('subject')->whereDoesntHave('student_attempt' , function($q) use($student){
                        $q->where('student_id',$student->user_id);
                    });
                })->orderby('start_date')->paginate(15)->toArray();
            }else{
                $exam_data_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'session_year_id' => $session_year_id ,  ['end_date', '>=', $current_date_time]])->has('question_choice')->with('subject')->whereDoesntHave('student_attempt' , function($q) use($student){
                    $q->where('student_id',$student->user_id);
                })->orWhere(function($query) use ($class_id,$session_year_id,$current_date_time,$student) {
                    $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'session_year_id' => $session_year_id ,  ['end_date', '>=', $current_date_time]])->with('subject')->whereDoesntHave('student_attempt' , function($q) use($student){
                        $q->where('student_id',$student->user_id);
                    });
                })->orderby('start_date')->paginate(15)->toArray();
            }
            if(isset($exam_data_db) && !empty($exam_data_db)){

                $exam_data = array();
                $exam_list = array();
                // making the array of exam data
                foreach ($exam_data_db['data'] as $data) {

                    // total marks of exams
                    $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$data['id'])->first();
                    $total_marks = $total_marks['sum(marks)'];

                    if($data['model_type'] == 'App\Models\ClassSection'){
                        $class_section_data = ClassSection::where('id',$data['model_id'])->with('class.medium','section')->first();
                        $class_name = $class_section_data->class->name.' - '.$class_section_data->section->name;
                    }else{
                        $class_data = ClassSchool::where('id',$data['model_id'])->with('medium')->first();
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
                            'name' => $data['subject']['name'].' - '.$data['subject']['type']
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
            }else{
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
    public function getOnlineExamQuestions(Request $request){
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required',
            'exam_key' => 'required',
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
            $student = $request->user()->student;

            // checks Exam key
            $check_key = OnlineExam::where(['id' => $request->exam_id, 'exam_key' => $request->exam_key])->count();
            if($check_key == 0){
                $response = array(
                    'error' => true,
                    'message' => trans('invalid_exam_key'),
                    'code' => 103
                );
                return response()->json($response);
            }

            // checks student exam status
            $check_student_status = StudentOnlineExamStatus::where(['online_exam_id' => $request->exam_id,'student_id' => $student->user_id])->count();
            if($check_student_status != 0){
                $response = array(
                    'error' => true,
                    'message' => trans('student_already_attempted_exam'),
                    'code' => 105
                );
                return response()->json($response);
            }

            //checks the exam started or not
            $time_data = Carbon::now()->toArray();
            $current_date_time = $time_data['formatted'];
            $check_start_date = OnlineExam::where('id' , $request->exam_id)->where('start_date','>',$current_date_time)->count();
            if($check_start_date != 0){
                $response = array(
                    'error' => true,
                    'message' => trans('exam_not_started_yet'),
                    'code' => 200,
                );
                return response()->json($response);
            }

            // add the exam status
            $student_exam_status = new StudentOnlineExamStatus();
            $student_exam_status->online_exam_id = $request->exam_id;
            $student_exam_status->student_id = $student->user_id;
            $student_exam_status->status = 1;
            $student_exam_status->save();

            // get total questions
            $total_questions = OnlineExamQuestionChoice::where('online_exam_id',$request->exam_id)->count();

            // get the questions data
            $get_exam_questions_db = OnlineExamQuestionChoice::where('online_exam_id',$request->exam_id)->with('questions')->get();
            $questions_data = array();
            $total_marks = 0;
            foreach ($get_exam_questions_db as $exam_questions) {
                $total_marks += $exam_questions->marks;

                // make options array
                $options_data = array();
                foreach ($exam_questions->questions->options as $question_options) {
                    $options_data[] = array(
                        'id' => $question_options->id,
                        'option' => htmlspecialchars_decode($question_options->option)
                    );
                }

                // make answers array
                $answers_data = array();
                foreach ($exam_questions->questions->answers as $question_answers) {
                    $answers_data[] = array(
                        'id' => $question_answers->id,
                        'option_id' => $question_answers->answer,
                        'answer' => htmlspecialchars_decode($question_answers->options->option)
                    );
                }

                // make question array
                $questions_data[] = array(
                    'id' => $exam_questions->id,
                    'question' => htmlspecialchars_decode($exam_questions->questions->question),
                    'question_type' => $exam_questions->questions->question_type,
                    'options' => $options_data,
                    'answers' => $answers_data,
                    'marks' => $exam_questions->marks,
                    'image' => $exam_questions->questions->image_url,
                    'note' => $exam_questions->questions->note,
                );
            }
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $questions_data ?? null,
                'total_questions' => $total_questions,
                'total_marks'=> $total_marks,
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
    public function submitOnlineExamAnswers(Request $request){
        $validator = Validator::make($request->all(), [
            'online_exam_id' => 'required|numeric',
            'answers_data' => 'required|array',
            'answers_data.*.question_id' => 'required|numeric',
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
            $student = $request->user()->student;

            // checks the online exam exists
            $check_online_exam_id = OnlineExam::where('id',$request->online_exam_id)->count();
            if($check_online_exam_id){

                $answers_exists = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $request->online_exam_id])->count();
                if($answers_exists){
                    $response = array(
                        'error' => true,
                        'message' => 'Answers already submitted',
                        'code' => 103,
                    );
                    return response()->json($response);
                }

                foreach ($request->answers_data as $answer_data) {

                    // checks the question exists with provided exam id
                    $check_question_exists = OnlineExamQuestionChoice::where(['id' => $answer_data['question_id']])->count();
                    if($check_question_exists){

                        // get the question id from question choiced
                        $question_id = OnlineExamQuestionChoice::where(['id' => $answer_data['question_id'] ,'online_exam_id' => $request->online_exam_id])->pluck('question_id')->first();

                        // checks the option exists with provided question
                        $check_option_exists = OnlineExamQuestionOption::where(['id' => $answer_data['option_id'] , 'question_id' => $question_id])->count();

                        //get the current date
                        $currentTime = Carbon::now();
                        $current_date = date($currentTime->toDateString());

                        if($check_option_exists){
                            foreach ($answer_data['option_id'] as $options) {
                                // add the data of answers
                                $store_answers = new OnlineExamStudentAnswer();
                                $store_answers->student_id = $student->user_id;
                                $store_answers->online_exam_id = $request->online_exam_id;
                                $store_answers->question_id = $answer_data['question_id'];
                                $store_answers->option_id = $options;
                                $store_answers->submitted_date = $current_date;
                                $store_answers->save();
                            }

                            $student_exam_status_id = StudentOnlineExamStatus::where(['student_id' => $student->user_id , 'online_exam_id' => $request->online_exam_id])->pluck('id')->first();
                            if(isset($student_exam_status_id) && !empty($student_exam_status_id)){
                                $update_status = StudentOnlineExamStatus::find($student_exam_status_id);
                                $update_status->status = 2;
                                $update_status->save();
                            }
                        }
                    }else{
                        $response = array(
                            'error' => true,
                            'message' => trans('invalid_question_id'),
                            'code' => 103
                        );
                        return response()->json($response);
                    }
                }
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                    'code' => 200,
                );
                return response()->json($response);
            }else{
                $response = array(
                    'error' => true,
                    'message' => trans('invalid_online_exam_id'),
                    'code' => 103
                );
                return response()->json($response);
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
    public function getOnlineExamReport(Request $request){
        $validator = Validator::make($request->all(), [
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
            $student = $request->user()->student;
            $class_section_id = $student->class_section_id;
            $class_id = $student->class_section->class_id;


            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            //get current
            $time_data = Carbon::now()->toArray();
            $current_date_time = $time_data['formatted'];

            $exam_query = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'subject_id' => $request->subject_id , 'session_year_id' => $session_year_id, ['start_date','<=',$current_date_time]])->orWhere(function($query) use ($class_id,$session_year_id,$request,$current_date_time) {
                $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'subject_id' => $request->subject_id , 'session_year_id' => $session_year_id, ['start_date','<=',$current_date_time]]);
            });
            $exam_exists = $exam_query->count();
            $exam_query_without_session_year = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'subject_id' => $request->subject_id , ['start_date','<=',$current_date_time]])->orWhere(function($query) use ($class_id,$session_year_id,$request,$current_date_time) {
                $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'subject_id' => $request->subject_id , ['start_date','<=',$current_date_time]]);
            });

            // checks the exams exists
            if(isset($exam_exists) && !empty($exam_exists)){
                //total online exams id and counts
                $total_exam_ids = $exam_query->pluck('id');
                //online exam ids attempted
                $attempted_online_exam_ids = StudentOnlineExamStatus::where('student_id',$student->user_id)->whereIn('online_exam_id',$total_exam_ids)->pluck('online_exam_id');

                //get the submitted answers (i.e. option id)
                $online_exams_attempted_answers = OnlineExamStudentAnswer::where('student_id',$student->user_id)->whereIn('online_exam_id',$total_exam_ids)->pluck('option_id');

                //get the submitted choiced question id
                $online_exams_submitted_question_ids = OnlineExamStudentAnswer::where('student_id',$student->user_id)->whereIn('online_exam_id',$total_exam_ids)->pluck('question_id');

                //get the questions id
                $get_question_ids = OnlineExamQuestionChoice::whereIn('id',$online_exams_submitted_question_ids)->pluck('question_id');

                //removes the question id of the question if one of the answer of particular question is wrong
                foreach ($get_question_ids as $question_id) {
                    $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id',$question_id)->whereNotIn('answer',$online_exams_attempted_answers)->count();
                    if($check_questions_answers_exists){
                        unset($get_question_ids[array_search($question_id, $get_question_ids->toArray())]);
                    }
                }
                //get the correct answers question id
                $correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id',$get_question_ids)->whereIn('answer',$online_exams_attempted_answers)->pluck('question_id');


                //total exams
                $total_exams = $exam_query_without_session_year->count();

                //total exam attempted
                $total_attempted_exams = StudentOnlineExamStatus::where('student_id',$student->user_id)->whereIn('online_exam_id',$total_exam_ids)->count();

                // total missed exams
                $total_missed_exams = $exam_query_without_session_year->whereNotIn('id',$attempted_online_exam_ids)->count();

                // get the correct choiced question id and marks
                $total_obtained_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->whereIn('online_exam_id',$total_exam_ids)->whereIn('question_id',$correct_answers_question_id)->first();
                $total_obtained_marks = $total_obtained_marks['sum(marks)'];

                //overall total marks
                $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->whereIn('online_exam_id',$total_exam_ids)->first();
                $total_marks = $total_marks['sum(marks)'];

                if($total_obtained_marks){
                    $percentage = number_format(($total_obtained_marks * 100) / $total_marks,2);
                }


                // particular online exam data
                $online_exams_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'subject_id' => $request->subject_id , 'session_year_id' => $session_year_id, ['start_date','<=',$current_date_time]])->orWhere(function($query) use ($class_id,$session_year_id,$request,$current_date_time) {
                    $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'subject_id' => $request->subject_id , 'session_year_id' => $session_year_id, ['start_date','<=',$current_date_time]]);
                })->with(['student_attempt' => function($q) use($student){
                    $q->where('student_id',$student->user_id);
                }])->has('question_choice')->paginate(10)->toArray();


                $exam_list = array();
                $total_obtained_marks_exam = '';
                foreach ($online_exams_db['data'] as $data) {
                    $exam_submitted_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id , 'online_exam_id' => $data['id']])->pluck('question_id');
                    $get_exam_question_ids = OnlineExamQuestionChoice::whereIn('id',$exam_submitted_question_ids)->pluck('question_id');


                    $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('option_id');


                    //removes the question id of the question if one of the answer of particular question is wrong
                    foreach ($get_exam_question_ids as $question_id) {
                        $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id',$question_id)->whereNotIn('answer',$exam_attempted_answers)->count();
                        if($check_questions_answers_exists){
                            unset($get_exam_question_ids[array_search($question_id, $get_exam_question_ids->toArray())]);
                        }
                    }

                    $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id',$get_exam_question_ids)->whereIn('answer',$exam_attempted_answers)->pluck('question_id');

                    $total_obtained_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$data['id'])->whereIn('question_id',$exam_correct_answers_question_id)->first();
                    $total_obtained_marks_exam = $total_obtained_marks_exam['sum(marks)'];
                    $total_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$data['id'])->first();
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
    public function getAssignmentReport(Request $request){
        $validator = Validator::make($request->all(), [
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
            $student = $request->user()->student;

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // get the assignments ids
            $assingment_ids = Assignment::where(['class_section_id' => $student->class_section_id,'session_year_id' => $session_year_id , 'subject_id' => $request->subject_id])->pluck('id');

            //total assignments of class
            $total_assignments = Assignment::where(['class_section_id' => $student->class_section_id,'session_year_id' => $session_year_id , 'subject_id' => $request->subject_id])->count();

            //total assignment submiited
            $total_submitted_assignments = AssignmentSubmission::where('student_id' , $student->user_id)->whereIn('assignment_id',$assingment_ids)->count();

            // submitted assingment id
            $submitted_assignment_ids = AssignmentSubmission::where('student_id' , $student->user_id)->whereIn('assignment_id',$assingment_ids)->pluck('assignment_id');

            //total assignment unsubmitted
            $total_assingment_unsubmitted = Assignment::where(['class_section_id' => $student->class_section_id , 'subject_id' => $request->subject_id])->whereNotIn('id',$submitted_assignment_ids)->count();

            //total points of assignment submitted
            $total_assignment_submitted_points = Assignment::select(DB::raw("sum(points)"))->where('class_section_id' , $student->class_section_id)->whereIn('id',$submitted_assignment_ids)->whereNot('points',null)->first();
            $total_assignment_submitted_points = $total_assignment_submitted_points['sum(points)'];

            // total obtained assignment points
            $assingment_id_with_points = Assignment::where(['class_section_id' => $student->class_section_id , 'subject_id' => $request->subject_id])->whereIn('id',$submitted_assignment_ids)->whereNot('points',null)->pluck('id');
            $total_points_obtained = AssignmentSubmission::select(DB::raw("sum(points)"))->whereIn('assignment_id',$assingment_id_with_points)->first();
            $total_points_obtained = $total_points_obtained['sum(points)'];

            if($total_points_obtained){
                //percentage
                $percentage = number_format(($total_points_obtained*100) / $total_assignment_submitted_points,2);
            }

            $submitted_assignment_data_db = Assignment::with('submission')->where(['class_section_id' => $student->class_section_id , 'subject_id' => $request->subject_id])->whereIn('id',$submitted_assignment_ids)->whereNot('points',null);
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
        return response()->json($response,200,[],JSON_PRESERVE_ZERO_FRACTION);
    }
    public function getOnlineExamResultList(Request $request){
        try{
            $student = $request->user()->student;
            $class_section_id = $student->class_section_id;
            $class_id = $student->class_section->class_id;

            // current session year id
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // get the class subject id on the basis of subject id passed
            // query meets the condition for both class section and class
            if(isset($request->subject_id) && !empty($request->subject_id)){
                $online_exam_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'subject_id' => $request->subject_id , 'session_year_id' => $session_year_id])->whereHas('student_attempt' , function($q) use($student){
                    $q->where('student_id',$student->user_id);
                })->orWhere(function($query) use ($class_id,$session_year_id,$request,$student) {
                        $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'session_year_id' => $session_year_id , 'subject_id' => $request->subject_id])->with('subject')->whereHas('student_attempt' , function($q) use($student){
                            $q->where('student_id',$student->user_id);
                        });
                })->with('subject')->paginate(10)->toArray();
            }else{
                $online_exam_db = OnlineExam::where(['model_type' => 'App\Models\ClassSection' , 'model_id' => $class_section_id , 'session_year_id' => $session_year_id])->whereHas('student_attempt' , function($q) use($student){
                    $q->where('student_id',$student->user_id);
                })->orWhere(function($query) use ($class_id,$session_year_id,$student) {
                        $query->where(['model_type' => 'App\Models\ClassSchool' , 'model_id' => $class_id , 'session_year_id' => $session_year_id ])->with('subject')->whereHas('student_attempt' , function($q) use($student){
                            $q->where('student_id',$student->user_id);
                        });
                })->with('subject')->paginate(10)->toArray();
            }
            $exam_list_data = array();
            foreach ($online_exam_db['data'] as $data) {
                //get the choice question id
                $exam_submitted_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id , 'online_exam_id' => $data['id']])->pluck('question_id');
                $exam_submitted_date = OnlineExamStudentAnswer::where(['student_id' => $student->user_id , 'online_exam_id' => $data['id']])->pluck('submitted_date')->first();

                $question_ids = OnlineExamQuestionChoice::whereIn('id',$exam_submitted_question_ids)->pluck('question_id');


                $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $data['id']])->pluck('option_id');

                //removes the question id of the question if one of the answer of particular question is wrong
                foreach ($question_ids as $question_id) {
                    $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id',$question_id)->whereNotIn('answer',$exam_attempted_answers)->count();
                    if($check_questions_answers_exists){
                        unset($question_ids[array_search($question_id, $question_ids->toArray())]);
                    }
                }

                $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id',$question_ids)->whereIn('answer',$exam_attempted_answers)->pluck('question_id');

                // get the data of only attempted data
                $total_obtained_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$data['id'])->whereIn('question_id',$exam_correct_answers_question_id)->first();
                $total_obtained_marks_exam = $total_obtained_marks_exam['sum(marks)'];
                $total_marks_exam = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$data['id'])->first();
                $total_marks_exam = $total_marks_exam['sum(marks)'];

                $exam_list_data[] = array(
                    'online_exam_id' => $data['id'],
                    'subject' => array(
                        'id' => $data['subject_id'],
                        'name' => $data['subject']['name'].' - '.$data['subject']['type'],
                    ),
                    'title' => $data['title'],
                    'obtained_marks' => $total_obtained_marks_exam ?? "0",
                    'total_marks' => $total_marks_exam ?? "0",
                    'exam_submitted_date' => $exam_submitted_date ??  date('Y-m-d', strtotime($data['end_date']))
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
    public function getOnlineExamResult(Request $request){
        $validator = Validator::make($request->all(), [
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
            $student = $request->user()->student;

            //get the total questions count
            $total_questions = OnlineExamQuestionChoice::where('online_exam_id' , $request->online_exam_id)->count();

            //get the exam's choiced question id
            $exam_choiced_question_ids = OnlineExamStudentAnswer::where(['student_id' => $student->user_id , 'online_exam_id' => $request->online_exam_id])->pluck('question_id');

            //get the questions id
            $question_ids = OnlineExamQuestionChoice::whereIn('id',$exam_choiced_question_ids)->pluck('question_id');

            //get the options submitted by student
            $exam_attempted_answers = OnlineExamStudentAnswer::where(['student_id' => $student->user_id, 'online_exam_id' => $request->online_exam_id])->pluck('option_id');

            //removes the question id of the question if one of the answer of particular question is wrong
            foreach ($question_ids as $question_id) {
                $check_questions_answers_exists = OnlineExamQuestionAnswer::where('question_id',$question_id)->whereNotIn('answer',$exam_attempted_answers)->count();
                if($check_questions_answers_exists){
                    unset($question_ids[array_search($question_id, $question_ids->toArray())]);
                }
            }

            // get the correct answers counter
            $exam_correct_answers = OnlineExamQuestionAnswer::whereIn('question_id',$question_ids)->whereIn('answer',$exam_attempted_answers)->groupby('question_id')->pluck('question_id')->count();

            // question id of correct answers
            $exam_correct_answers_question_id = OnlineExamQuestionAnswer::whereIn('question_id',$question_ids)->whereIn('answer',$exam_attempted_answers)->pluck('question_id');

            //data of correct answers
            $exam_correct_answers_data = OnlineExamQuestionAnswer::whereIn('question_id',$question_ids)->whereIn('answer',$exam_attempted_answers)->groupby('question_id')->get();

            // array of correct answer with choiced exam id and marks
            $correct_answers_data = array();
            foreach ($exam_correct_answers_data as $correct_data) {
                $choice_questions = OnlineExamQuestionChoice::where(['online_exam_id' => $request->online_exam_id , 'question_id' => $correct_data->question_id])->first();
                $correct_answers_data[] = array(
                    'question_id' => $choice_questions->id,
                    'marks' => $choice_questions->marks
                );

            }

            // get questions ids
            $all_questions_ids = OnlineExamQuestionChoice::whereNotIn('question_id',$question_ids)->where('online_exam_id',$request->online_exam_id)->pluck('question_id');

            // get the incorrect answers && unattempted counter
            $exam_in_correct_answers = OnlineExamQuestionAnswer::whereIn('question_id',$all_questions_ids)->whereNotIn('answer',$exam_attempted_answers)->groupby('question_id')->pluck('question_id')->count();

            // data of in correct && unattempted answers
            $exam_in_correct_answers_data = OnlineExamQuestionAnswer::whereIn('question_id',$all_questions_ids)->whereNotIn('answer',$exam_attempted_answers)->groupby('question_id')->get();

            // array of in correct answer && unattempted with choiced exam id and marks
            $in_correct_answers_data = array();
            foreach ($exam_in_correct_answers_data as $in_correct_data) {
                $choice_questions = OnlineExamQuestionChoice::where(['online_exam_id' => $request->online_exam_id , 'question_id' => $in_correct_data->question_id])->first();
                if(isset($choice_questions) && !empty($choice_questions)){
                    $in_correct_answers_data[] = array(
                        'question_id' => $choice_questions->id,
                        'marks' => $choice_questions->marks
                    );
                }
            }

            // total obtained and total marks
            $total_obtained_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$request->online_exam_id)->whereIn('question_id',$exam_correct_answers_question_id)->first();
            $total_obtained_marks = $total_obtained_marks['sum(marks)'];
            $total_marks = OnlineExamQuestionChoice::select(DB::raw("sum(marks)"))->where('online_exam_id',$request->online_exam_id)->first();
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
    public function getProfileDetails(){
        try{
            $user = Auth::user()->load(['student.class_section', 'student.category']);
            //Set Class Section name
            $user->class_section_name = $user->student->class_section->class->name . " " . $user->student->class_section->section->name;
            //Set Medium name
            $user->medium_name = $user->student->class_section->class->medium->name;
            unset($user->student->class_section);

            //Set Category name
            $user->category_name = $user->student->category->name;
            unset($user->student->category);
            $response = array(
                'error' => false,
                'message' => 'Data Fetched Successfully',
                'data' => flattenMyModel($user),
                'code' => 100,
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
}
