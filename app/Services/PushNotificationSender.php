<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PushNotificationSender
{
    private static string $url = "https://fcm.googleapis.com/fcm/send";
    private static $oneSignalUrl = "https://onesignal.com/api/v1/notifications";

    public static function sendWebMessage($title, $message, $topic, $type, $data = null)
    {
        Log::info('sending firebase notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);
        $key = config('environment.PUSH_KEY');
        $fields = array(
            'to' => $topic,
            'priority' => "high",
            'data' => [
                'type' => $type,
                'click_action' => config('environment.APP_DASHBOARD'),
                'data' => $data
            ],
            'notification' => array("title" => $title, "body" => $message,"click_action"=>config('environment.APP_DASHBOARD'))
        );

        $headers = array(
            self::$url,
            'Content-Type: application/json',
            'Authorization: key=' . $key
        );


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            return false;
        }

        curl_close($ch);
        return $result;

    }
    public static function sendMobileMessage($title, $message, $topic, $type, $data = null)
    {
        Log::info('sending firebase notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);
        $key = config('environment.PUSH_KEY');

        $fields = array(
            'to' => "/topics/" .$topic,
            'priority' => "high",
            'data' => ['type' => $type,
                'click_action' => "FLUTTER_NOTIFICATION_CLICK",
                'data' => $data
            ],
            'notification' => array("title" => $title, "body" => $message)
        );

        $headers = array(
            self::$url,
            'Content-Type: application/json',
            'Authorization: key=' . $key
        );


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            return false;
        }

        curl_close($ch);
        return $result;

    }

    public static function sendOneSignal($title, $message, $topic, $type = null, $data = null){
        Log::info('sending oneSignal notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => self::$oneSignalUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'app_id' => config('environment.ONESIGNAL_APP_ID'),
                'include_player_ids' => [$topic],
                'include_external_user_ids ' => [$topic],
                'contents' => [
                    'en' => $message,
                ],
                "web_url" => config('environment.APP_DASHBOARD'),
                'name' => $title
            ]),
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic ".config('environment.ONESIGNAL_REST_API_KEY'),
                "accept: application/json",
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        Log::info($response);
        if($err){
            Log::error($err);
        }

        return $response;
    }

}