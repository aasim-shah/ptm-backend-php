<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Models\Attendance;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\MainMeeting;
use App\Models\Meetings;
use App\Models\Parents;
use App\Models\Schools;
use App\Models\Settings;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\StudentSubject;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Support\Access;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;
use Spatie\Permission\Models\Role;
use stdClass;
use function Termwind\style;


class StudentController
{

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }
    public function classSectionStudents(Request $request)
    {
        $name = $request->get('name');
        $userID = $request->user()->id;
        $class_id = $request->class_id;

        $students = User::where(function ($query) use ($name) {
            if($name){
                $query->where('name', 'LIKE', '%'.$name.'%');

            }
        })
            ->where('type', 'student')
            ->with('student.class_section.class')
            ->with('student.father')
            ->with('student.mother')
            ->with('userSchool.school')
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->where('class_status','ACCEPTED');
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->orderBy('first_name', 'ASC')->get();

        if ($students) {
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $students
            );
        } else {
            $response = array(
                'error' => true,
                'message' => trans('no_data_found'),
                'data' => $students
            );
        }

        return response()->json($response);
    }
    public function classSectionStudentsRequests(Request $request)
    {
        $name = $request->get('name');
        $userID = $request->user()->id;
        $class_id = $request->class_id;

        $students = User::where(function ($query) use ($name) {
            if($name){
                $query->where('name', 'LIKE', '%'.$name.'%');

            }
        })
            ->where('type', 'student')
            ->with('student.class_section.class')
            ->with('student.father')
            ->with('student.mother')
            ->with('userSchool.school')
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->where('class_status','PENDING');
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->orderBy('first_name', 'ASC')->get();

        if ($students) {
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $students
            );
        } else {
            $response = array(
                'error' => true,
                'message' => trans('no_data_found'),
                'data' => $students
            );
        }

        return response()->json($response);
    }

    public function createStudent(Request $request)
    {

        $user_ = $request->user();
        $fatherId = null;
        $motherId = null;
        $file_path = null;
        if ($user_->type == 'parent') {
            if(!$user_->parent){
                $response = array(
                    'error' => true,
                    'message' => "Failed to create student. No parents are associated with your account",
                    'data' => null
                );
                return response()->json($response);
            }
            if ($user_->gender == 'male') {
                $fatherId = $user_->parent->user_id;
            } else {
                $motherId = $user_->parent->user_id;
            }
        } else {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);

        }

        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'dob' => 'required',
            'gender' => 'required',
            'relationship' => 'required'
        ]);
        $student_image = $request->file('image');
        if($student_image){
            Log::info($student_image->getClientOriginalName());
            try{
               $ext = explode("/",$student_image->getClientOriginalName())[1];
            }catch (\Exception $e){
                $ext = explode(".",$student_image->getClientOriginalName())[1];
            }
            $file_name = Utility::getUUID().".".$ext;
            $file_path = 'students/' .$file_name ;
            resizeImage($student_image);
            $destinationPath = storage_path('app/public/students');
            $student_image->move($destinationPath, $file_name);
        }


        $user = new User();
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->image = $file_path;
        $user->gender = $request->gender;
        $user->dob = $request->dob;
        $user->type = 'student';
        $user->status = 1;
        $user->reset_request = 0;
        $user->save();
        $user = $user->refresh();

        $student = new Students();
        $student->user_id = $user->id;
        $student->relationship = $request->relationship;
        $student->is_new_admission = 1;
        $student->father_id = $fatherId;
        $student->mother_id = $motherId;
        $student->save();
        $response = array(
            'error' => false,
            'message' => 'Student created Successfully.',
            'data' => $user,
            'code' => 200,
        );
        return response()->json($response);
    }
    public function updateStudent(Request $request)
    {
        $student_id = $request->student_id;
        $user_ = $request->user();
        if ($user_->type != 'parent') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }

        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'dob' => 'required',
            'gender' => 'required',
            'relationship' => 'required'
        ]);
        $student_image = $request->file('image');
        $file_path = null;

        $student = Students::where('user_id',$student_id)
            ->where(function ($query) use($user_){
                $query->where('mother_id',$user_->id);
                $query->orWhere('father_id',$user_->id);
            })->first();
        if(!$student){
            $response = array(
                'error' => true,
                'message' => "Invalid student detected.",
                'data' => null
            );
            return response()->json($response);
        }

        if($student_image){
            try{
                $ext = explode("/",$student_image->getClientOriginalName())[1];
            }catch (\Exception $e){
                $ext = explode(".",$student_image->getClientOriginalName())[1];
            }
            $file_name = Utility::getUUID().".".$ext;
            $file_path = 'students/' . $file_name;
            resizeImage($student_image);
            $destinationPath = storage_path('app/public/students');
            $student_image->move($destinationPath, $file_name);
        }

        $user = User::where('id',$student_id)->whereHas('student')->first();


        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        if($file_path){
            $user->image = $file_path;
        }
        if($request->dob){
            $user->dob = $request->dob;
        }
        if($request->gender){
            $user->gender = $request->gender;
        }
        $user->save();
        $user = $user->refresh();

        if($request->relationship){
            $student->relationship = $request->relationship;
        }
        $student->is_new_admission = 1;
        $student->save();

        $response = array(
            'error' => false,
            'message' => 'Student updated Successfully.',
            'data' => $user,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function assignStudentToClassSection(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'user_id' => 'required',
            'class_id' => 'required',
            'school_id' => 'required'
        ]);
        $class = ClassSection::where('class_id', $request->class_id)->with('class')->first();

        $isSchoolsAssigned = Students::where('class_section_id',$class->id)->where('user_id', $request->user_id)->first();
        if($isSchoolsAssigned){
            return response()->json([
                'status' => 400,
                'message' => "This student is already associated with this class",
            ], 400);
        }
        $student = Students::where('user_id', $request->user_id)->with('user')->first();
        if(!$student){
            return response()->json([
                'status' => 400,
                'message' => "Invalid student provided.",
            ], 400);
        }
        $student->class_section_id = $class->id;
        $student->class_status = 'PENDING';
        $student->save();
        $student = $student->refresh();
        if($student){
            $message = "New student request available for the class ".$class->class->name;
            $this->notificationSender->sendNotification(
                "Student Request",
                $message,
                [$class->class_teacher_id], "STUDENT_REQUEST", ['student'=>$student,'class'=>$class]);
            $this->notificationSender->saveLogs($class->class_teacher_id,$user->id,$message,'STUDENT_REQUEST','New student request');
        }

        return response()->json($student, 200);
    }
    public function removeStudentFromClassSection(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'user_id' => 'required',
            'class_id' => 'required',
        ]);
        $class = ClassSection::where('class_id', $request->class_id)->with('class')->first();

        $isSchoolsAssigned = Students::where('class_section_id',$class->id)->with('user')->where('user_id', $request->user_id)->first();
        if($isSchoolsAssigned){
            $isSchoolsAssigned->class_section_id = null;
            $isSchoolsAssigned->class_status = null;
            $isSchoolsAssigned->save();
        }

        $classSubjects = ClassSubject::where('class_id',$request->class_id)->pluck('subject_id')->toArray();
        $session_year_id = Settings::select('message')->where('type', 'session_year')->pluck('message')->first();

        foreach ($classSubjects as $classSubject) {
             StudentSubject::where([
                'student_id' => $request->user_id,
                'subject_id' => $classSubject,
                'class_section_id' => $class->id,
                'session_year_id' => intval($session_year_id)
            ])->delete();
        }

       $meeting = Meetings::where('student_id',$request->user_id)->first();
        if($meeting){
           $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
            if($meetingParticipantsCount == 1){
                MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
            }
            Meetings::where('student_id',$request->user_id)->delete();
        }
//        Attendance::where('student_id',$request->user_id)->where('class_section_id',$class->id)->delete();
//        StudentReportCards::where('student_id',$request->user_id)->where('class_id',$request->class_id)->delete();
//        StudentQuarter::where('student_id',$request->user_id)->where('class_id',$request->class_id)->delete();

        if($user->type == 'parent'){
            $message = "Student ".$isSchoolsAssigned->user->first_name." ".$isSchoolsAssigned->user->last_name.
                " has been removed from ".$class->class->name." by parent";
            $this->notificationSender->sendNotification(
                "Student Removed",
                $message,
                [$class->class_teacher_id], "STUDENT_REMOVED", $meeting);
            $this->notificationSender->saveLogs($class->class_teacher_id,$user->id,$message,'STUDENT_REMOVED','Student removed from the class');

        }else{
            $message = "Student ".$isSchoolsAssigned->user->first_name." ".$isSchoolsAssigned->user->last_name.
                " has been removed from ".$class->class->name." by teacher";
            $this->notificationSender->sendNotification(
                "Student Removed",
                $message,
                [$isSchoolsAssigned->mother_id,$isSchoolsAssigned->father_id], "STUDENT_REMOVED", $meeting);
            $this->notificationSender->saveLogs($isSchoolsAssigned->mother_id ?? $isSchoolsAssigned->father_id,$user->id,$message,'STUDENT_REMOVED','Student removed from the class');

        }
        return response()->json([
            "error" => false,
            "status" => 'success',
            "message" => 'student removed from the class'
        ]);
    }

    public function acceptOrRejectStudentFromClassSection(Request $request){

        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'class_id' => 'required',
            'user_id' => 'required',
            'status' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }
        $student = Students::where('user_id', $request->user_id)->with('user')->first();
        if(!$student){
            return response()->json([
                'status' => 400,
                'message' => "Invalid student provided.",
            ], 400);
        }
        $class = ClassSection::where('class_id', $request->class_id)->first();
        $classObj = ClassSchool::where('id', $request->class_id)->first();

        if($request->status){
            $student->class_section_id = $class->id;
            $student->class_status = 'ACCEPTED';
            $student->save();
            $classSubjects = ClassSubject::where('class_id',$request->class_id)->pluck('subject_id')->toArray();
            $session_year_id = Settings::select('message')->where('type', 'session_year')->pluck('message')->first();
            $student_subject = array();

            foreach ($classSubjects as $classSubject) {

                $if_subject_already_selected = StudentSubject::where([
                    'student_id' => $request->user_id,
                    'subject_id' => $classSubject,
                    'class_section_id' => $class->id,
                    'session_year_id' => intval($session_year_id)
                ])->first();
                if (!$if_subject_already_selected) {
                    $student_subject[] = array(
                        'student_id' => $request->user_id,
                        'subject_id' => $classSubject,
                        'class_section_id' => $class->id,
                        'session_year_id' => intval($session_year_id)
                    );
                }
            }
            StudentSubject::insert($student_subject);

            $userSchool = UserSchools::where('user_id',$student->mother_id ?? $student->father_id)->where('school_id',$classObj->school_id)->first();
            if(!$userSchool){
                $userSchool = new UserSchools();
                $userSchool->id = Utility::getUUID();
                $userSchool->user_id = $student->mother_id ?? $student->father_id;
                $userSchool->school_id = $classObj->school_id;
                $userSchool->save();
            }
            $student = $student->refresh();

            $message = "Student ".$student->user->first_name." ".$student->user->last_name.
                " has been accepted to ".$classObj->name." by the teacher";
            $this->notificationSender->sendNotification(
                "Student Accepted",
                $message,
                [$student->mother_id,$student->father_id], "STUDENT_ACCEPTED", $student);
            $this->notificationSender->saveLogs($student->mother_id ?? $student->father_id,$user->id,$message,
                'STUDENT_ACCEPTED','Student accepted by the teacher');

            $response = array(
                'error' => false,
                'message' => 'Students assigned Successfully.',
                'data' => $student,
                'code' => 200,
            );
            return response()->json($response, 200);
        }else{
            $student->class_section_id = null;
            $student->class_status = 'REJECTED';
            $student->save();
            $student = $student->refresh();

            $message = "Student ".$student->user->first_name." ".$student->user->last_name.
                " has been rejected from ".$classObj->name." by the teacher";
            $this->notificationSender->sendNotification(
                "Student Rejected",
                $message,
                [$student->mother_id,$student->father_id], "STUDENT_REJECTED", $student);
            $this->notificationSender->saveLogs($student->mother_id ?? $student->father_id,$user->id,$message,
                'STUDENT_REJECTED','Student rejected by the teacher');

            $response = array(
                'error' => false,
                'message' => 'Students rejected Successfully.',
                'data' => $student,
                'code' => 200,
            );
            return response()->json($response, 200);

        }

    }


    public function studentList(Request $request)
    {
        $user = $request->user();
        $classIds = null;
        $fatherId = null;
        $motherId = null;

        $firstName = trim($request->get('first_name')) ?? null;
        $lastName = trim($request->get('last_name')) ?? null;

        if ($user->type == 'parent') {
            if ($user->gender == 'male') {
                $fatherId = $user->parent->user_id;
            } else {
                $motherId = $user->parent->user_id;
            }
        } elseif ($user->type == 'teacher') {
            if ($request->class_id) {
                $classIds = [$request->class_id];
            } else {
                $classIds = ClassSection::where('class_teacher_id', $user->id)->pluck('class_id')->toArray();
            }
        }

        if ($classIds) {
            $students = User::where(function ($query) use ($firstName, $lastName) {
                if ($firstName && !$lastName) {
                    $query->where('first_name', 'LIKE', '%'.$firstName.'%');
                }
                if (!$firstName && $lastName) {
                    $query->where('last_name', 'LIKE', '%'.$lastName.'%');
                }
                if ($firstName && $lastName) {
                    $query->where('first_name', 'LIKE', '%'.$firstName.'%');
                    $query->orWhere('last_name', 'LIKE', '%' . $lastName . '%');
                }
            })->whereHas('student', function ($query) use ($classIds) {
                $query->where('class_status','ACCEPTED');
                $query->whereHas('class_section', function ($query) use ($classIds) {
                    $query->whereIn('class_id', $classIds);
                });
            })
                ->with('student.class_section.class.school')->paginate(50);

        } else {
            $students = User::whereHas('student', function ($query) use ($fatherId, $motherId) {
                if ($fatherId) {
                    $query->where('father_id', $fatherId);
                } elseif ($motherId) {
                    $query->where('mother_id', $motherId);
                }
            })->where(function ($query) use ($firstName, $lastName) {
                if ($firstName && !$lastName) {
                    $query->where('first_name', 'LIKE', '%'.$firstName.'%');
                }
                if (!$firstName && $lastName) {
                    $query->where('last_name', 'LIKE', '%'.$lastName.'%');
                }
                if ($firstName && $lastName) {
                    $query->where('first_name', 'LIKE', '%'.$firstName.'%');
                    $query->orWhere('last_name', 'LIKE', '%' . $lastName . '%');
                }
            })
                ->with('student.class_section.class.school')
                ->with(['student' => function($query){
                    $query->withCount('student_subjects');
                }])
                ->paginate(50);
        }
        $response = array(
            'error' => false,
            'message' => 'Students Fetched Successfully.',
            'data' => $students,
            'code' => 200,
        );
        return response()->json($response, 200);

    }

    public function findStudent(Request $request)
    {
        $id = $request->user_id;
        $user = User::where('id', $id)->whereHas('student')->first();
        $response = array(
            'error' => false,
            'message' => 'Student Fetched Successfully.',
            'data' => $user,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function deleteStudent(Request $request)
    {

        $user = $request->user();
        $user_id = $request->user_id;
        if ($user && $user->type != 'parent') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
        $parentId = $user->parent->user_id;
        $student = Students::where('user_id',$user_id)->with('user')
            ->where(function ($query) use($parentId){
                $query->where('mother_id',$parentId);
                $query->orWhere('father_id',$parentId);
            })->first();
        if(!$student){
            $response = array(
                'error' => true,
                'message' => "Invalid student detected.",
                'data' => null
            );
            return response()->json($response);
        }

        $classSection = ClassSection::where('id',$student->class_section_id)->first();

        Attendance::where('student_id',$request->user_id)->delete();
        StudentReportCards::where('student_id',$request->user_id)->delete();
        StudentQuarter::where('student_id',$request->user_id)->delete();

        $meeting = Meetings::where('student_id',$request->user_id)->first();
        if($meeting){
            $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
            if($meetingParticipantsCount == 1){
                MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
            }
            Meetings::where('student_id',$request->user_id)->delete();
        }

        if($classSection){
            $message = "Student ".$student->user->first_name." ".$student->user->last_name.
                " has been removed by the parent";
            $this->notificationSender->sendNotification(
                "Student Removed",
                $message,
                [$classSection->class_teacher_id], "STUDENT_REMOVED", $meeting);
            $this->notificationSender->saveLogs($classSection->class_teacher_id,$user->id,$message,'STUDENT_REMOVED','Student removed by the parent');
        }

        $user = User::where('id', $user_id)->whereHas('student', function ($query) use ($parentId) {
            $query->where(function ($query) use ($parentId) {
                $query->where('father_id', $parentId)
                    ->orWhere('mother_id', $parentId);
            });
        })->delete();
        Students::where('user_id',$user_id)->delete();

        $response = array(
            'error' => false,
            'message' => 'Student deleted Successfully.',
            'data' => $user,
            'code' => 200
        );
        return response()->json($response);
    }

    public function parentSchools(Request $request)
    {
        $userId = $request->user()->id;
        $schoolName = $request->get('name');

        $schools = Schools::where(function ($query) use ($schoolName) {
            if ($schoolName) {
                $query->where('school_name', 'LIKE', "%{$schoolName}%");
            }
        })->whereHas('userSchools', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();
        $response = array(
            'error' => false,
            'message' => 'Schools fetched Successfully.',
            'data' => $schools,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function schoolTeacher(Request $request)
    {
        $userId = $request->user()->id;
        $school_id  = $request->school_id;
        if($request->user()->type == 'teacher'){
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        if(!$school_id){
            $schoolIds = UserSchools::where('user_id', $userId)->pluck('school_id')->toArray();
            $userIds = UserSchools::whereIn('school_id', $schoolIds)->pluck('user_id')->toArray();
            $teachers = User::whereHas('teacher')->with('userSchool.school')->whereIn('id', $userIds)->get();
        }else{
            $userIds = UserSchools::where('school_id', $school_id)->pluck('user_id')->toArray();
            $teachers = User::whereHas('teacher')->with('userSchool.school')->whereIn('id', $userIds)->get();
        }

        $response = array(
            'error' => false,
            'message' => 'School teachers fetched Successfully.',
            'data' => $teachers,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function studentReport(Request $request){
        if ($request->student_id && !Access::canViewStudent($request->user(), $request->student_id)) {
            return Access::forbidden();
        }
        $user = $request->user();
        $user_id = $request->user_id;
        $quarter_id = $request->quarter_id ?? null;
        $session_year = $request->session_year_id ?? null;
        $student_id = $request->student_id;
        if ($user && $user->type != 'parent') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
//        $studentReport = StudentReportCards::with('quarter')->with('subject')->where(function ($query) use ($quarter_id,$session_year) {
//            if ($quarter_id) {
//                $query->where('quarter_id', $quarter_id);
//            }
//            if ($session_year) {
//                $query->where('session_year_id', $session_year);
//            }
//        })->where('student_id',$student_id)
//             ->get();

        $published = StudentQuarter::where('student_id',$student_id)
            ->where(function ($query) use ($quarter_id,$session_year){
                if($quarter_id){
                    $query->where('quarter_id',$quarter_id);
                }
                if ($session_year) {
                    $query->where('session_year_id', $session_year);
                }
            })->whereHas('quarter',function ($query) use ($quarter_id,$student_id,$session_year){
                $query->where('id',$quarter_id);
                $query->whereHas('studentReportCards',function ($query) use ($quarter_id,$student_id,$session_year){
                    $query->where('quarter_id',$quarter_id);
                    $query->where('student_id',$student_id);
                    if ($session_year) {
                        $query->where('session_year_id', $session_year);
                    }
                });
            })->with(['quarter' => function($query) use($quarter_id,$student_id,$session_year){
                $query->where('id',$quarter_id);
                $query->with(['studentReportCards' => function($query) use($quarter_id,$student_id,$session_year){
                    $query->where('quarter_id',$quarter_id);
                    $query->where('student_id',$student_id);
                    if ($session_year) {
                        $query->where('session_year_id', $session_year);
                    }
                    $query->with('subject');
                }]);
//                $query->with('studentReportCards.subject');
            }])
            ->first();

        $stdClass = new stdClass();
        $stdClass->reports = [];
        $stdClass->published = $published;

        $response = array(
            'error' => false,
            'message' => 'Student report fetched Successfully.',
            'data' => $stdClass,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function studentTeachers(Request $request){
        if ($request->student_id && !Access::canViewStudent($request->user(), $request->student_id)) {
            return Access::forbidden();
        }
        $user = $request->user();
        if ($user && $user->type != 'parent') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
        $request->validate([
            'student_id' => 'required'
        ]);

        $student_id = $request->student_id;

        $classSections = Students::where('user_id',$student_id)->pluck('class_section_id')->toArray();
        $teachers = User::where('type','teacher')
            ->whereHas('teacher',function ($query) use ($classSections){
                $query->whereHas('class_section',function ($query) use ($classSections){
                    $query->whereIn('id',$classSections);
                });
            })
            ->with('teacher.class_section.class')
            ->orderBy('first_name','ASC')
            ->get();

        $response = array(
            'error' => false,
            'message' => 'Student\'s teachers fetched Successfully.',
            'data' => $teachers,
            'code' => 200,
        );
        return response()->json($response, 200);
    }
    public function studentParents(Request $request){
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
        $request->validate([
            'student_id' => 'required'
        ]);

        $student_id = $request->student_id;

        $mothers = Students::where('user_id',$student_id)->pluck('mother_id')->toArray();
        $fathers = Students::where('user_id',$student_id)->pluck('father_id')->toArray();

        $parents = User::where('type','parent')
            ->where(function ($query) use ($mothers,$fathers){
                $query->whereIn('id',$mothers);
                $query->orWhere('id',$fathers);
            })
            ->orderBy('first_name','ASC')
            ->get();

        $response = array(
            'error' => false,
            'message' => 'Student\'s parents fetched Successfully.',
            'data' => $parents,
            'code' => 200,
        );
        return response()->json($response, 200);
    }
    public function studentAttendance(Request $request){

        $request->validate([
            'student_id' => 'required'
        ]);
        $student_id = $request->student_id;
        if (!Access::canViewStudent($request->user(), $student_id)) {
            return Access::forbidden();
        }
        $subject_id = $request->subject_id;
        $year = $request->year;
        $month = $request->month;

        if($subject_id){
            $attendance = Attendance::where('student_id',$student_id)
                ->where('subject_id',$subject_id)
                ->orderBy('date','DESC')
                ->whereYear('date', '=', $year)
                ->whereMonth('date', '=', $month)
                ->with('subject')
                ->paginate(10);
        }else{
            $attendance = Attendance::where('student_id',$student_id)
                ->orderBy('date','DESC')
                ->with('subject')
                ->whereYear('date', '=', $year)
                ->whereMonth('date', '=', $month)
                ->paginate(10);
        }

        $response = array(
            'error' => false,
            'message' => 'Student\'s teachers fetched Successfully.',
            'data' => $attendance,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function classSectionStudentsRequestsCancel(Request $request){
        $user = $request->user();
        if ($user && $user->type != 'parent') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
        $student_id = $request->user_id;
        $user_id = $user->id;

        $student = Students::where('class_status','PENDING')
            ->where('user_id',$student_id)
            ->where(function ($query) use ($user_id){
                $query->where('mother_id',$user_id);
                $query->orWhere('father_id',$user_id);
            })
            ->first();
        if($student){
            $student->class_status = null;
            $student->class_section_id = null;
            $student->save();
            $response = array(
                'error' => false,
                'message' => "Successfully updated",
                'data' => null,
                'code' => 200,
            );
            return response()->json($response);

        }
        $response = array(
            'error' => true,
            'message' => "Failed to update user status",
            'data' => null,
            'code' => 400,
        );
        return response()->json($response,400);
    }
}