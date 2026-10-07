<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Webpatser\Uuid\Uuid;

class Utility
{

   public static function convertDateWithTimeZone($attr,$current_time_zone) {
            $utc = strtotime($attr)-date('Z');
            $attr = $utc+$current_time_zone;
            return date("Y-m-d H:i:s", $attr);
    }
    public static function sendEmail($view,$subject,$toMail){
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom(config('environment.MAIL_FROM_ADDRESS'), config('environment.MAIL_FROM_NAME'));
        $email->setSubject($subject);
        $email->addTo($toMail, "Parent Teacher");
        $email->addContent("text/html",$view);
        $sendgrid = new \SendGrid(config('environment.MAIL_PASSWORD'));
        try {
            $sendgrid->send($email);
            Log::info('email sent to '.$toMail);
            return true;
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
                'data' => []
            ], 200);
        }
    }
    public static function getUUID()
    {
        try {
            return md5(Uuid::generate() . time());
        } catch (\Exception $e) {
            return md5(rand(100, 999) . time() . rand(1, 999999));
        }
    }

    public static function getOtp(){
        if(config('environment.OTP_ENABLED')){
            return rand(10000,99999);
        }
        return 55555;
    }
    public static function time_elapsed_string($datetime, $full = false) {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}