<?php

namespace App\Http\Controllers\V2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\Schools;
use App\Models\Students;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use LaravelFCM\Facades\FCM;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Message\Topics;

class PushNotificationController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function pushNotificationView()
    {
        if (Auth::user()->type == 'Principal') {
            $schools = Schools::where('principal_id', Auth::user()->id)->get();
        } else {
            $schools = Schools::all();
        }

        return view('push-notification')->with([
            'schools' => $schools
        ]);
    }

    public function sendNotification(Request $request)
    {
        Log::info("sendNotification***");
        $validator = Validator::make($request->all(),
            [
                'school_id' => 'required',
                'user_type' => 'required',
                'filter_class_section_id' => 'required',
                'user_ids'=>'required',
                'title' => 'required|max:50',
                'message' => 'required|max:200',
            ]);
        $messages = [
            'school_id' => 'The school field is required',
            'user_type' => 'The user type field is required',
            'filter_class_section_id' => 'The class field is required',
            'user_ids' => 'The users field is required'
        ];
        $validator->setCustomMessages($messages);
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return response()->json([
                'error' => true,
                'status' => 400,
                'message' => $errorMessage,
            ]);
        }
        if ($request->user_ids[0] == 'ALL'){
            if($request->school_id == 'ALL'){
                $all_user_ids = UserSchools::pluck('user_id')->toArray();
            }else{
                $all_user_ids = UserSchools::where('school_id',$request->school_id)->pluck('user_id')->toArray();
            }

            $user_ids = $all_user_ids;
        }else{
            $user_ids = $request->user_ids;
        }
        $title = $request->title;
        $message = $request->message;
        $user_ids = array_unique($user_ids);

        $this->notificationSender->sendNotification(
            $title, $message, $user_ids, 'CUSTOM_NOTIFICATION', []
        );
        foreach ($user_ids as $userId) {
            $notificationLogArray [] = [
                "id" => Utility::getUUID(),
                "user_id" => $userId,
                "from_user_id" => Auth::user()->id,
                "message" => $message,
                "type" => "CUSTOM_NOTIFICATIONS",
                "title" => $title,
                'created_at' => Carbon::now()
            ];
        }
        $this->notificationSender->insertLogs($notificationLogArray);

        return response()->json([
            'error' => false,
            'message' => trans('notification_sent_successfully')
        ]);
    }

    public function classUsers($class_id, $type, $school_id)
    {
        Log::info("classUsers");
        Log::info("class ".$class_id." type ".$type." school_id ".$school_id);

        if ($school_id == "ALL") {
            if ($type == "ALL") {
                $users = User::whereIn('type', ['teacher', 'parent'])->get();
            }else{
                $users = User::where('type', $type)->get();
            }
            Log::info($users);
            return response()->json($users);
        }

        if($class_id == 'ALL'){
            $schoolUsers = UserSchools::where('school_id', $school_id)->pluck('user_id')->toArray();
            if ($type == "ALL") {
                $users = User::whereIn('id',$schoolUsers)->whereIn('type', ['teacher', 'parent'])->get();
            }else{
                $users = User::whereIn('id',$schoolUsers)->where('type', $type)->get();
            }
            Log::info($users);
            return response()->json($users);
        }

        $schoolUsers = UserSchools::whereHas('user',function ($query) use ($type){
            $query->where('type',$type);
        })->where('school_id', $school_id)->pluck('user_id')->toArray();

        if($type == 'teacher'){
            $users = ClassSection::where('class_id',$class_id)->whereIn('class_teacher_id',$schoolUsers)->pluck('class_teacher_id')->toArray();
            $users = User::whereIn('id',$users)->get();
            return response()->json($users);
        }

        if($type == 'parent'){
            $users = $this->parents($class_id);
            return response()->json($users->get());
        }

        if($type == 'ALL'){
            $users = ClassSection::where('class_id',$class_id)->whereIn('class_teacher_id',$schoolUsers)->pluck('class_teacher_id')->toArray();
            $parents = $this->parents($class_id)->pluck('id')->toArray();
            $userIds = array_unique(array_merge($users,$parents));
            return User::whereIn('id',$userIds)->get();
        }

    }

    public function parents($class_id){
        $fIds = Students::whereHas('class_section', function ($query) use ($class_id) {
            $query->where('class_id', $class_id);
        })->pluck('father_id')->toArray();

        $mIds = Students::whereHas('class_section', function ($query) use ($class_id) {
            $query->where('class_id', $class_id);
        })->pluck('mother_id')->toArray();

        $parentIds = array_unique(array_merge($fIds,$mIds));
        return User::whereHas('parent')
            ->where('type','parent')
            ->whereIn('id', $parentIds);
    }

    public function sendMeetingNotification(Request $request)
    {
        $user_ids = [];
        try {
            foreach ($request->toArray() as $user){
                $user_ids[] = $user['id'];
            }
            $this->notificationSender->sendNotification(
                'Meeting', 'Meeting started', $user_ids, 'CUSTOM_NOTIFICATION', []
            );
            return response()->json([
                'error' => false,
                'message' => trans('notification_sent_successfully')
            ]);
        }catch (\Throwable $th){
            return response()->json([
                'error' => true,
                'message' => $th->getMessage()
            ]);
        }


    }
    public function sendtestNotification(Request $request)
    {
        try {
            $optionBuilder = new OptionsBuilder();
            $optionBuilder->setTimeToLive(60*20);

            $notificationBuilder = new PayloadNotificationBuilder('Meeting');
            $notificationBuilder->setBody('Meeting Started')
                ->setSound('default')
                ->setClickAction(config('environment.APP_URL'));
            $dataBuilder = new PayloadDataBuilder();
            $dataBuilder->addData(
                [
                    'sender_name' => 'Parent Teacher Mobile',
                    'message' => 'Meeting Started',
                    'body' => 'Meeting Started'
                ]);
            $option = $optionBuilder->build();
            $notification = $notificationBuilder->build();
            $tokenUser = User::where('id',Auth::user()->id)->first();

            $topic = new Topics();
            $topic->topic($tokenUser->fcm_id)->andTopic(function($condition) use ($tokenUser){
                $condition->topic($tokenUser->fcm_id)->orTopic('cultural');
            });
            $data = $dataBuilder->build();
            $downstreamResponse = FCM::sendTo($tokenUser->fcm_id, $option, $notification, $data);

            return $downstreamResponse->numberSuccess();

        }catch (\Throwable $th){
            Log::error($th);
        }

        Log::info('notification sent successfully');

    }
}