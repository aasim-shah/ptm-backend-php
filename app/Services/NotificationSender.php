<?php

namespace App\Services;

use App\Events\WebNotificationEvent;
use App\Helpers\Utility;
use App\Models\NotificationLogs;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationSender
{

    function __construct()
    {
    }

    public function sendNotification($title, $message,$user_ids, $type = null, $data = [],$web = false)
    {
        $environment = config('environment.APP_ENV');

        try {
            if (config('environment.BROADCAST_DRIVER') == "pusher") {
                foreach ($user_ids as $topic) {
                    event(new WebNotificationEvent($title, $message, $topic, $type, $data));
                }
            } else {
                Log::info("user ids for push notifications");
                Log::info($user_ids);
                $tokens = User::whereIn('id',$user_ids)
                    ->where('enable_notification',1)->pluck('fcm_id')->toArray() ?? [];
                foreach ($tokens as $topic) {
                    PushNotificationSender::sendMobileMessage($title, $message, $topic, $type, $data);
                    if($web){
                        PushNotificationSender::sendWebMessage($title, $message, $topic, $type, $data);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    public function oneTimeNotificationSend($title,$message,$topic, $type, $data )
    {
        return PushNotificationSender::sendMobileMessage($title,$message,$topic, $type, $data );
    }

    public function saveLogs($user_id,$from_user_id,$message,$type,$title = null){

        $logs = new NotificationLogs();
        $logs->id = Utility::getUUID();
        $logs->user_id = $user_id;
        $logs->from_user_id = $from_user_id;
        $logs->message = $message;
        $logs->data = null;
        $logs->title = $title;
        $logs->type = $type;
        $logs->save();
        return $logs->refresh();
    }
    public function insertLogs($data){
        return NotificationLogs::insert($data);
    }


}