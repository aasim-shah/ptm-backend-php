<?php

use App\Models\Settings;
use App\Models\User;

function send_notification($user, $title, $body, $type)
{
    $FcmToken = User::where('fcm_id', '!=', '')->whereIn('id', $user)->get()->pluck('fcm_id');

    $url = 'https://fcm.googleapis.com/fcm/send';
    $serverKey = Settings::select('message')->where('type', 'fcm_server_key')->pluck('message')->first();

    $notification_data1 = [
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        "title" => $title,
        "body" => $body,
        "type" => $type,

    ];
    $notification_data2 = [
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        "type" => $type,

    ];

    $data = [
        "registration_ids" => $FcmToken,
        "notification" => $notification_data1,
        "data" => $notification_data2,
        "priority" => "high"
    ];
    $encodedData = json_encode($data);

    $headers = [
        'Authorization:key=' . $serverKey,
        'Content-Type: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);

    // Execute post
    $result = curl_exec($ch);
    if ($result == FALSE) {
        \Illuminate\Support\Facades\Log::error('Curl failed: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    // Close connection
    curl_close($ch);
}
