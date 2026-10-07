<?php

namespace App\Http\Controllers;

use App\Models\Principal;
use App\Models\Schools;
use App\Models\UserSchools;
use GPBMetadata\Google\Api\Log;
use Throwable;
use App\Models\User;
use App\Models\Parents;
use App\Models\Teacher;
use App\Models\Students;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Settings;
use Illuminate\Http\Request;
use App\Models\SubjectTeacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function login()
    {
        if (Auth::user()) {
            return view('auth.login');
        } else {
            return view('auth.login');
        }
    }

    public function index()
    {
        $teacher = null;
        $student = null;
        $parent = null;
        $teachers = null;
        $boys = 0;
        $girls = 0;
        $schoolsCount = 0;
        $announcement = [];
        if (Auth::user()->hasRole('Super Admin')) {
            $teacher = Teacher::whereNull('deleted_at')->count();
            $student = Students::whereNull('deleted_at')->whereNotNull('class_section_id')->where('class_status','ACCEPTED')->count();
            $parent = Parents::whereNull('deleted_at')->count();


            $teachers = Teacher::whereHas('user')->with('user:id,first_name,last_name,image')
                ->whereHas('user', function ($query) {
                    $query->where('status', 1)
                        ->whereNotNull('email_verified_at')
                        ->whereNull('deleted_at');
                })
                ->get();
            foreach ($teachers as $teac) {
                if ($teac->user->image === null) {
                    $defaultImage = 'profile/default-image.jpg';
                    $teac->user->image = $defaultImage;
                }
            }

            if ($student > 0) {
                $boys_count = Students::whereNotNull('class_section_id')->where('class_status','ACCEPTED')->whereHas('user', function ($query) {
                    $query->where('gender', 'male')->whereNull('deleted_at');
                })->count();
                $girls_count = Students::whereNotNull('class_section_id')->where('class_status','ACCEPTED')->whereHas('user', function ($query) {
                    $query->where('gender', 'female')->whereNull('deleted_at');
                })->count();

                $boys = round((($boys_count * 100) / $student), 2);
                $girls = round(($girls_count * 100) / $student, 2);
            }
            $announcement = Announcement::where('table_type', "")->orderBy('created_at', 'desc')->limit(5)->get();
            $schoolsCount = Schools::count();
        }
        if (Auth::user()->hasRole('Principal')) {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id',$userId)->first();
            $schools = Schools::where('principal_id',$principal->user_id)->pluck('id')->toArray();
            $schoolsCount = null;

            $class_section = ClassSection::SubjectTeacher()->whereHas('section',function ($query) use ($schools){
                $query->whereHas('school');
                $query->whereIn('school_id',$schools);
            });

            $class_teacher_ids = $class_section->pluck('class_teacher_id')->toArray();
            $class_section_ids = $class_section->pluck('id')->toArray();

            $schoolTeacher= UserSchools::whereIn('school_id',$schools)->pluck('user_id')->toArray();
            $teacher = Teacher::whereIn('user_id', $schoolTeacher)
                ->whereNull('deleted_at')->whereHas('user', function ($query) {
                    $query->whereNotNull('email_verified_at')
                        ->whereNull('deleted_at');
                })
                ->count();

            $student = Students::whereIn('class_section_id',$class_section_ids)->where('class_status','ACCEPTED')->count();

            $schoolParents= UserSchools::whereIn('school_id',$schools)->pluck('user_id')->toArray();
            $parent = Parents::whereIn('user_id',$schoolParents)
                ->whereHas('user', function ($query) {
                $query->whereNotNull('email_verified_at')
                    ->whereNull('deleted_at');
            })
                ->count();

            $teachers = Teacher::whereIn('user_id', $schoolTeacher)
                ->with('user:id,first_name,last_name,image')
                ->whereHas('user', function ($query) {
                    $query->where('status', 1)
                        ->whereNotNull('email_verified_at')
                        ->whereNull('deleted_at');
                })
                ->get();
            foreach ($teachers as $teac) {
                if ($teac->user->image === null) {
                    $defaultImage = 'profile/default-image.jpg';
                    $teac->user->image = $defaultImage;
                }
            }
            if ($student > 0) {
                $boys_count = Students::whereIn('class_section_id',$class_section_ids)->where('class_status','ACCEPTED')->whereHas('user', function ($query) {
                    $query->where('gender', 'male')->whereNull('deleted_at');
                })->count();
                $girls_count = Students::whereIn('class_section_id',$class_section_ids)->where('class_status','ACCEPTED')->whereHas('user', function ($query) {
                    $query->where('gender', 'female')->whereNull('deleted_at');
                })->count();

                $boys = round((($boys_count * 100) / $student), 2);
                $girls = round(($girls_count * 100) / $student, 2);
            }
            $announcement = Announcement::whereIn('school_id',$schools)->where('table_type', "")->orderBy('created_at', 'desc')->limit(5)->get();
        }
        return view('home', compact('teacher', 'parent', 'student', 'announcement', 'teachers', 'boys', 'girls','schoolsCount'));
    }



    public function resetpassword()
    {
        return view('settings.reset_password');
    }

    public function checkPassword(Request $request)
    {
        $old_password = $request->old_password;
        $password = User::where('id', Auth::id())->first();
        if (Hash::check($old_password, $password->password)) {
            return response()->json(1);
        } else {
            return response()->json(0);
        }
    }

    public function changePassword(request $request)
    {
        $id = Auth::id();
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);
        try {
            $user = User::where('id', $id)->first();
            if (!Hash::check($request->old_password, $user->password)) {
                $response = array(
                    'error' => true,
                    'message' => trans('The old password is incorrect ')
                );
            }else{
                $data['password'] = Hash::make($request->new_password);
                $user->update($data);
                $response = array(
                    'error' => false,
                    'message' => trans('data_update_successfully')
                );
            }

        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }




    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();
        return redirect('/');
    }

    public function getSubjectByClassSection(Request $request)
    {
        $class_section = ClassSection::select('class_id')->where('id', $request->class_section_id)->first();
        $subjects = ClassSubject::SubjectTeacher()->where('class_id', $class_section->class_id)->with('subject')->get();
        return response($subjects);
    }
    public function getTeacherByClassSubject(Request $request)
    {
        // find the teachers which exists in class_section with subject
        $teacher_exists = SubjectTeacher::where(['class_section_id' => $request->class_section_id, 'subject_id' => $request->subject_id])->pluck('teacher_id')->toArray();
        if (sizeof($teacher_exists)) {
            // if data is edited then find teachers according to it
            if (isset($request->edit_id) && !empty($request->edit_id)) {
                $teacher_id = SubjectTeacher::where('id', $request->edit_id)->pluck('teacher_id')->first();
                unset($teacher_exists[array_search($teacher_id, $teacher_exists)]);
                array_values($teacher_exists);
            }
            //remove the existsing teachers for class section with subject
            $teachers = Teacher::with('user')->whereNotIn('id', $teacher_exists)->get();
        } else {
            // get all teachers..
            $teachers = Teacher::with('user')->get();
        }
        return response($teachers);
    }
    function resetPasswordView()
    {
        $class_section = ClassSection::with('class', 'section')->get();
        return view('students.reset_password', compact('class_section'));
    }

    public function pwReset(Request $request){

    }


    public function editProfile()
    {
        $admin_data = Auth::user();
        return view('settings.update_profile', compact('admin_data'));
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'mobile' => 'required|digits_between:4,15',
            'gender' => 'required',
            'dob' => 'required',
            'email' => 'required|email|regex:/^[^@]+@[^@]+\.[^@]+$/',
            'image' => 'nullable|mimes:jpeg,png,jpg|image|max:5048',
            'current_address' => 'required',
            'permanent_address' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $user_db = User::find($request->id);
            $user_db->first_name = $request->first_name;
            $user_db->last_name = $request->last_name;
            $user_db->mobile = $request->mobile;
            $user_db->gender = $request->gender;
            $user_db->dob = date('Y-m-d', strtotime($request->dob));
            $user_db->email = $request->email;
            $user_db->current_address = $request->current_address;
            $user_db->permanent_address = $request->permanent_address;
            if (!empty($request->image)) {
                if (!is_null($user_db->getRawOriginal('image')) &&Storage::disk('public')->exists($user_db->getRawOriginal('image'))) {
                    Storage::disk('public')->delete($user_db->getRawOriginal('image'));
                }

                $image = $request->image;
                // made file name with combination of current time
                $file_name = time() . '-' . $image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'user/' . $file_name;
                //resized image
                // resizeImage($image);
                //stored image to storage/public/user folder
                $destinationPath = storage_path('app/public/user');
                $image->move($destinationPath, $file_name);

                $user_db->image = $file_path;
            }
            $user_db->save();
            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully')
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
    public function updateWarningModal(Request $request){
        try {
            $data = array(
                'type' => 'update_warning_modal',
                'message' => 1,
            );
            Settings::insert($data);
            return redirect()->back();
        }catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
}
