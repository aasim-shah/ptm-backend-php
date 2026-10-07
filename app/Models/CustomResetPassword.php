<?php

namespace App\Models;

use App\Helpers\Utility;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class CustomResetPassword extends \Illuminate\Auth\Notifications\ResetPassword
{

    /**
     * @param string $token
     */
    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $details = [
            'actionUrl' => $url,
            'actionText' => 'Reset Password'
        ];
        $view = view('vendor_overrided_email', compact('details'))->render();
        Utility::sendEmail($view,'Password Reset',$notifiable->getEmailForPasswordReset());
//        return (new MailMessage)
//            ->subject('Reset Password')
//            ->line('You are receiving this email because we received a password reset request for your account.')
//            ->action('Reset Password', $url)
//            ->line('If you did not request a password reset, no further action is required.');

    }
}