<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/** Firebase HTTP v1 via the supported Admin SDK. No legacy server-key endpoint. */
class PushNotificationSender
{
    public static function sendWebMessage($title,$message,$token,$type,$data=null)
    {
        return self::send($token,$type,$data);
    }
    public static function sendMobileMessage($title,$message,$token,$type,$data=null)
    {
        return self::send($token,$type,$data);
    }
    private static function send($token,$type,$data)
    {
        if (!$token || !config('delivery.push_enabled')) { return false; }
        if (!app()->environment('production') && !hash_equals((string)config('delivery.test_token_hash'),hash('sha256',(string)$token))) { return false; }
        try {
            // Lock-screen payloads do not include student names or message bodies.
            return app('firebase.messaging')->send(CloudMessage::withTarget('token',(string)$token)
                ->withNotification(Notification::create('Parent Teacher Mobile','You have a school update. Open PTM to view it securely.'))
                ->withData(['type'=>(string)$type]));
        } catch (\Throwable $e) { Log::warning('Push delivery failed',['error'=>class_basename($e)]); return false; }
    }
    public static function sendOneSignal($title,$message,$topic,$type=null,$data=null)
    {
        // This unconfigured legacy provider is deliberately not a silent fallback.
        return false;
    }
}
