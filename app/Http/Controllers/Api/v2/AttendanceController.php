<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Students;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\User;
use App\Services\NotificationSender;
use Carbon\Carbon;
use Exception;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function attendance(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'class_id' => 'required',
            'present_student_ids' => 'array',
            'absence_student_id' => 'array',
            'date' => 'required',
            'subject_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if(!$classSection){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected",
                'data' => null
            );
            return response()->json($response);
        }
        try {

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];
            $date = date('Y-m-d', strtotime($request->date));

            $presentList = $request->present_student_ids;
            $absenceList = $request->absence_student_id;
            $subject_id = $request->subject_id;
            Attendance::where([
                'date' => $date,
                'subject_id' => $subject_id,
                'class_section_id' => $classSection->id])->delete();

            if(sizeof($presentList) > 0){
                $available = Students::whereIn('user_id',$presentList)->get();
                if(!$available || sizeof($presentList) != sizeof($available)){
                    $response = array(
                        'error' => true,
                        'message' => "Invalid students detected"
                    );
                    return response()->json($response);
                }
            }

            if(sizeof($absenceList) > 0){
                $available = Students::whereIn('user_id',$absenceList)->get();
                if(!$available || sizeof($available) != sizeof($absenceList)){
                    $response = array(
                        'error' => true,
                        'message' => "Invalid students detected"
                    );
                    return response()->json($response);
                }
            }

            $present = array();
            $absence = array();
            for ($i = 0; $i < count($presentList); $i++) {
                $present [] = [
                    "class_section_id" => $classSection->id,
                    "student_id" => $presentList[$i],
                    "session_year_id" => $session_year_id,
                    "subject_id" => $subject_id,
                    "type" => 1,
                    "date" => $request->date,
                ];
            }

            for ($i = 0; $i < count($absenceList); $i++) {
                $absence [] = [
                    "class_section_id" => $classSection->id,
                    "student_id" => $absenceList[$i],
                    "session_year_id" => $session_year_id,
                    "subject_id" => $subject_id,
                    "type" => 0,
                    "date" => $request->date,
                ];
            }
            Attendance::insert($present);
            $presentNotificationLogArray = array();
            $sub = Subject::where('id',$subject_id)->first();
            foreach ($present as $pSId) {
                $presentStudent = Students::with('user')->where('user_id',$pSId['student_id'])->first();
                $message = $presentStudent->user->first_name." ".$presentStudent->user->last_name." attended the ".$sub->name." class on ".$request->date;
                $this->notificationSender->sendNotification(
                    "Student Attendance",
                    $message,
                    [$presentStudent->mother_id , $presentStudent->father_id], "ATTENDANCE");
                $presentNotificationLogArray [] = [
                    "id" => Utility::getUUID(),
                    "user_id" => $presentStudent->mother_id ?? $presentStudent->father_id,
                    "from_user_id" => $user->id,
                    "message" => $message,
                    "type" => "ATTENDANCE",
                    "title" => "Student Attendance",
                    'created_at' => Carbon::now()
                ];
            }
            $this->notificationSender->insertLogs($presentNotificationLogArray);

            Attendance::insert($absence);
            $absentNotificationLogArray = array();
            $sub = Subject::where('id',$subject_id)->first();

            foreach ($absence as $aSId) {
                $absentStudent = Students::with('user')->where('user_id',$aSId['student_id'])->first();
                $message = $absentStudent->user->first_name." ".$absentStudent->user->last_name." was absent the ".$sub->name." class on ".$request->date;
                $this->notificationSender->sendNotification(
                    "Student Attendance",
                    $message,
                    [$absentStudent->mother_id , $absentStudent->father_id], "ATTENDANCE");
                $absentNotificationLogArray [] = [
                    "id" => Utility::getUUID(),
                    "user_id" => $absentStudent->mother_id ?? $absentStudent->father_id,
                    "from_user_id" => $user->id,
                    "message" => $message,
                    "type" => "ATTENDANCE",
                    "title" => "Student Attendance",
                    'created_at' => Carbon::now()
                ];
            }
            $this->notificationSender->insertLogs($absentNotificationLogArray);

            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Exception $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function editAttendance(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'class_id' => 'required',
            'present_student_ids' => 'array',
            'absence_student_id' => 'array',
            'date' => 'required',
            'subject_id' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if(!$classSection){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected",
                'data' => null
            );
            return response()->json($response);
        }
        try {

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];
            $date = date('Y-m-d', strtotime($request->date));

            Attendance::where(['date' => $date, 'class_section_id' => $classSection->id])->delete();
            $presentList = $request->present_student_ids;
            $absenceList = $request->absence_student_id;
            $subject_id = $request->subject_id;

            if(sizeof($presentList)>0){
                Attendance::where(['date' => $date, 'class_section_id' => $classSection->id])->whereIn('student_id',$presentList)->delete();
            }
            if(sizeof($absenceList)>0){
                Attendance::where(['date' => $date, 'class_section_id' => $classSection->id])->whereIn('student_id',$absenceList)->delete();
            }

            $present = array();
            $absence = array();
            for ($i = 0; $i < count($presentList); $i++) {
                $present [] = [
                    "class_section_id" => $classSection->id,
                    "student_id" => $presentList[$i],
                    "session_year_id" => $session_year_id,
                    "subject_id" => $subject_id,
                    "type" => 1,
                    "date" => $request->date,
                ];
            }

            for ($i = 0; $i < count($absenceList); $i++) {
                $absence [] = [
                    "class_section_id" => $classSection->id,
                    "student_id" => $absenceList[$i],
                    "session_year_id" => $session_year_id,
                    "subject_id" => $subject_id,
                    "type" => 0,
                    "date" => $request->date,
                ];
            }
            Attendance::insert($present);
            Attendance::insert($absence);

            $response = [
                'error' => false,
                'message' => trans('data_update_successfully')
            ];
        } catch (Exception $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function viewAttendance(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }

        $class_id = $request->class_id;
        $date = $request->date;
        $subject_id = $request->subject_id;
        $classSection = ClassSection::where('class_id', $class_id)->first();
        if(!$classSection){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected",
                'data' => null
            );
            return response()->json($response);
        }

        $attendance = Attendance::where('date', $date)
            ->where('class_section_id', $classSection->id)
            ->where('subject_id', $subject_id)
            ->with('student')->get();

        $absentCount = Attendance::where('date', $date)
            ->where('class_section_id', $classSection->id)
            ->where('type', false)
            ->where('subject_id', $subject_id)
            ->with('student')->count();

        $presentCount = Attendance::where('date', $date)
            ->where('class_section_id', $classSection->id)
            ->where('type', true)
            ->where('subject_id', $subject_id)
            ->with('student')->count();

        $response = array(
            'error' => false,
            'message' => 'Attendance fetched Successfully.',
            'data' => [
                "absent_count" => $absentCount,
                "present_count" => $presentCount,
                "attendance" => $attendance,
            ],
            'code' => 200,
        );
        return response()->json($response);

    }

}