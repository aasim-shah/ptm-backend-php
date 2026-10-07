<?php

namespace App\Http\Controllers\Api;


use App\Helpers\Utility;
use App\Models\Attendance;
use App\Models\CallDetails;
use App\Models\ClassQuarter;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Country;
use App\Models\MainMeeting;
use App\Models\Mediums;
use App\Models\Meetings;
use App\Models\Parents;
use App\Models\PasswordReset;
use App\Models\Schools;
use App\Models\SchoolSubjects;
use App\Models\Section;
use App\Models\Settings;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserSchools;
use App\Models\UserSubscription;
use App\Services\NotificationSender;
use Carbon\Carbon;
use App\Support\Access;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Holiday;
use App\Models\SessionYear;
use App\Models\Slider;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class ApiController extends Controller
{

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }


    public function getMeeting(Request $request)
    {


        try {

            $validateUser = Validator::make($request->all(),
                [
                    'status' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();
            $meetings = Meetings::where('user_id', $user->id)->with('teacher', 'parent')->where('status', $request->status)->orderBy('updated_at', 'DESC')->get();

            return response()->json([
                'status' => 200,
                'message' => 'Meetings Feched Successfully',
                'data' => $meetings
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function searchMeetings(Request $request)
    {


        try {

            $validateUser = Validator::make($request->all(),
                [
                    'search_title' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();
            $meetings = Meetings::where(function ($query) use ($user) {
                $query->where('parent_id', $user->id);
                $query->orWhere('teacher_id', $user->id);
            })->where('title', 'LIKE', '%' . $request->search_title . '%')->with('teacher', 'parent')->orderBy('updated_at', 'DESC')->get();

            return response()->json([
                'status' => 200,
                'message' => 'Meetings Fetched Successfully',
                'data' => $meetings
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function searchjParentTeacher(Request $request)
    {


        try {

            $validateUser = Validator::make($request->all(),
                [
                    'search_title' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();

            $users = User::where('first_name', 'LIKE', '%' . $request->search_title . '%')->where('type', $request->type)->orderBy('updated_at', 'DESC')->get();

            return response()->json([
                'status' => 200,
                'message' => 'Record Feched Successfully',
                'data' => $users
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function verifyPincode(Request $request)
    {


        try {

            $validateUser = Validator::make($request->all(),
                [
                    'pincode' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }
            $user = $request->user();
            if ($user->pincode == $request['pincode']) {

                $user->pincode = null;
                $user->email_verified_at = Carbon::now();
                $user->update();

                return response()->json([
                    'status' => 200,
                    'message' => 'Pincode verified Successfully',
                    'data' => $user
                ], 200);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid Pincode',
                    'data' => []
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function sendPincode(Request $request)
    {


        try {

            $user = $request->user();

            $pincode = Utility::getOtp();
            $user->pincode = $pincode;
            $user->update();
            $data['email'] = $user->email;
            $data['subject'] = 'Verifications by Parent Teacher Mobile';
            $data['pincode'] = $pincode;
            $data['view'] = view('emails.pincode', compact('pincode'))->render();

            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("fanfeedback@parentteachermobile.com", "parentteachermobile.com");
            $email->setSubject($data['subject']);
            $email->addTo($data['email'], "Parent Teacher Mobile");
            $email->addContent("text/html", $data['view']);
            $sendgrid = new \SendGrid(config('environment.SENDGRID_API_KEY') ?: config('environment.MAIL_PASSWORD'));
            try {
                $sendgrid->send($email);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error($e);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Pincode Sent Successfully',
                'data' => []
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }

    }

    public function forgotPassword1(Request $request)
    {

        try {

            $validateUser = Validator::make($request->all(),
                [
                    'email' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $pincode = Utility::getOtp();

            $user = User::where('email', $request->email)->first();
            if ($user) {
                $user->pincode = $pincode;
                $user->update();

                $data['email'] = $user->email;
                $data['subject'] = 'Verifications by Parent Teacher Mobile';
                $data['pincode'] = $pincode;
                $data['view'] = view('emails.pincode', compact('pincode'))->render();

                $email = new \SendGrid\Mail\Mail();
                $email->setFrom("fanfeedback@parentteachermobile.com", "parentteachermobile.com");
                $email->setSubject($data['subject']);
                $email->addTo($data['email'], "Parent Teacher Mobile");
                $email->addContent("text/html", $data['view']);
                $sendgrid = new \SendGrid(config('environment.SENDGRID_API_KEY') ?: config('environment.MAIL_PASSWORD'));
                try {
                    $sendgrid->send($email);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 400,
                        'message' => $e->getMessage(),
                        'data' => []
                    ], 200);
                }

                return response()->json([
                    'status' => 200,
                    'message' => 'Pincode Sent Successfully',
                    'data' => []
                ], 200);

            } else {
                return response()->json([
                    'status' => 400,
                    'message' => 'No User found..',
                    'data' => []
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }

    }

    public function resetpassword(Request $request)
    {


        try {

            $validateUser = Validator::make($request->all(),
                [
                    'password' => 'required|string|min:8|confirmed',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();
            $user->password = Hash::make($request['password']);
            $user->update();

            return response()->json([
                'status' => 200,
                'message' => 'Password Reset Successfully',
                'data' => []
            ], 200);


        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }

    }


    public function getMeetings(Request $request)
    {

        $status = $request->get('status');
        $type = $request->get('type') ?? null;
        $date = $request->get('date');

        try {
            $user = $request->user();
            $user_id = $user->id;

            $meetings = Meetings::select('id', 'title', 'status', 'user_id', 'meeting_created_user_type', 'teacher_id', 'parent_id', 'meeting_date', 'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash')->where(function ($query) use ($user, $user_id) {
                $query->where('parent_id', $user_id);
                $query->orWhere('teacher_id', $user_id);
            })
                ->with(['teacher' => function ($query) {
                    $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
                }])
                ->with(['principal' => function ($query) {
                    $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
                }])
                ->where(function ($query) use ($status, $date, $type, $user) {
                    if ($status) {
                        $query->where('status', $status);
                    }
                    if ($date) {
                        $query->where('meeting_date', $date);
                    }
                    if ($type == 'received') {
                        if ($user->type == 'teacher') {
                            $query->where('meeting_created_user_type', '!=', 'teacher');
                        }
                        if ($user->type == 'parent') {
                            $query->where('meeting_created_user_type', '!=', 'parent');
                        }
                    }
                })
                ->orderBy('id', 'DESC')
                ->groupBy('meeting_hash');

//            $meetingsStudents = Meetings::where(function ($query) use ($user,$meetings) {
//                $query->whereIn('id', $meetings->pluck('id')->toArray());
//            })->where(function ($query) use ($status) {
//                if ($status) {
//                    $query->where('status', $status);
//                }
//            })->with('student.student.class_section.class')->groupBy('student_id')->get();

            $meetingsParents = Meetings::where(function ($query) use ($user, $meetings) {
                $query->whereIn('meeting_hash', $meetings->pluck('meeting_hash')->toArray());
            })->where(function ($query) use ($status) {
                if ($status) {
                    $query->where('status', $status);
                }
            })->with(['parent' => function ($query) {
                $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
            }])->groupBy('student_id')->pluck('parent_id','meeting_hash');

            $meetings = $meetings->paginate(10);

            foreach ($meetings as $meeting) {
                $singleMeeting = Meetings::where('meeting_hash',$meeting->meeting_hash)->pluck('parent_id');
                $meeting->_parents = User::where('type','parent')->whereIn('id',$singleMeeting)->get();
            }

            return response()->json([
                'status' => 200,
                'message' => 'Meetings fetched successfully',
                'data' => $meetings
            ], 200);

        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }

    }
    public function getMeetingsV2(Request $request)
    {

        $status = $request->get('status');
        $type = $request->get('type') ?? null;
        $date = $request->get('date');

        $user = Auth::user();
        $user_id = $user->id;
        $user_type = $user->type;
        $meetings = MainMeeting::whereHas('meeting_details',function ($query) use ($user_id,$type,$date,$status,$user,$user_type){
            $query->select('id', 'title', 'status', 'user_id', 'meeting_created_user_type', 'teacher_id', 'parent_id', 'meeting_date',
                'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash');
            $query->where(function ($query) use ($user_id){
                $query->where('parent_id', $user_id);
                $query->orWhere('teacher_id', $user_id);
            });
            $query->where(function ($query) use ($status, $date, $type, $user,$user_type) {
                if ($status) {
                    $query->where('status', $status);
                }
                if ($date) {
                    $query->where('meeting_date', $date);
                }
                if ($type == 'received') {
                    if ($user_type == 'teacher') {
                        $query->where('meeting_created_user_type', '!=', 'teacher');
                    }
                    if ($user_type == 'parent') {
                        $query->where('meeting_created_user_type', '!=', 'parent');
                    }
                }
            });
        })->with('user','meeting_details.parent','meeting_details.teacher','meeting_details.principal')
            ->orderBy('meeting_date','DESC')
            ->orderBy('meeting_time','ASC')
            ->paginate(10);

        return response()->json([
            'status' => 200,
            'message' => 'Meetings fetched successfully',
            'data' => $meetings
        ], 200);
    }

    public function getTeachers(Request $request)
    {


        try {

            $user = $request->user();
            $teachers = User::where('type', 'teacher')->get();

            return response()->json([
                'status' => 200,
                'message' => 'Teachers Fetched Successfully',
                'data' => $teachers
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function getParents(Request $request)
    {


        try {

            $user = $request->user();
            $teachers = User::where('type', 'parent')->get();

            return response()->json([
                'status' => 200,
                'message' => 'Teachers Fetched Successfully',
                'data' => $teachers
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function updateMeeting(Request $request)
    {

        try {

            $validateUser = Validator::make($request->all(),
                [
                    'meeting_id' => 'required',
                    'status' => 'required',
                ]);

            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();
            if (empty($request->parent_id)) {
                $request->parent_id = $user->id;
            }
            if (empty($request->teacher_id)) {
                $request->teacher_id = $user->id;
            }

            $meeting = Meetings::where('id',$request->meeting_id)->first();
            if ($meeting->user_id == $user->id) {
                return response()->json([
                    'status' => 400,
                    'message' => trans('no_permission_message')
                ], 400);
            }

            $meeting->status = strtolower($request->status);
            $meeting->update();

            $participantCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
            $participantAcceptedCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->where('status','accepted')->count();
            $participantRejectedCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->where('status','rejected')->count();

            $mainMeeting = MainMeeting::where('meeting_hash',$meeting->meeting_hash)->first();
            if($mainMeeting){
                if($participantCount == $participantAcceptedCount){
                    $mainMeeting->status = 'accepted';
                }
                if($participantCount == $participantRejectedCount){
                    $mainMeeting->status = 'rejected';
                }
                $mainMeeting->save();
            }
            if($request->status == 'accepted'){
                if($meeting->meeting_created_user_type == 'teacher'){
                    $message = "A new meeting is scheduled for ".$meeting->meeting_date . " " . $meeting->meeting_time." has been accepted by parent";
                     $this->notificationSender->sendNotification(
                        "Meeting request Accepted",
                        $message,
                        [$meeting->teacher_id], "MEETING", $meeting);
                     $this->notificationSender->saveLogs($meeting->teacher_id,$user->id,$message,'MEETING','Meeting request Accepted');
                }
                if($meeting->meeting_created_user_type == 'parent'){
                    $message = "A new meeting is scheduled for ".$meeting->meeting_date . " " . $meeting->meeting_time." has been accepted by teacher";
                    $this->notificationSender->sendNotification(
                    "Meeting request Accepted",
                    $message,
                    [$meeting->parent_id], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($meeting->parent_id,$user->id,$message,'MEETING','Meeting request Accepted');

                }
            }
            if($request->status == 'rejected'){
                if($meeting->meeting_created_user_type == 'teacher'){
                    $message = "A new meeting is scheduled for ".$meeting->meeting_date . " " . $meeting->meeting_time." has been rejected by parent";
                    $this->notificationSender->sendNotification(
                    "Meeting request Rejected",
                    $message,
                    [$meeting->teacher_id], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($meeting->teacher_id,$user->id,$message,'MEETING','Meeting request Rejected');

                }
                if($meeting->meeting_created_user_type == 'parent'){
                    $message = "A new meeting is scheduled for ".$meeting->meeting_date . " " . $meeting->meeting_time." has been rejected by teacher";
                    $this->notificationSender->sendNotification(
                    "Meeting request Rejected",
                    $message,
                    [$meeting->parent_id], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($meeting->parent_id,$user->id,$message,'MEETING','Meeting request Rejected');
                }
            }
            return response()->json([
                'status' => 200,
                'message' => 'Meeting Updated Successfully',
                'data' => $meeting
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function postMeeting(Request $request)
    {
        Log::info($request);
        try {

            $meetingTime = str_replace(' ', ' ', $request->meeting_time);
            $meetingEndTime = str_replace(' ', ' ', $request->meeting_end_time);
            if(strtotime($meetingTime) >= strtotime($meetingEndTime)){
                throw new BadRequestException("The meeting time must be a date before meeting end time",400);
            }

            $validateUser = Validator::make($request->all(),
                [
                    'title' => 'required',
                    'student_parent_ids' => 'nullable',
                    'meeting_date' => 'required|after:yesterday',
                    'meeting_time' => 'required',
                    'meeting_end_time' => 'required'
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();
            $meeting_created_user_type = "teacher";
            if ($user->type == 'parent') {
                $meeting_created_user_type = 'parent';
            }
            if ($user->type == 'Principal') {
                $meeting_created_user_type = 'Principal';
            }

            $meeting_hash = Utility::getUUID();
            $principal_id = $request->is_principal;
            if ($request->is_principal) {
                $userSchool = UserSchools::where('user_id', $user->id)->first();
                $school = Schools::where('id', $userSchool->id)->first();
                if (!$school->principal_id) {
                    return response()->json([
                        'status' => 400,
                        'message' => "There is no principal associated with your school",
                    ], 400);
                }
                $principal_id = $school->principal_id;
            }

            if ($request->student_parent_ids && $user->type != 'teacher') {
                $response = array(
                    'error' => true,
                    'message' => trans('no_permission_message'),
                    'data' => null,
                    'code' => 400,
                );
                return response()->json($response);
            }
            if ($request->teacher_id && $user->type != 'parent') {
                $response = array(
                    'error' => true,
                    'message' => trans('no_permission_message'),
                    'data' => null,
                    'code' => 400,
                );
                return response()->json($response);
            }
            $student_parent_ids = $request->student_parent_ids;
            if (sizeof($student_parent_ids) == 0) {
                $student_parent_ids[0]['parent_id'] = $user->id;
                $student_parent_ids[0]['student_id'] = null;
            }

            if (empty($request->teacher_id)) {
                $request->teacher_id = $user->id;
            }

            $meeting = array();
            if (sizeof($student_parent_ids) > 0) {
                foreach ($student_parent_ids as $studentParent) {
                    $meeting [] = [
                        'title' => $request->title,
                        'user_id' => $user->id,
                        'student_id' => $studentParent['student_id'],
                        'parent_id' => $studentParent['parent_id'],
                        'teacher_id' => $request->teacher_id,
                        'principal_id' => $principal_id,
                        'meeting_created_user_type' => $meeting_created_user_type,
                        'meeting_hash' => $meeting_hash,
                        'meeting_date' => $request->meeting_date,
                        'meeting_time' => $meetingTime,
                        'meeting_end_time' => $meetingEndTime,
                        'description' => $request->description,
                        'status' => 'new',
                    ];


                    if ($studentParent['parent_id']) {
                        $isParent = Parents::where('user_id', $studentParent['parent_id'])->first();
                        if (!$isParent) {
                            return response()->json([
                                'status' => 400,
                                'message' => "Invalid parent detected.",
                                "data" => [
                                    "parent_id" => $studentParent['parent_id']
                                ]
                            ], 400);
                        }
                    }
                    if ($request->teacher_id) {
                        $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();
                        if (!$isTeacher) {
                            return response()->json([
                                'status' => 400,
                                'message' => "Invalid teacher detected.",
                                "data" => [
                                    "teacher_id" => $request->teacher_id
                                ]
                            ], 400);
                        }
                    }
                }
                $notiIds = [];
                if ($meeting_created_user_type == 'teacher') {
                    $notiIds = array_column($meeting, 'parent_id');
                }
                if ($meeting_created_user_type == 'parent') {
                    $notiIds = array_column($meeting, 'teacher_id');
                }
                if (sizeof($notiIds) > 0) {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        $notiIds, "MEETING", $meeting[0]);
                    $notificationLogArray = array();
                    foreach ($notiIds as $notiId) {
                        $notificationLogArray [] = [
                            "id" => Utility::getUUID(),
                            "user_id" => $notiId,
                            "from_user_id" => $user->id,
                            "message" => $message,
                            "type" => "MEETING",
                            "title" => "New meeting scheduled",
                            'created_at' => Carbon::now()
                        ];
                    }
                    $this->notificationSender->insertLogs($notificationLogArray);
                }

                Meetings::insert($meeting);
            } else {
                if ($student_parent_ids[0]['parent_id']) {
                    $isParent = Parents::where('user_id', $student_parent_ids[0]['parent_id'])->first();

                    if (!$isParent) {
                        return response()->json([
                            'status' => 400,
                            'message' => "Invalid parent detected.",
                            "data" => [
                                "parent_id" => $student_parent_ids[0]['parent_id']
                            ]
                        ], 400);
                    }
                }
                if ($request->teacher_id) {
                    $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();
                    if (!$isTeacher) {
                        return response()->json([
                            'status' => 400,
                            'message' => "Invalid teacher detected.",
                            "data" => [
                                "teacher_id" => $request->teacher_id
                            ]
                        ], 400);
                    }
                }
                $meeting = Meetings::create([
                    'title' => $request->title,
                    'user_id' => $user->id,
                    'student_id' => $student_parent_ids[0]['student_id'],
                    'parent_id' => $student_parent_ids[0]['parent_id'],
                    'teacher_id' => $request->teacher_id,
                    'meeting_hash' => $meeting_hash,
                    'principal_id' => $principal_id,
                    'meeting_created_user_type' => $meeting_created_user_type,
                    'meeting_date' => $request->meeting_date,
                    'meeting_time' => $meetingTime,
                    'meeting_end_time' => $meetingEndTime,
                    'description' => $request->description,
                    'status' => 'new',
                ]);


                if ($meeting_created_user_type == 'teacher') {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        [$student_parent_ids[0]['parent_id']], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($student_parent_ids[0]['parent_id'],$user->id,$message,'MEETING','New meeting scheduled');

                }
                if ($meeting_created_user_type == 'parent') {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        [$request->teacher_id], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($request->teacher_id,$user->id,$message,'MEETING','New meeting scheduled');

                }

            }

            return response()->json([
                'status' => 200,
                'message' => 'Meeting Created Successfully',
                'data' => []
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }
    public function postMeetingV2(Request $request)
    {
        Log::info($request);

       $user = $request->user();
       $isActive = UserSubscription::where('user_id',$user->id)
            ->where('status',"ACTIVE")->where('remaining_time','!=','0')->first();
       if(!$isActive){
           $response = [
               'error' => true,
               'message' => "No Active Subscription",
               'data' => UserSubscription::where('user_id',$user->id)
                   ->where('status',"ACTIVE")->first()
           ];
           return response()->json($response,400);
       }

        try {

            $meetingTime = str_replace(' ', ' ', $request->meeting_time);
            $meetingEndTime = str_replace(' ', ' ', $request->meeting_end_time);
            if(strtotime($meetingTime) >= strtotime($meetingEndTime)){
                throw new BadRequestException("The meeting time must be a date before meeting end time",400);
            }

            $validateUser = Validator::make($request->all(),
                [
                    'title' => 'required',
                    'student_parent_ids' => 'nullable',
                    'meeting_date' => 'required|after:yesterday',
                    'meeting_time' => 'required',
                    'meeting_end_time' => 'required'
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $meeting_created_user_type = "teacher";
            if ($user->type == 'parent') {
                $meeting_created_user_type = 'parent';
            }
            if ($user->type == 'Principal') {
                $meeting_created_user_type = 'Principal';
            }

            $meeting_hash = Utility::getUUID();
            $principal_id = $request->is_principal;
            if ($request->is_principal) {
                $userSchool = UserSchools::where('user_id', $user->id)->first();
                $school = Schools::where('id', $userSchool->id)->first();
                if (!$school->principal_id) {
                    return response()->json([
                        'status' => 400,
                        'message' => "There is no principal associated with your school",
                    ], 400);
                }
                $principal_id = $school->principal_id;
            }

            if ($request->student_parent_ids && $user->type != 'teacher') {
                $response = array(
                    'error' => true,
                    'message' => trans('no_permission_message'),
                    'data' => null,
                    'code' => 400,
                );
                return response()->json($response,400);
            }
            if ($request->teacher_id && $user->type != 'parent') {
                $response = array(
                    'error' => true,
                    'message' => trans('no_permission_message'),
                    'data' => null,
                    'code' => 400,
                );
                return response()->json($response,400);
            }
            $student_parent_ids = $request->student_parent_ids;
            if (sizeof($student_parent_ids) == 0) {
                $student_parent_ids[0]['parent_id'] = $user->id;
                $student_parent_ids[0]['student_id'] = null;
            }

            if (empty($request->teacher_id)) {
                $request->teacher_id = $user->id;
            }

            if($user->type == 'teacher' && $isActive->call_count <= sizeof($student_parent_ids)){
                $response = array(
                    'error' => true,
                    'message' => "Participant count exceeded",
                    'data' => sizeof($student_parent_ids),
                    'code' => 400,
                );
                return response()->json($response,400);
            }

            $mainMeeting = new MainMeeting();
            $mainMeeting->id = Utility::getUUID();
            $mainMeeting->user_id = $user->id;
            $mainMeeting->title = $request->title;
            $mainMeeting->description = $request->description;
            $mainMeeting->meeting_date = $request->meeting_date;
            $mainMeeting->meeting_time = $meetingTime;
            $mainMeeting->meeting_end_time = $meetingEndTime;
            $mainMeeting->meeting_hash = $meeting_hash;
            $mainMeeting->save();

            $meeting = array();
            if (sizeof($student_parent_ids) > 0) {
                foreach ($student_parent_ids as $studentParent) {
                    $meeting [] = [
                        'title' => $request->title,
                        'user_id' => $user->id,
                        'student_id' => $studentParent['student_id'] ?? $request->student_id,
                        'parent_id' => $studentParent['parent_id'],
                        'teacher_id' => $request->teacher_id,
                        'principal_id' => $principal_id,
                        'meeting_created_user_type' => $meeting_created_user_type,
                        'meeting_hash' => $meeting_hash,
                        'meeting_date' => $request->meeting_date,
                        'meeting_time' => $meetingTime,
                        'meeting_end_time' => $meetingEndTime,
                        'description' => $request->description,
                        'status' => 'new',
                    ];


                    if ($studentParent['parent_id']) {
                        $isParent = Parents::where('user_id', $studentParent['parent_id'])->first();
                        if (!$isParent) {
                            return response()->json([
                                'status' => 400,
                                'message' => "Invalid parent detected.",
                                "data" => [
                                    "parent_id" => $studentParent['parent_id']
                                ]
                            ], 400);
                        }
                    }
                    if ($request->teacher_id) {
                        $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();
                        if (!$isTeacher) {
                            return response()->json([
                                'status' => 400,
                                'message' => "Invalid teacher detected.",
                                "data" => [
                                    "teacher_id" => $request->teacher_id
                                ]
                            ], 400);
                        }
                    }
                }
                $notiIds = [];
                if ($meeting_created_user_type == 'teacher') {
                    $notiIds = array_column($meeting, 'parent_id');
                }
                if ($meeting_created_user_type == 'parent') {
                    $notiIds = array_column($meeting, 'teacher_id');
                }
                if (sizeof($notiIds) > 0) {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        $notiIds, "MEETING", $meeting[0]);
                    $notificationLogArray = array();
                    foreach ($notiIds as $notiId) {
                        $notificationLogArray [] = [
                            "id" => Utility::getUUID(),
                            "user_id" => $notiId,
                            "from_user_id" => $user->id,
                            "message" => $message,
                            "type" => "MEETING",
                            "title" => "New meeting scheduled",
                            'created_at' => Carbon::now()
                        ];
                    }
                    $this->notificationSender->insertLogs($notificationLogArray);
                }

                Meetings::insert($meeting);
            } else {
                if ($student_parent_ids[0]['parent_id']) {
                    $isParent = Parents::where('user_id', $student_parent_ids[0]['parent_id'])->first();

                    if (!$isParent) {
                        return response()->json([
                            'status' => 400,
                            'message' => "Invalid parent detected.",
                            "data" => [
                                "parent_id" => $student_parent_ids[0]['parent_id']
                            ]
                        ], 400);
                    }
                }
                if ($request->teacher_id) {
                    $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();
                    if (!$isTeacher) {
                        return response()->json([
                            'status' => 400,
                            'message' => "Invalid teacher detected.",
                            "data" => [
                                "teacher_id" => $request->teacher_id
                            ]
                        ], 400);
                    }
                }
                $meeting = Meetings::create([
                    'title' => $request->title,
                    'user_id' => $user->id,
                    'student_id' => $student_parent_ids[0]['student_id'] ?? $request->student_id,
                    'parent_id' => $student_parent_ids[0]['parent_id'],
                    'teacher_id' => $request->teacher_id,
                    'meeting_hash' => $meeting_hash,
                    'principal_id' => $principal_id,
                    'meeting_created_user_type' => $meeting_created_user_type,
                    'meeting_date' => $request->meeting_date,
                    'meeting_time' => $meetingTime,
                    'meeting_end_time' => $meetingEndTime,
                    'description' => $request->description,
                    'status' => 'new',
                ]);


                if ($meeting_created_user_type == 'teacher') {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        [$student_parent_ids[0]['parent_id']], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($student_parent_ids[0]['parent_id'],$user->id,$message,'MEETING','New meeting scheduled');

                }
                if ($meeting_created_user_type == 'parent') {
                    $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                    $this->notificationSender->sendNotification(
                        "New meeting scheduled",
                        $message,
                        [$request->teacher_id], "MEETING", $meeting);
                    $this->notificationSender->saveLogs($request->teacher_id,$user->id,$message,'MEETING','New meeting scheduled');

                }

            }

            return response()->json([
                'status' => 200,
                'message' => 'Meeting Created Successfully',
                'data' => []
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function getSchool(Request $request)
    {
        try {
            $user = $request->user();
            $school_id = $request->get('school_id');
            if ($school_id) {
                $school = UserSchools::where('user_id', $user->id)->pluck('school_id')->toArray();
                $assignedSchool = Schools::whereIn('id', $school)->where('id', $school_id)->get();

                return response()->json([
                    'status' => 200,
                    'message' => 'School fetched Successfully',
                    'school' => $assignedSchool,
                ], 200);

            } else {

                $school = UserSchools::where('user_id', $user->id)->pluck('school_id')->toArray();
                $assignedSchool = Schools::whereIn('id', $school)->get();
                return response()->json([
                    'status' => 200,
                    'message' => 'School fetched Successfully',
                    'school' => $assignedSchool,
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }


    public function postSchool(Request $request)
    {
        $user = $request->user();

        try {
            //Validated
            $validateUser = Validator::make($request->all(),
                [
                    'country_id' => 'required',
                    'school_name' => 'required',
                    'phone' => 'nullable',
                    'email' => 'nullable|email|unique:schools,email',
                    'post_code' => 'required'
                ]);

            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $isSchool = Schools::where('school_name', 'like', '%' . $request->school_name . '%')->first();
            if ($isSchool) {
                return response()->json([
                    'status' => 400,
                    'message' => "This school is already registered.",
                ], 400);
            }
            $image = '';
            if (!empty($request->image)) {
                $image = $request->image;
                $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                $file_path = 'schools/' . $file_name;
                $destinationPath = storage_path('app/public/schools');
                $image->move($destinationPath, $file_name);

                $image = $file_path;
            }
            $school = Schools::create([
                'country_id' => $request->country_id,
                'user_id' => $user->id,
                'school_name' => $request->school_name,
                'address' => $request->address,
                'locality' => $request->locality,
                'post_town' => $request->post_town,
                'post_code' => $request->post_code,
                'email' => $request->email,
                'phone' => $request->phone,
                'website' => $request->website,
                'image' => $image,
            ]);

            foreach (Section::default_sections as $default_section){
                $section = new Section();
                $section->name = $default_section;
                $section->school_id = $school->id;
                $section->save();
            }


            return response()->json([
                'status' => 200,
                'message' => 'School Created Successfully',
                'data' => $school
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }


    public function getCountries(Request $request)
    {


        try {
            $countries = Country::orderBy('id', 'DESC')->get();
            if (!empty($countries)) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Countries fetched successfully.',
                    'data' => $countries
                ], 200);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => 'Countries Not Found',
                    'data' => []
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }


    public function getSchools(Request $request)
    {
        $name = $request->get('name');
        $user = $request->user();
        try {

            $schools = Schools::where(function ($query) use ($name,$user) {
                if ($name) {
                    $query->where('school_name', 'LIKE', "%{$name}%");
                }
                if ($user->country_id) {
                    $query->where('country_id',$user->country_id);
                }
            })->orderBy('school_name', 'ASC')->with('country')->paginate(10);
            return response()->json([
                'status' => 200,
                'message' => 'Schools fetched successfully.',
                'data' => $schools
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->fcm_id = '';
            $user->save();
            $user->currentAccessToken()->delete();
            $response = array(
                'status' => false,
                'message' => 'Logout Successfully done.',
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'status' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }

    public function getHolidays(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'assignment_id' => 'nullable|numeric',
        //     'subject_id' => 'nullable|numeric',
        // ]);

        // if ($validator->fails()) {
        //     $response = array(
        //         'error' => true,
        //         'message' => $validator->errors()->first(),
        //     );
        //     return response()->json($response);
        // }

        try {
            $data = Holiday::get();
            $response = array(
                'error' => false,
                'message' => "Holidays Fetched Successfully",
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

    public function getSliders(Request $request)
    {
        try {
            $data = Slider::get();
            $response = array(
                'error' => false,
                'message' => "Sliders Fetched Successfully",
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

    public function getSessionYear(Request $request)
    {
        try {
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            $data = SessionYear::orderBy('start_date', 'DESC')->get();
            $response = array(
                'error' => false,
                'message' => "Session Year Fetched Successfully",
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

    public function getSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:privacy_policy,contact_us,about_us,terms_condition,app_settings,fees_settings',
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
            $settings = getSettings();
            if ($request->type == "app_settings") {
                $session_year = $settings['session_year'] ?? "";
                $calender = !empty($session_year) ? SessionYear::find($session_year) : null;

                $data['app_link'] = $settings['app_link'] ?? "";
                $data['ios_app_link'] = $settings['ios_app_link'] ?? "";
                $data['app_version'] = $settings['app_version'] ?? "";
                $data['ios_app_version'] = $settings['ios_app_version'] ?? "";
                $data['force_app_update'] = $settings['force_app_update'] ?? "";
                $data['app_maintenance'] = $settings['app_maintenance'] ?? "";
                $data['session_year'] = $calender;
                $data['school_name'] = $settings['school_name'] ?? "";
                $data['school_tagline'] = $settings['school_tagline'] ?? "";
                $data['teacher_app_link'] = $settings['teacher_app_link'] ?? "";
                $data['teacher_ios_app_link'] = $settings['teacher_ios_app_link'] ?? "";
                $data['teacher_app_version'] = $settings['teacher_app_version'] ?? "";
                $data['teacher_ios_app_version'] = $settings['teacher_ios_app_version'] ?? "";
                $data['teacher_force_app_update'] = $settings['teacher_force_app_update'] ?? "";
                $data['teacher_app_maintenance'] = $settings['teacher_app_maintenance'] ?? "";
                $data['online_payment'] = $settings['online_payment'] ?? "1";

                if (isset($settings['razorpay_status']) && $settings['razorpay_status']) {
                    if (isset($settings['fees_due_date'])) {
                        $date = date('Y-m-d', strtotime($settings['fees_due_date']));
                    }
                    $data['fees_settings'] = array(
                        'razorpay_status' => $settings['razorpay_status'] ?? "",
                        'razorpay_secret_key' => $settings['razorpay_secret_key'] ?? "",
                        'razorpay_api_key' => $settings['razorpay_api_key'] ?? "",
                        'razorpay_webhook_secret' => $settings['razorpay_webhook_secret'] ?? "",
                        'razorpay_webhook_url' => $settings['razorpay_webhook_url'] ?? "",
                        'razorpay_api_key' => $settings['razorpay_api_key'] ?? "",
                        'currency_code' => $settings['currency_code'] ?? "",
                        'currency_symbol' => $settings['currency_symbol'] ?? "",
                    );
                }

                if (isset($settings['stripe_status']) && $settings['stripe_status']) {
                    if (isset($settings['fees_due_date'])) {
                        $date = date('Y-m-d', strtotime($settings['fees_due_date']));
                    }
                    $data['fees_settings'] = array(
                        'stripe_status' => $settings['stripe_status'] ?? "",
                        'stripe_publishable_key' => $settings['stripe_publishable_key'] ?? "",
                        'stripe_secret_key' => $settings['stripe_secret_key'] ?? "",
                        'stripe_webhook_secret' => $settings['stripe_webhook_secret'] ?? "",
                        'stripe_webhook_url' => $settings['stripe_webhook_url'] ?? "",
                        'currency_code' => $settings['currency_code'] ?? "",
                        'currency_symbol' => $settings['currency_symbol'] ?? "",
                        'fees_due_date' => $date ?? "",
                        'fees_due_charges' => $settings['fees_due_charges'] ?? "",
                    );
                }
                if (isset($settings['online_exam_terms_condition']) && !empty($settings['online_exam_terms_condition'])) {
                    $data['online_exam_terms_condition'] = htmlspecialchars_decode($settings['online_exam_terms_condition']);
                } else {
                    $data['online_exam_terms_condition'] = "";
                }
            } else {
                $data = $settings[$request->type] ?? "";
            }
            $response = array(
                'error' => false,
                'message' => "Data Fetched Successfully",
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

    protected function forgotPassword(Request $request)
    {
        $input = $request->only('email');
        $validator = Validator::make($input, [
            'email' => "required|email"
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response);
        }
        if (User::where('email', $request->email)->exists()) {
            $user = User::where('email', $request->email)->first();
            try {
                $otp = Utility::getOtp();
                $record = User::where('email', '=', $user->email)->update(['pincode' => $otp]);
                if ($record) {
                    $details = [
                        'otp' => $otp
                    ];
                    $view = view('emails.pincode', compact('details'))->render();
                    Utility::sendEmail($view,'Email Verification OTP',$request->email);
                    return response(["status" => 200, "message" => "OTP sent successfully"]);
                } else {
                    return response(["status" => 400, 'message' => 'Invalid details provided'],400);
                }
            } catch (\Exception $e) {
                $response = array(
                    'error' => true,
                    'message' => trans('error_occurred'),
                    'code' => 400,
                );
                Log::error($e->getMessage(), ['exception' => $e]);
                return response($response,'400');

            }
        } else {
            return response(["status" => 400, 'message' => 'Invalid'],400);
        }
    }

    protected function verifyOtp(Request $request)
    {

        $user = User::where([['email', '=', $request->email], ['pincode', '=', $request->otp]])->first();
        if ($user) {
            $user->pincode = null;
            $user->email_verified_at = Carbon::now();
            $user->save();
            return response(["status" => 200, "message" => "Success"]);
        } else {
            return response(["status" => 400, 'message' => 'Invalid details provided'],400);
        }
    }

    protected function changePasswordAfterOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'new_password' => 'required|between:6,12',
            'new_confirm_password' => 'same:new_password',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 400,
            );
            return response()->json($response,400);
        }
        try {
            $user = User::where('email', $request->email)->first();
            $user->update(['password' => Hash::make($request->new_password)]);
            $response = array(
                'error' => false,
                'message' => "Password Changed successfully.",
                'code' => 200,
            );
            return response()->json($response);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 400,
            );
            return response()->json($response,400);
        }

    }

    protected function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'nullable',
            'new_password' => 'required|between:6,12',
            'new_confirm_password' => 'same:new_password',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }

        try {
            $user = $request->user();
            if($request->current_password) {

                if (Hash::check($request->current_password, $user->password)) {
                    $user->update(['password' => Hash::make($request->new_password)]);
                    $response = array(
                        'error' => false,
                        'message' => "Password Changed successfully.",
                        'code' => 200,
                    );
                    return response()->json($response);

                } else {
                    $response = array(
                        'error' => true,
                        'message' => "Invalid Password",
                        'code' => 109,
                    );
                    return response()->json($response, 400);
                }
            }
            $user->update(['password' => Hash::make($request->new_password)]);
            $response = array(
                'error' => false,
                'message' => "Password Changed successfully.",
                'code' => 200,
            );
            return response()->json($response);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response,400);

        }
    }

    public function medium()
    {
        $medium = Mediums::get();
        $response = array(
            'error' => false,
            'message' => "Mediums fetched successfully",
            'medium' => $medium,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function sections(Request $request)
    {
        $user = $request->user();
        $schools = UserSchools::where('user_id', $user->id)->pluck('school_id')->toArray();
        $sections = Section::whereIn('school_id', $schools)->get();

        $response = array(
            'error' => false,
            'message' => "Sections fetched successfully",
            'sections' => $sections,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function getTeacherSchools(Request $request)
    {
        $user = $request->user();
        $schoolName = $request->get('name');

        try {
            $schools = Schools::where(function ($query) use ($schoolName) {
                if ($schoolName) {
                    $query->where('school_name', 'LIKE', "%{$schoolName}%");
                }
            })->whereHas('userSchools', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->orderBy('school_name', 'ASC')->with('country')->get();
            if (!$schools->isEmpty()) {
                $schools->transform(function ($school) {
                    $school->image = asset('storage/' . $school->image); // Assuming images are stored in the "storage/app/public" directory.
                    return $school;
                });
                return response()->json([
                    'status' => 200,
                    'message' => 'Schools fetched successfully.',
                    'data' => $schools
                ], 200);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => 'Schools Not Found',
                    'data' => []
                ], 400);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

    public function createClass(Request $request)
    {
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response,400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'section_id' => 'required',
            'subject_id' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }
        $school_id = $request->school_id;
        $section_id = $request->section_id;
        $isSchoolSection = Section::where('id', $section_id)->where('school_id', $school_id)->first();
        if (!$isSchoolSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid school or section detected.",
                'data' => [
                    "school_id" => $school_id,
                    "section_id" => $section_id
                ],
            );
            return response()->json($response, 400);
        }

        if (!$school_id) {
            $school = UserSchools::where('user_id', $user->id)->first();
            if (!$school) {
                $response = array(
                    'error' => true,
                    'message' => "Failed to create class. No school is associated with your teacher account",
                    'data' => null
                );
                return response()->json($response,400);
            }
            $school_id = $school->school_id;
        } else {
            $school = UserSchools::where('user_id', $user->id)->where('school_id', $school_id)->first();
            if (!$school) {
                $response = array(
                    'error' => true,
                    'message' => "Failed to create class. Invalid school id detected",
                    'data' => null
                );
                return response()->json($response,400);
            }
        }

        $class = ClassSchool::where('name', $request->name)
            ->where('school_id', $school_id)
            ->where('grade', $request->grade)
            ->first();

        if (!$class) {
            $class = ClassSchool::create([
                'name' => $request->name,
                'medium_id' => $request->medium_id ?? null,
                'school_id' => $school_id,
                'grade' => $request->grade,
            ]);
        }else{
            $response = array(
                'error' => true,
                'message' => "Failed to create class. Duplicate class detected",
                'data' => null
            );
            return response()->json($response,400);
        }
        $subject_id = $request->subject_id;
        $isSubject = Subject::whereIn('id',$subject_id)->first();
        if(!$isSubject){
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => $isSubject
            );
            return response()->json($response,400);
        }

        $classSection = ClassSection::where('class_id', $class->id)
            ->where('section_id', $request->section_id)
            ->where('class_teacher_id', $user->id)
            ->first();

        if (!$classSection) {
            $classSection = ClassSection::create([
                'class_id' => $class->id,
                'section_id' => $request->section_id,
                'class_teacher_id' => $user->id,
            ]);
        }

        foreach($subject_id as $subject){
            $isExists = ClassSubject::where('class_id',$class->id)->where('subject_id',$subject)->first();
            if(!$isExists){
                $classSubject = new ClassSubject();
                $classSubject->subject_id = $subject;
                $classSubject->class_id = $class->id;
                $classSubject->type = "Compulsory";
                $classSubject->save();
            }
            $classAssigned = SubjectTeacher::where('class_section_id',$classSection->id)
                ->where('subject_id',$subject)->first();
            if(!$classAssigned){
                $subjectTeacher = new SubjectTeacher();
                $subjectTeacher->class_section_id = $classSection->id;
                $subjectTeacher->subject_id = $subject;
                $subjectTeacher->teacher_id = $user->id;
                $subjectTeacher->save();
                $subjectTeacher = $subjectTeacher->refresh();
            }
        }


        return response()->json([
            'status' => 200,
            'message' => 'Class created successfully.',
            'data' => $class
        ], 200);
    }
    public function deleteClass(Request $request)
    {
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response,400);
        }

        $validator = Validator::make($request->all(), [
            'class_id' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }


        if (!Access::teacherOwnsClass($user, $request->class_id)) {
            return Access::forbidden();
        }

        $classSection = ClassSection::where('class_id', $request->class_id);
        $sectionIds = (clone $classSection)->pluck('id')->toArray();
        $class = ClassSchool::where('id', $request->class_id)->first();

        // Read the class's students before detaching them, so meetings can be
        // cleaned up and parents notified.
        $classStudents = Students::whereIn('class_section_id', $sectionIds)->with('user')->get();
        $classStudent_ids = $classStudents->pluck('user_id')->toArray();

        $meeting = Meetings::whereIn('student_id',$classStudent_ids)->first();
        if($meeting){
            $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
            if($meetingParticipantsCount == 1){
                MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
            }
            Meetings::whereIn('student_id',$classStudent_ids)->delete();
        }

        Students::whereIn('class_section_id', $sectionIds)->update([
            'class_status' => null,
            'class_section_id' => null,
        ]);

        foreach ($classStudents as $classStudent){
            $message = "Class ".($class->name ?? '') ." has been removed by the teacher";
            $this->notificationSender->sendNotification(
                "Class Removed",
                $message,
                [$classStudent->mother_id,$classStudent->father_id], "CLASS_REMOVED", $meeting);
            $this->notificationSender->saveLogs($classStudent->mother_id ?? $classStudent->father_id,$user->id,$message,'CLASS_REMOVED','Class removed by the teacher');
        }
        ClassSchool::where('id', $request->class_id)->delete();
        $classSection->delete();
        return response()->json([
            'error' => false,
            'status' => 200,
            'message' => 'Class deleted successfully.',
            'data' => []
        ]);
    }

    public function subjectDelete(Request $request){
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response,400);
        }

        $validator = Validator::make($request->all(), [
            'subject_id' => 'required',
            'class_id' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }

        $teachesSubject = SubjectTeacher::where('subject_id', $request->subject_id)
            ->where('teacher_id', $user->id)
            ->whereIn('class_section_id', ClassSection::where('class_id', $request->class_id)->pluck('id'))
            ->exists();
        if (!$teachesSubject && !Access::teacherOwnsClass($user, $request->class_id)) {
            return Access::forbidden();
        }
        $subject_id = $request->subject_id;

        $canDelete = Subject::where('id',$subject_id)->first();
//        if(!$canDelete){
//            $response = array(
//                'message' => trans('no_permission_message')
//            );
//            return response()->json($response,400);
//        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();

//        StudentReportCards::where('subject_id',$subject_id)->where('class_id',$request->class_id)->delete();
        SubjectTeacher::where('subject_id',$subject_id)
            ->where('class_section_id',$classSection->id)
            ->where('teacher_id',$user->id)->delete();

        $subjectStudents = StudentSubject::where('subject_id',$subject_id)->where('class_section_id',$classSection->id)
            ->pluck('student_id')->toArray();

        foreach ($subjectStudents as $subjectStudent){
            $student = Students::where('user_id',$subjectStudent)->first();
            if($student){

                $message = "Subject ".$canDelete->name ." has been removed by the teacher";
                $this->notificationSender->sendNotification(
                    "Subject Removed",
                    $message,
                    [$student->mother_id,$student->father_id], "SUBJECT_REMOVED", $canDelete);
                $this->notificationSender->saveLogs($student->mother_id ?? $student->father_id,$user->id,$message,'SUBJECT_REMOVED','Subject removed by the teacher');

            }
        }

        StudentSubject::where('subject_id',$subject_id)
            ->where('class_section_id',$classSection->id)->delete();

//        Attendance::where('class_section_id',$classSection->id)->where('subject_id',$subject_id)->delete();

        ClassSubject::where('subject_id',$subject_id)->where('class_id',$request->class_id)->delete();
//        Subject::where('id',$subject_id)->delete();
        return response()->json([
            'error' => false,
            'status' => 200,
            'message' => 'Subject deleted successfully.',
            'data' => []
        ]);
    }
}
