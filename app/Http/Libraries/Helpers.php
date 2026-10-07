<?php


namespace App\Http\Libraries;


use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

class Helpers
{

    /** Upload file
     * @param file $file
     * @param location
     * @return string
     */
    public function uploadFile($file = null, $location = null)
    {
        $path = '';
        if (!is_null($file) && !is_null($location)) {
            $file = $file;
            $path = $file->store('public/'.$location);
            return $path;
        }
        return $path;
    }


    public function EmailSystem($data){

        $email = new \SendGrid\Mail\Mail();
        $email->setFrom("fanfeedback@parentteachermobile.com", "parentteachermobile.com");
        $email->setSubject($data['subject']);
        $email->addTo($data['email'], "Parent Teacher Mobile");
        $email->addContent("text/html", $data['view']);
        $sendgrid = new \SendGrid(config('environment.SENDGRID_API_KEY') ?: config('environment.MAIL_PASSWORD'));
        try {
            $sendgrid->send($email);
        } catch (\Exception $e) {

        }
    }


    public function encrypt_string($string_to_encrypt)
    {
        $id = '_'.$string_to_encrypt;
        $timestamp = time();
        $randomKey = rand();
        $key = base64_encode($timestamp . $randomKey . $id);
        return $key;
    }

    public function decrypt_string($encrypted_string)
    {
        $password=base64_decode($encrypted_string);
        $password=explode('_',$password);
        return $password[1];
    }

}
