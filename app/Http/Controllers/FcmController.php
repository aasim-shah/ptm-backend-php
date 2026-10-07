<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Models\Chat;
use App\Models\User;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Contract\Firestore;
use LaravelFCM\Facades\FCM;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Message\Topics;
use Kreait\Firebase\Factory;
use Google\Cloud\Firestore\FirestoreClient;

class FcmController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function saveToken()
    {
        $token = \request()->fcm_token;
        $user_id = Auth::user()->id;

        $user = User::where('id', $user_id)->first();
        $user->fcm_id = $token;
        $user->save();

        return response()->json([
            "status" => 200
        ], 200);
    }

    public function createChat(Request $request){

        if(!$request->id){
            $response = array(
                'message' => trans('error_occurred')
            );
            return redirect()->back()->withErrors($response);
        }
        $user = Auth::user();
        $to_user_id = $request->id;
        $receiver = User::where('id',$to_user_id)->first();

        if(!$receiver){
            $response = array(
                'message' => "Invalid user detected"
            );
            return redirect()->back()->withErrors($response);
        }

        $file_name = null;
        $destinationPath = null;
        $file = $request->file;
        $file_extension = null;
        if($file && $file !='undefined'){
            $file_extension = explode("/", $file->getClientMimeType())[1];
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
                'file' => $file_name,
                'file_extension' => $file_extension,
                'sender_id' => $user->id,
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
            $chatExists->new_message = true;
            if(!$chatExists->message || $chatExists->message['sender_id'] == $user->id){
                $chatExists->new_message_count = $chatExists->new_message_count+1;
            }else{
                $chatExists->new_message_count = 1;
            }
            $chatExists->message = $chatJson;
            $chatExists->save();
        }
        $this->brodCastMessage($user,$request->message,$to_user_id,$chatJson);
        $this->sendMessage($request,$destinationPath,$file_name,$chatId,$chatJson);

        return response()->json($chatJson);
    }

    private function brodCastMessage($user,$message,$to_user_id,$chatJson){
        $message =  $message ?? "New File";

        $senderName = $user->first_name.' '.$user->last_name;
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60*20);

        $notificationBuilder = new PayloadNotificationBuilder('New Message From : '.$senderName);
        $notificationBuilder->setBody($message)
            ->setSound('default')
            ->setClickAction(config('environment.APP_SITE'));
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(
            [
                'sender_name' => $senderName,
                'message' => $message,
                'body' => $chatJson
            ]);
        $tokenUser = User::where('id',$to_user_id)->first();

        $topic = new Topics();
        $topic->topic($tokenUser->fcm_id)->andTopic(function($condition) use ($tokenUser){
            $condition->topic($tokenUser->fcm_id)->orTopic('cultural');
        });
        $data = $dataBuilder->build();

        $tokenUser = User::where('id',$to_user_id)->where('enable_notification',1)->first();
        PushNotificationSender::sendMobileMessage('New Message From : '.$senderName, $message,$tokenUser ? $tokenUser->fcm_id:null, "CHAT", $data);
        $this->notificationSender->saveLogs($to_user_id,$user->id,$message,'CHAT','New Message From : '.$senderName);

    }

    public function sendMessage($request,$destinationPath,$fileName,$chatId,$chatJson)
    {
        $message = $request->input('message');
        $to_user_id = $request->id;

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
            $expiration = new \DateTime('2032-01-01T00:00:00.000Z');
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
    public function index()
    {
    }

}