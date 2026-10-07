<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;
    protected $throttle = 60;

    public function sendResetLinkEmail(Request $request)
    {
        Log::info('sendResetLinkEmail.....');
        $this->validateEmail($request);

        $user = User::where($this->credentials($request))->first();
        if (is_null($user)) {
            return $this->sendResetLinkFailedResponse($request, PasswordBroker::INVALID_USER);
        }
        if ($user->type != 'Principal') {
            return $this->sendResetLinkFailedResponse($request, PasswordBroker::INVALID_USER);
        }

        $reset = PasswordReset::where(
            'email', $user->getEmailForPasswordReset()
        )->first();

        if ($reset && $this->tokenRecentlyCreated($reset)) {
//            return $this->sendResetLinkFailedResponse($request, PasswordBroker::RESET_THROTTLED);
        }
        $token = $this->createToken($request, $user, $reset);
        //keep in mind that saved token is hashed version of this
        $user->sendPasswordResetNotification($token);

        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }
    public function createToken($request, $user, $reset)
    {
        $email = $user->getEmailForPasswordReset();

        if ($reset) {
            PasswordReset::where('email',$email)->delete();
        }

        $token = $this->createNewToken();

        PasswordReset::create([
            'user_id' => $user->id,
            'email' => $email,
            'token' => bcrypt($token),
            'created_at' => Carbon::now(),
            'ip_address' => $request->ip()
        ]);

        return $token;
    }
    public function createNewToken()
    {
        return hash_hmac('sha256', Str::random(40), $this->getHashKey());
    }

    /**
     * Replicate hash key used by DatabaseTokenRepository
     */
    public function getHashKey()
    {
        $key = config('app.key');
        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        return $key;
    }
    protected function tokenRecentlyCreated($token)
    {
        if ($this->throttle <= 0) {
            return false;
        }

        return Carbon::parse($token->created_at)->addSeconds(
            $this->throttle
        )->isFuture();
    }
}
