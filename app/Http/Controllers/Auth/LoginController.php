<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function loginWeb(Request $request){
        $validateUser = Validator::make($request->all(),
            [
                'email' => 'required|email',
                'password' => 'required'
            ]);

        if ($validateUser->fails()) {
            $errorMessage = $validateUser->errors()->first();
            $response = array(
                'message' => $errorMessage
            );
            Auth::logout();
            return back()->withErrors($response);
        }


        if (!Auth::attempt($request->only(['email', 'password']))) {
            $response = array(
                'message' => trans('invalid_credentials')
            );
            Auth::logout();
            return back()->withErrors($response);
        }

        $user = Auth::user();
        if ($user->hasAnyRole(['Super Admin', 'Principal'])) {
            $request->session()->regenerate();
            $user->timezone_offset = $request->timezone_offset;
            $user->save();
            return redirect(route('home'));
        }
        $response = array(
            'message' => trans('no_permission_message')
        );

        Auth::logout();
        return back()->withErrors($response);
    }
}
