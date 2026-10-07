<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Schools;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use Carbon\Carbon;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;
use LaravelFCM\Facades\FCM;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Message\Topics;

class ChatController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function teacherChat()
    {
        $to_user = request()->to_user;

        if (Auth::user()->type != 'parent') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        $school = UserSchools::where('user_id', $sender_id)->first();

        $schoolIds = UserSchools::where('user_id', $sender_id)->pluck('school_id')->toArray();
        $schoolsPrincipals = Schools::whereIn('id', $schoolIds)->pluck('principal_id')->toArray();

        if (!$school) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'teacher');
        })->whereIn('school_id', $schoolIds)->pluck('user_id')->toArray();
        $userIds = array_merge($userIds,$schoolsPrincipals);

//        $school = Schools::where('id',$school->school_id)->first();

//        if($school->principal_id){
//            array_push($userIds,$school->principal_id);
//        }



        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'teacher');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'teacher');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];
        return response()->json($response);

    }

    public function parentChat()
    {
        $to_user = request()->to_user;
        if (Auth::user()->type != 'teacher') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        $school = UserSchools::where('user_id', $sender_id)->first();
        if (!$school) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'parent');
        })->where('school_id', $school->school_id)->pluck('user_id')->toArray();

        $school = Schools::where('id',$school->school_id)->first();

        if($school->principal_id){
            array_push($userIds,$school->principal_id);
        }

        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];

        return response()->json($response);
    }

    public function createChat(Request $request){
        if(!$request->to_user_id){
            return response()->json([
                'status' => 400,
                'message' => trans('error_occurred')
            ], 400);
        }

        $user = Auth::user();
        $to_user_id = $request->to_user_id;
        $receiver = User::where('id',$to_user_id)->first();
        if(!$receiver){
            return response()->json([
                'status' => 400,
                'message' => "Invalid user detected",
                'data' => [
                    "to_user_id" => $to_user_id
                ]
            ], 400);
        }
        $file_name = null;
        $file = $request->file;
        $destinationPath = null;
        $file_extension = null;
        if($file){
            $file_extension = $request->file_extension;
            $file_name = Utility::getUUID() . "." . $file_extension;
            $destinationPath = storage_path('app/public/chat/files');
            $file->move($destinationPath, $file_name);
        }

        $chatExists = Chat::where(function ($query) use ($user,$receiver){
            $query->where('user_id',$user->id);
            $query->where('to_user_id',$receiver->id);
        })->orWhere(function ($query) use ($user,$receiver){
            $query->where('user_id',$receiver->id);
            $query->where('to_user_id',$user->id);
        })->first();

        $is_image = false;
        $ext = explode('.',$file_name);
        if(sizeof($ext) > 1 && in_array($ext[1],['png','jpg','jpeg','svg','webp','mp4','gif'])){
            $is_image = true;
        }

        $chatJson =
            [
                'message' => $request->message,
                'sender_id' => $user->id,
                'file' => $file_name,
                'file_extension' => $file_extension,
                'is_image' => $is_image,
                'sender_name' => $user->first_name,
                'receiver_name' => $receiver->first_name,
                'created_at' => Carbon::now()
            ];

        $chatId = Utility::getUUID();
        if(!$chatExists){
            $chat = new Chat();
            $chat->id = $chatId;
            $chat->user_id = $user->id;
            $chat->to_user_id = $to_user_id;
            $chat->new_message = true;
            $chat->message = json_encode($chatJson,true);
            $chat->save();
        }else{
            $chatId = $chatExists->id;
            if($chatExists->message['sender_id'] == $user->id){
                $chatExists->new_message_count = $chatExists->new_message_count+1;
            }else{
                $chatExists->new_message_count = 1;
            }
            $chatExists->new_message = true;
            $chatExists->message = $chatJson;
            $chatExists->save();
        }
        $this->brodCastMessage($user,$request->message,$to_user_id,$chatJson);
        $this->sendMessage($request,$destinationPath,$file_name,$chatId,$chatJson);
        return response()->json($chatJson);
    }

    public function chatRead(Request $request){
        $chat_id = $request->id;
        $user = Auth::user();

        $isChat = Chat::where('id',$chat_id)
            ->where(function ($query) use ($user){
                $query->where('user_id',$user->id);
                $query->orWhere('to_user_id',$user->id);
            })
            ->first();
        if(!$isChat){
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }

        if($isChat->message['sender_id'] != $user->id){
            $isChat->new_message_count = 0;
            $isChat->save();
        }
        return response()->json($isChat->refresh());

    }
    private function brodCastMessage($user,$message,$to_user_id,$chatJson){

        $message =  $message ?? "New File";
        $senderName = $user->first_name.' '.$user->last_name;
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60*20);
        $notificationBuilder = new PayloadNotificationBuilder('New Message From : '.$senderName);
        $notificationBuilder->setBody($message?? 'New File')
            ->setSound('default')
            ->setClickAction(config('environment.APP_SITE'));
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(
            [
                'sender_name' => $senderName,
                'message' => $message,
                'body' => $chatJson
            ]);

        $tokenUser = User::where('id',$to_user_id)->where('enable_notification',1)->first();

        if($tokenUser){
            $topic = new Topics();
            $topic->topic($tokenUser->fcm_id)->andTopic(function($condition) use ($tokenUser){
                $condition->topic($tokenUser->fcm_id)->orTopic('cultural');
            });
            $data = $dataBuilder->build();
            PushNotificationSender::sendMobileMessage('New Message From : '.$senderName, $message, $tokenUser->fcm_id, "CHAT", $data);
            $this->notificationSender->saveLogs($to_user_id,$user->id,$message,'CHAT','New Message From : '.$senderName);

        }

    }

    public function sendMessage($request,$destinationPath,$fileName,$chatId,$chatJson)
    {
        $message = $request->input('message')?? 'New File';
        $to_user_id = $request->to_user_id;

        $storage = (new Factory)
            ->withServiceAccount(config('services.firebase.key_file'))
            ->withDatabaseUri(config('environment.FIREBASE_DB_URL'))
            ->createStorage();

        $firebaseFilePathName = null;
        if($destinationPath){
            $firebase_storage_path = 'Chat/'.$chatId.'/';
            $filePath = $destinationPath.'/'.$fileName;
            $firebaseFilePathName = $firebase_storage_path . $fileName;
            $object = $storage->getBucket()->upload(fopen($filePath,'r'),
                ['name' => $firebaseFilePathName]);
            $expiration = new \DateTime('2035-11-01T00:00:00.000Z');
            $firebaseFilePathName = $object->signedUrl($expiration);
        }

        $chatJson['file'] = $firebaseFilePathName;

        try{

            $firestore = new FirestoreClient([
                'projectId' => config('environment.FIREBASE_PROJECT_ID'),
                'keyFilePath' => config('services.firebase.key_file'),
            ]);
            $userRef = $firestore->collection('messages')->document($chatId);
            $chatMessagesCollectionRef = $userRef->collection('chatMessages');
            $newMessageRef = $chatMessagesCollectionRef->add([
                'text' => $message,
                'data' => $chatJson,
                'to_user_id' =>$to_user_id,
                'user' => auth()->user()->first_name,
            ]);
        }catch (\Exception $e){
            Log::error($e);
        }

    }

}