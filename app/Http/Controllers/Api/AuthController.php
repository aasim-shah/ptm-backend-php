<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utility;
use App\Models\Parents;
use App\Models\Plan;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserSchools;
use App\Models\ApplicationRating;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{

    public function updateUser(Request $request)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'fcm_id' => 'required',
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }

            $user = $request->user();

            $userU = User::whereId($user->id)->first();
            if (isset($request['title']) && !empty($request['title'])) {
                $userU->title = $request['title'];
            }

                $userU->first_name = $request['first_name'];
                $userU->last_name = $request['last_name'];
                $userU->name = $request['first_name']." ".$request['last_name'];

            if (isset($request['password']) && !empty($request['password'])) {
                $userU->password = $request['password'];
            }
            if (isset($request['fcm_id']) && !empty($request['fcm_id'])) {
                $userU->fcm_id = $request['fcm_id'];
            }
            if (isset($request['language']) && !empty($request['language'])) {
                $userU->language = $request['language'];
            }
            if (isset($request['hear_about_us']) && !empty($request['hear_about_us'])) {
                $userU->hear_about_us = $request['hear_about_us'];
            }
            if (isset($request['industry']) && !empty($request['industry'])) {
                $userU->industry = $request['industry'];
            }
            if (isset($request['job_title']) && !empty($request['job_title'])) {
                $userU->job_title = $request['job_title'];
            }
            $userU->image = null;
            $remove_profile_image = $request['remove_profile_image'] === 'true';

            if(!$remove_profile_image){
                if (isset($request['image']) && !empty($request['image'])) {

                    $image = $request->image;
                    $file_name = time() . '-' . $image->getClientOriginalName();
                    $file_path = 'users/' . $file_name;
                    $destinationPath = storage_path('app/public/users');
                    $image->move($destinationPath, $file_name);

                    $userU->image = $file_path;
                }
            }


            $userU->update();
            if($user->type == 'parent'){
                $parent = Parents::where('user_id',$userU->id)->update([
                    'first_name' => $request['first_name'],
                    'last_name' => $request['last_name']
                ]);
            }
            return response()->json([
                'status' => 200,
                'message' => 'User updated Successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function createUser(Request $request)
    {
        try {
            // Only parent and teacher accounts can self-register. Admin and principal
            // accounts are created from the admin panel.
            $request->merge(['type' => strtolower((string) $request->input('type'))]);
            $validateUser = Validator::make($request->all(),
                [
                    'type' => 'required|in:parent,teacher',
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'fcm_id' => 'required',
//                    'email' => 'required', Rule::unique('users')->whereNull('deleted_at'),
                    //'email' => 'required|email|unique:users,email',
                    'email'=>['required','email',Rule::unique('users','email')->whereNull('deleted_at')],
                    'password' => 'required'
                ]);

            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }
            $otp= Utility::getOtp();

            DB::beginTransaction();
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name." ".$request->last_name,
                'fcm_id' => Utility::getUUID().Utility::getOtp(),
                'email' => $request->email,
                'industry' => $request->industry,
                'job_title' => $request->job_title,
                'country_id' => $request->country_id ?? null,
                'email_verified_at' => null,
                'pincode' => $otp,
                'password' => Hash::make($request->password),
                'title' => (isset($request->title) ? $request->title : ''),
                'language' => (isset($request->language) ? $request->language : ''),
                'type' => $request->type,
                'hear_about_us' => (isset($request->hear_about_us) ? $request->hear_about_us : ''),
            ]);

            $user->assignRole(ucfirst($request->type));
            if ($request->type == 'teacher') {
                Teacher::create([
                    'user_id' => $user->id,
                    'qualification' => $request->qualification,
                ]);
            }

            if ($request->type == 'parent') {
                Parents::create([
                    'user_id' => $user->id,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'gender' => $request->gender,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'occupation' => $request->occupation,
                    'dob' => $request->dob,
                ]);
            }
            DB::commit();
            try{

                $details = [
                    'name' =>  $user->first_name. " ".$user->last_name,
                    'otp' => $otp
                ];
                $this->activateTrial($user->id,$user->email);

                $view = view('emails.registration', compact('details'))->render();
                Utility::sendEmail($view,'Welcome to Parent Teacher Mobile',$request->email);

                Log::info("Registration email sent to ".$request->email);

            }catch (\Exception $e){
                Log::error("Failed to send email...");
                Log::error($e);
            }

            return response()->json([
                'status' => 200,
                'message' => 'User Created Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return response()->json([
                'status' => 400,
                'message' => trans('error_occurred')
            ], 500);
        }
    }

    private function activateTrial($user_id,$email){
        $subscription = new UserSubscription();
        $subscription->id = Utility::getUUID();
        $subscription->user_id = $user_id;
        $subscription->email = $email;
        $subscription->type = Plan::TRAIL;
        $subscription->remaining_time = config('environment.TRAIL')*60;
        $subscription->call_count = config('environment.TRAIL_CALL_COUNT');
        $subscription->activated_date = Carbon::now()->toDateString();
        $subscription->expire_date = Carbon::now()->addDays((config('environment.TRAIL_DAYS') ?? 5))->toDateString();
        $subscription->status = 'ACTIVE';
        $subscription->save();
    }

    public function getProfile(Request $request)
    {
        try {

            $user = $request->user();
            $userU = User::whereId($user->id)->first();

            return response()->json([
                'status' => 200,
                'message' => 'User fetched Successfully',
                'user' => $userU,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function loginUser(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(),
                [
                    'email' => 'required|email',
                    'password' => 'required'
                ]);

            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }


            if (!Auth::attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Email & Password does not match with our record.',
                ], 401);
            }

            $user = User::where('email', $request->email)->with('rate')->first();
            $hasSchool = UserSchools::where('user_id',$user->id)->first();
            $user->has_schools = (bool)$hasSchool;

            if($user && !$user->email_verified_at){

                try{
                    $otp= Utility::getOtp();
                    $user->pincode = $otp;
                    $user->save();
                    $details = [
                        'name' =>  $user->first_name. " ".$user->last_name,
                        'otp' => $otp
                    ];

                    $view = view('emails.registration', compact('details'))->render();
                    Utility::sendEmail($view,'Welcome to Parent Teacher Mobile',$user->email);

                    Log::info("Registration email sent to ".$user->email);

                }catch (\Exception $e){
                    Log::error("Failed to send email...");
                    Log::error($e);
                }

                return response()->json([
                    'status' => 200,
                    'message' => 'Your email is not verified.Please check your email inbox',
                    'user' => $user,
                    'has_school' => (bool)$hasSchool
                ], 200);
            }

            $auth = Auth::user();
            if ($request->fcm_id) {
                $auth->fcm_id = $request->fcm_id;
                $auth->save();
            }

            return response()->json([
                'status' => 200,
                'message' => 'User Logged In Successfully',
                'token_type' => 'Bearer',
                'user' => $user->refresh(),
                'has_school' => (bool)$hasSchool,
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function userSchools(Request $request)
    {
        $user = $request->user();

        $validateUser = Validator::make($request->all(),
            [
                'school_ids' => 'required|array'
            ]);
        if ($validateUser->fails()) {
            $errorMessage = $validateUser->errors()->first();
            return response()->json([
                'status' => 400,
                'message' => $errorMessage,
            ], 401);
        }
        if($user->type == 'teacher'){
            $teacherSchool = UserSchools::with('school')->where('user_id',$user->id)->first();
            if($teacherSchool){
                return response()->json([
                    'status' => 400,
                    'message' => "You have already connected a school",
                    'data' => [$teacherSchool]
                ], 400);
            }
        }

        $data = [
            "user_id" => $user->id,
            "schools" => $request->get('school_ids'),
        ];

        if($user->type == 'teacher'){
            $parentSchools = UserSchools::with('school')->where('user_id',$user->id)->whereIn('school_id',$data['schools'])->get();
            if($parentSchools && sizeof($parentSchools)>0){
                return response()->json([
                    'status' => 400,
                    'message' => "Some of the provided schools have already contacted you.",
                    "data" => $parentSchools
                ], 400);
            }
        }

        $userSchools = array();
        foreach ($data['schools'] as $school) {
            $userSchools [] = [
                "id" => Utility::getUUID(),
                "user_id" => $data['user_id'],
                "school_id" => $school
            ];
        }
        $isSchoolLinked = UserSchools::where('user_id',$data['user_id'])->whereIn('school_id',$data['schools'])->first();
        if($isSchoolLinked){
            return response()->json([
                'status' => 400,
                'message' => "Some of the provided schools have already linked you!",
            ], 400);
        }
        $userSchools = UserSchools::insert($userSchools);

        return response()->json([
            'status' => 200,
            'message' => 'User Schools Successfully Saved!',
            'token_type' => 'Bearer',
            'user' => $userSchools,
            'token' => $user->createToken("API TOKEN")->plainTextToken
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        Log::info("Profile Update API Called");

        try {
            $user = $request->user();
            $type= $user->type;
            $validateUser = Validator::make($request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'title' => 'required',
                    'gender' => 'required',
                    'language' => 'required'
                ]);
            if ($validateUser->fails()) {
                $errorMessage = $validateUser->errors()->first();
                return response()->json([
                    'status' => 400,
                    'message' => $errorMessage,
                ], 401);
            }
            $image = $request->file('image');
            $file_path = null;
            if($image) {

                if ($type == 'teacher') {
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    $file_path = 'teachers/' . $file_name;
                    resizeImage($image);
                    $destinationPath = storage_path('app/public/teachers');
                    $image->move($destinationPath, $file_name);
                }
                if ($type == 'parent') {
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    $file_path = 'parents/' . $file_name;
                    resizeImage($image);
                    $destinationPath = storage_path('app/public/parents');
                    $image->move($destinationPath, $file_name);
                }
            }
            $user = User::where('id', $user->id)->first();
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->name = $request->first_name." ".$request->last_name;

            if ($request->title) {
                $user->title = $request->title;
            }
            if ($request->gender) {
                $user->gender = $request->gender;
            }
            if ($file_path) {
                $user->image = $file_path;
            }

            if ($request->hear_about_us) {
                $user->hear_about_us = $request->hear_about_us;
            }
            if ($request->language) {
                $user->language = $request->language;
            }
            if ($request->dob) {
                $user->dob = $request->dob;
            }
            $user->save();
            $user = $user->refresh();

            if ($type == 'teacher') {
                if ($request->qualification) {
                    Teacher::updateOrCreate(['user_id' => $user->id], [
                        'qualification' => $request->qualification,
                    ]);
                }
            }
            if ($type == 'parent') {
                $parent = Parents::where('user_id', $user->id)->first();
                $parent->first_name = $request->first_name;
                $parent->last_name = $request->last_name;
                if ($request->mobile) {
                    $parent->mobile = $request->mobile;
                }
                if ($request->occupation) {
                    $parent->occupation = $request->occupation;
                }
                if ($request->dob) {
                    $parent->dob = $request->dob;
                }
                $parent->save();
            }
            return response()->json([
                'status' => 200,
                'message' => 'User updated Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function details(Request $request)
    {

        $user = User::where('id', $request->user()->id)->with('parent')
            ->with(['teacher' => function($query){
                $query->withCount(['subjects' => function ($query) {
                    $query->select(\DB::raw('count(distinct(subject_id))'));
                }]);
            }])->first();
        $hasSchool = false;
        if($user){
            $hasSchool = UserSchools::where('user_id',$user->id)->first();
            $user->has_schools = (bool)$hasSchool;
        }

        return response()->json([
            'status' => 200,
            'message' => 'User fetched Successfully',
            'user' => $user,
            'has_school' => (bool)$hasSchool
        ], 200);
    }

    public function signout(Request $request){
        $user = $request->user();
        $user = User::where('id', $user->id)->first();
        $user->fcm_id = null;
        $user->save();
        return response()->json([
            'status' => 200,
            'message' => 'User signout Successfully',
            'user' => null,
        ], 200);
    }
    public function fcm(Request $request){
        $user = $request->user();
        $fcm_id = $request->fcm_id ?? null;
        $user = User::where('id', $user->id)->first();
        $user->enable_notification = (bool)$fcm_id;
        $user->save();
        return response()->json([
            'status' => 200,
            'message' => 'Fcm updated',
            'user' => null,
        ], 200);
    }
    public function deleteUser(Request $request){
        try {
            $user = $request->user();
            $user = User::where('id', $user->id);
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'User not found.', 'code' => 404], 404);
            }
            $user->delete();

            $response = [
                'error' => false,
                'message' => 'User deleted successfully.',
                'code' => 200,
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            $response = [
                'error' => true,
                'message' => 'An error occurred while deleting the user.',
                'code' => 500,
            ];

            return response()->json($response, 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $validateRate = Validator::make($request->all(),
            [
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:255',
            ]);


            $rate = ApplicationRating::create([
                'user_id'=> $user->id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Rating and review submitted successfully.',
                'rate' => $rate,
            ], 200);



        }catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function settings(){
        return response()->json([
            'status' => 200,
            'message' => 'Settings',
            'data' => [
                'trial_period' => config('environment.TRAIL_DAYS'),
                'trial_time' => config('environment.TRAIL') * 60,
                'trial_text' => config('environment.TRAIL_DAYS')." Days Only",
                'premium_price' => config('environment.PREMIUM_PRICE'),
                'premium_time' => config('environment.PREMIUM') * 60
            ],
        ], 200);
    }

}