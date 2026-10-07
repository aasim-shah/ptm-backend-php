<?php

namespace App\Http\Controllers;

use App\Mail\SampleMail;
use App\Models\ClassSubject;
use App\Models\MainMeeting;
use App\Models\Meetings;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\Settings;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PDO;
use Exception;
use Throwable;
use App\Models\User;
use App\Models\Parents;
use App\Models\Category;
use App\Models\Students;
use App\Models\ClassSchool;
use App\Models\SessionYear;
use App\Models\ClassSection;
use Illuminate\Http\Request;
use App\Imports\StudentsImport;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ExamMarks;
use App\Models\ExamResult;
use App\Models\FeesChoiceable;
use App\Models\FeesPaid;
use App\Models\OnlineExamStudentAnswer;
use App\Models\PaymentTransaction;
use App\Models\StudentOnlineExamStatus;
use App\Models\StudentSessions;
use App\Models\StudentSubject;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }


    public function sendMail(Request $request){


    }


    public function index()
    {
        if (!Auth::user()->can('student-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if(Auth::user()->type == 'Principal'){
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id',$userId)->first();
            $schools = Schools::where('principal_id',$principal->user_id)->pluck('id')->toArray();
            $class_section = ClassSection::whereHas('class')->SubjectTeacher()->whereHas('section',function ($query) use ($schools){
                $query->whereHas('school');
                $query->whereIn('school_id',$schools);
            })->with('class', 'section')->get();
            $schools = null;
        }else{
            $class_section = [];
            $schools = Schools::all();
        }
        $category = Category::where('status', 1)->get();
        return view('students.details', compact('class_section', 'category','schools'));
    }

    public function create()
    {
        if (!Auth::user()->can('student-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }

        $class_section = ClassSection::whereHas('class')->with('class', 'section')->get();
        $category = Category::where('status', 1)->get();
        $data = getSettings('session_year');
        $session_year = SessionYear::select('name')->where('id', $data['session_year'])->pluck('name')->first();
        $get_student = Students::withTrashed()->latest('id')->pluck('user_id')->first();
        $admission_no = $session_year . ($get_student + 1);
        return view('students.index', compact('class_section', 'category', 'admission_no'));
    }

    public function createBulkData()
    {
        if (!Auth::user()->can('student-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class_section = ClassSection::with('class', 'section')->get();
        // $category = Category::where('status', 1)->get();
        // $data = getSettings('session_year');
        // $session_year = SessionYear::select('name')->where('id', $data['session_year'])->pluck('name')->first();
        // $get_student = Students::select('id')->latest('id')->pluck('id')->first();
        // $admission_no = $session_year . ($get_student + 1);

        return view('students.add_bulk_data', compact('class_section'));
    }

    public function storeBulkData(Request $request)
    {
        if (!Auth::user()->can('student-create') || !Auth::user()->can('student-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'file' => 'required|mimes:csv'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $class_section_id = $request->class_section_id;
            Excel::import(new StudentsImport($class_section_id), $request->file);
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
            );
        }
        return response()->json($response);
    }

    public function update(Request $request)
    {
        Log::info($request);
        if (!Auth::user()->can('student-create') || !Auth::user()->can('student-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'mobile' => 'nullable|regex:/^([0-9\s\-\+\(\)]*)$/',
            'image' => 'mimes:jpeg,png,jpg|image|max:2048',
            'dob' => 'required',
            'class_section_id' => 'required',
            'admission_no' => 'unique:users,email,' . $request->edit_id,
            'admission_date' => 'required',

        ]);
        if (!intval($request->father_email)) {
            $request->validate([
                'father_email' => 'email|unique:users,email,' . $request->father_email,
                'father_image' => 'mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }

        if (!intval($request->mother_email)) {
            $request->validate([
                'mother_email' => 'email|unique:users,email,' . $request->mother_email . '|unique:parents,email,' . $request->mother_email,
                'mother_image' => 'mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }

        if (isset($request->guardian_email) && !intval($request->guardian_email)) {
            $request->validate([
                'guardian_email' => 'email|unique:parents,email,' . $request->guardian_email,
                'guardian_image' => 'mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }

        try {
            //Add Father in User and Parent table data
//            if (!intval($request->father_email)) {
//                $father_user = new User();
//                $father_user->image = $request->file('father_image')->store('parents', 'public');
//                $father_user->password = Hash::make(str_replace('/', '', $request->father_dob));
//                $father_user->first_name = $request->father_first_name;
//                $father_user->last_name = $request->father_last_name;
//                $father_user->email = $request->father_email;
//                $father_user->mobile = $request->father_mobile;
//                $father_user->dob = date('Y-m-d', strtotime($request->father_dob));
//                $father_user->gender = 'Male';
//                $father_user->save();
//
//                $father_parent = new Parents();
//                $father_parent->user_id = $father_user->id;
//                $father_parent->first_name = $request->father_first_name;
//                $father_parent->last_name = $request->father_last_name;
//                $father_parent->image = $father_user->getRawOriginal('image');
//                $father_parent->occupation = $request->father_occupation;
//                $father_parent->mobile = $request->father_mobile;
//                $father_parent->email = $request->father_email;
//                $father_parent->dob = date('Y-m-d', strtotime($request->father_dob));
//                $father_parent->gender = 'Male';
//                $father_parent->save();
//                $father_parent_id = $father_parent->id;
//            } else {
//                $father_parent_id = $request->father_email;
//            }
//
//            //Add Mother in User and Parent table data
//            if (!intval($request->mother_email)) {
//                $mother_user = new User();
//                $mother_user->image = $request->file('mother_image')->store('parents', 'public');
//                $mother_user->password = Hash::make(str_replace('/', '', $request->mother_dob));
//                $mother_user->first_name = $request->mother_first_name;
//                $mother_user->last_name = $request->mother_last_name;
//                $mother_user->email = $request->mother_email;
//                $mother_user->mobile = $request->mother_mobile;
//                $mother_user->dob = date('Y-m-d', strtotime($request->mother_dob));
//                $mother_user->gender = 'Female';
//                $mother_user->save();
//
//                $mother_parent = new Parents();
//                $mother_parent->user_id = 0;
//                $mother_parent->first_name = $request->mother_first_name;
//                $mother_parent->last_name = $request->mother_last_name;
//                $mother_parent->image = $mother_user->getRawOriginal('image');
//                $mother_parent->occupation = $request->mother_occupation;
//                $mother_parent->mobile = $request->mother_mobile;
//                $mother_parent->email = $request->mother_email;
//                $mother_parent->dob = date('Y-m-d', strtotime($request->mother_dob));
//                $mother_parent->gender = 'Female';
//                $mother_parent->save();
//                $mother_parent_id = $mother_parent->id;
//            } else {
//                $mother_parent_id = $request->mother_email;
//            }



            $student = Students::where('user_id', $request->edit_id)->first();

            $fatherId = $student->father_id;
            $motherId = $student->mother_id;
            if(isset($fatherId)){

                $fatherUser = User::find($fatherId);

                $fatherUser->first_name = $request->father_first_name;
                $fatherUser->last_name = $request->father_last_name;
                $fatherUser->save();

                $fatherParent = Parents::where('user_id',$fatherId)->first();

                $fatherParent->first_name = $request->father_first_name;
                $fatherParent->last_name = $request->father_last_name;
                $fatherParent->save();


            }else{
                $motherUser = User::find($motherId);

                $motherUser->first_name = $request->mother_first_name;
                $motherUser->last_name = $request->mother_last_name;
                $motherUser->save();

                $motherParent = Parents::where('user_id',$motherId)->first();

                $motherParent->first_name = $request->mother_first_name;
                $motherParent->last_name = $request->mother_last_name;
                $motherParent->save();

            }


            //Create Student User First
            $user = User::find($request->edit_id);
            //            $user->password = Hash::make(str_replace('/', '', $request->dob));
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            //            $user->email = (isset($request->email)) ? $request->email : "";
            //            $user->email = $request->admission_no;
            $user->mobile = (isset($request->mobile)) ? $request->mobile : "";
            $user->dob = date('Y-m-d', strtotime($request->dob));
            $user->current_address = $request->current_address;
            $user->permanent_address = $request->permanent_address;
            $user->gender = $request->gender;

            //If Image exists then upload new image and delete the old image
            if ($request->hasFile('image')) {
                if ($user->getRawOriginal('image') && Storage::disk('public')->exists($user->getRawOriginal('image'))) {
                    Storage::disk('public')->delete($user->getRawOriginal('image'));
                }

                $student_image = $request->file('image');
                // made file name with combination of current time
                $file_name = time() . '-' . $student_image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'students/' . $file_name;
                //resized image
                resizeImage($student_image);
                //stored image to storage/public/students folder
                $destinationPath = storage_path('app/public/students');
                $student_image->move($destinationPath, $file_name);
                $user->image = $file_path;
            }
            $user->save();

            $student = Students::where('user_id', $user->id)->firstOrFail();
            $student->class_section_id = $request->class_section_id;
            $student->category_id = $request->category_id;
            //            $student->admission_no = $request->admission_no;
            $student->roll_number = $request->roll_number;
            $student->caste = $request->caste;
            $student->religion = $request->religion;
            $student->admission_date = date('Y-m-d', strtotime($request->admission_date));
//            $student->blood_group = $request->blood_group;
            $student->height = $request->height;
            $student->weight = $request->weight;
            if (isset($fatherParent_id)){
                $student->father_id = $fatherId;
            }else{
                $student->mother_id = $motherId;
            }

            $student->save();
            
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Exception $e) {
            Log::error('aaaa '.$e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('student-create') || !Auth::user()->can('student-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'mobile' => 'nullable|regex:/^([0-9\s\-\+\(\)]*)$/',
            'image' => 'mimes:jpeg,png,jpg|image|max:2048',
            'dob' => 'required',
            'class_section_id' => 'required',
            'category_id' => 'required',
            'admission_no' => 'required|unique:users,email',
            //            'caste' => 'required',
            //            'religion' => 'required',
            'admission_date' => 'required',
            'height' => 'required',
            'weight' => 'required',
            'current_address' => 'required',
            'permanent_address' => 'required',

            'father_first_name' => 'required',
            'father_last_name' => 'required',
            'father_mobile' => 'required',
            'father_occupation' => 'required',

            'mother_first_name' => 'required',
            'mother_last_name' => 'required',
            'mother_mobile' => 'required',
            'mother_occupation' => 'required',
        ]);
        if (!intval($request->father_email)) {
            $request->validate([
                'father_email' => 'required|email|unique:users,email|unique:parents,email',
                'father_image' => 'required|mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }

        if (!intval($request->mother_email)) {
            $request->validate([
                'mother_email' => 'required|email|unique:users,email|unique:parents,email',
                'mother_image' => 'required|mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }

        if (isset($request->guardian_email) && !intval($request->guardian_email)) {
            $request->validate([
                'guardian_email' => 'required|email|unique:parents,email',
                'guardian_image' => 'required|mimes:jpeg,png,jpg|image|max:2048',
            ]);
        }
        $response = array();
        try {
            $parentRole = Role::where('name', 'Parent')->first();
            $studentRole = Role::where('name', 'Student')->first();
            //Add Father in User and Parent table data
            $father_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($request->father_dob)));
            if (!intval($request->father_email)) {
                $father_email = $request->father_email;
                $father_user = new User();

                $father_image = $request->file('father_image');
                // made file name with combination of current time
                $file_name = time() . '-' . $father_image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'parents/' . $file_name;
                //resized image
                resizeImage($father_image);
                //stored image to storage/public/parents folder
                $destinationPath = storage_path('app/public/parents');
                $father_image->move($destinationPath, $file_name);

                $father_user->image = $file_path;
                $father_user->password = Hash::make($father_plaintext_password);
                $father_user->first_name = $request->father_first_name;
                $father_user->last_name = $request->father_last_name;
                $father_user->email = $father_email;
                $father_user->mobile = $request->father_mobile;
                $father_user->dob = date('Y-m-d', strtotime($request->father_dob));
                $father_user->gender = 'Male';
                $father_user->save();
                $father_user->assignRole($parentRole);

                $father_parent = new Parents();
                $father_parent->user_id = $father_user->id;
                $father_parent->first_name = $request->father_first_name;
                $father_parent->last_name = $request->father_last_name;
                $father_parent->image = $father_user->getRawOriginal('image');
                $father_parent->occupation = $request->father_occupation;
                $father_parent->mobile = $request->father_mobile;
                $father_parent->email = $request->father_email;
                $father_parent->dob = date('Y-m-d', strtotime($request->father_dob));
                $father_parent->gender = 'Male';
                $father_parent->save();
                $father_parent_id = $father_parent->id;
                $father_email = $request->father_email;
                $father_name = $request->father_first_name;
            } else {
                $father_parent_id = $request->father_email;
                $father_email = Parents::where('id', $request->father_email)->pluck('email')->first();
                $father_name = Parents::where('id', $request->father_email)->pluck('first_name')->first();
            }

            //Add Mother in User and Parent table data
            $mother_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($request->mother_dob)));
            if (!intval($request->mother_email)) {
                $mother_email = $request->mother_email;
                $mother_user = new User();

                $mother_image = $request->file('mother_image');
                // made file name with combination of current time
                $file_name = time() . '-' . $mother_image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'parents/' . $file_name;
                //resized image
                resizeImage($mother_image);
                //stored image to storage/public/parents folder
                $destinationPath = storage_path('app/public/parents');
                $mother_image->move($destinationPath, $file_name);

                $mother_user->image = $file_path;
                $mother_user->password = Hash::make($mother_plaintext_password);
                $mother_user->first_name = $request->mother_first_name;
                $mother_user->last_name = $request->mother_last_name;
                $mother_user->email = $mother_email;
                $mother_user->mobile = $request->mother_mobile;
                $mother_user->dob = date('Y-m-d', strtotime($request->mother_dob));
                $mother_user->gender = 'Female';
                $mother_user->save();
                $mother_user->assignRole($parentRole);

                $mother_parent = new Parents();
                $mother_parent->user_id = $mother_user->id;
                $mother_parent->first_name = $request->mother_first_name;
                $mother_parent->last_name = $request->mother_last_name;
                $mother_parent->image = $mother_user->getRawOriginal('image');
                $mother_parent->occupation = $request->mother_occupation;
                $mother_parent->mobile = $request->mother_mobile;
                $mother_parent->email = $request->mother_email;
                $mother_parent->dob = date('Y-m-d', strtotime($request->mother_dob));
                $mother_parent->gender = 'Female';
                $mother_parent->save();
                $mother_parent_id = $mother_parent->id;
                $mother_email = $request->mother_email;
                $mother_name = $request->mother_first_name;
            } else {
                $mother_parent_id = $request->mother_email;
                $mother_email = Parents::where('id', $request->mother_email)->pluck('email')->first();
                $mother_name = Parents::where('id', $request->mother_email)->pluck('first_name')->first();
            }

            if (isset($request->guardian_email)) {
                if (!intval($request->guardian_email)) {
                    $guardian_email = $request->guardian_email;
                    $guardian_parent = new Parents();
                    $guardian_parent->user_id = 0;
                    $guardian_parent->first_name = $request->guardian_first_name;
                    $guardian_parent->last_name = $request->guardian_last_name;

                    $guardian_image = $request->file('guardian_image');
                    // made file name with combination of current time
                    $file_name = time() . '-' . $guardian_image->getClientOriginalName();
                    //made file path to store in database
                    $file_path = 'parents/' . $file_name;
                    //resized image
                    resizeImage($guardian_image);
                    //stored image to storage/public/parents folder
                    $destinationPath = storage_path('app/public/parents');
                    $guardian_image->move($destinationPath, $file_name);

                    $guardian_parent->image = $file_path;
                    $guardian_parent->occupation = $request->guardian_occupation;
                    $guardian_parent->mobile = $request->guardian_mobile;
                    $guardian_parent->email = $guardian_email;
                    $guardian_parent->dob = date('Y-m-d', strtotime($request->guardian_dob));
                    $guardian_parent->gender = $request->guardian_gender;
                    $guardian_parent->save();
                    $guardian_parent_id = $guardian_parent->id;
                    $guardian_name = $request->guardian_first_name;
                } else {
                    $guardian_parent_id = $request->guardian_email;
                }
            } else {
                $guardian_parent_id = 0;
            }

            //Create Student User First

            $user = new User();

            //roll number
            $roll_number_db = Students::select(DB::raw('max(roll_number)'))->where('class_section_id',$request->class_section_id)->first();
            $roll_number_db = $roll_number_db['max(roll_number)'];
            $roll_number = $roll_number_db + 1;

            $child_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($request->dob)));

            $student_image = $request->file('image');
            // made file name with combination of current time
            $file_name = time() . '-' . $student_image->getClientOriginalName();
            $file_type = $file_name->getClientMimeType();
            Log::info('file Type '.$file_type);
            if($file_type === 'application/octet-stream'){
                $file_name = str_replace('.octet-stream', '.jpeg', $file_name);
                Log::info('file name '.$file_name);
            }
            //made file path to store in database
            $file_path = 'students/' . $file_name;
            Log::info('file path'.$file_path);
            //resized image
            resizeImage($student_image);
            //stored image to storage/public/students folder
            $destinationPath = storage_path('app/public/students');
            $student_image->move($destinationPath, $file_name);


            $user->image = $file_path;
            $user->password = Hash::make($child_plaintext_password);
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            //            $user->email = (isset($request->email)) ? $request->email : "";
            $user->email = $request->admission_no;
            $user->gender = $request->gender;
            $user->mobile = $request->mobile;
            $user->dob = date('Y-m-d', strtotime($request->dob));
            $user->current_address = $request->current_address;
            $user->permanent_address = $request->permanent_address;
            $user->save();
            $user->assignRole($studentRole);

            $student = new Students();
            $student->user_id = $user->id;
            $student->class_section_id = $request->class_section_id;
            $student->category_id = $request->category_id;
            $student->admission_no = $request->admission_no;
            $student->roll_number = $roll_number;
            $student->caste = $request->caste;
            $student->religion = $request->religion;
            $student->admission_date = date('Y-m-d', strtotime($request->admission_date));
//            $student->blood_group = $request->blood_group;
            $student->height = $request->height;
            $student->weight = $request->weight;
            $student->father_id = $father_parent_id;
            $student->mother_id = $mother_parent_id;
            $student->guardian_id = $guardian_parent_id;
            $student->save();

            //Send User Credentials via Email
            $school_name = getSettings('school_name');
            $father_data = [
                'subject' => 'Welcome to ' . $school_name['school_name'],
                'email' => $father_email,
                'name' => ' ' . $father_name,
                'username' => ' ' . $father_email,
                'password' => ' ' . $father_plaintext_password,
                'child_name' => ' ' . $request->first_name,
                'child_grnumber' => ' ' . $request->admission_no,
                'child_password' => ' ' . $child_plaintext_password,
            ];
            Mail::send('students.email', $father_data, function ($message) use ($father_data) {
                $message->to($father_data['email'])->subject($father_data['subject']);
            });

            $mother_data = [
                'subject' => 'Welcome to ' . $school_name['school_name'],
                'email' => $mother_email,
                'name' => ' ' . $mother_name,
                'username' => ' ' . $mother_email,
                'password' => ' ' . $mother_plaintext_password,
                'child_name' => ' ' . $request->first_name,
                'child_grnumber' => ' ' . $request->admission_no,
                'child_password' => ' ' . $child_plaintext_password,
            ];
            Mail::send('students.email', $mother_data, function ($message) use ($mother_data) {
                $message->to($mother_data['email'])->subject($mother_data['subject']);
            });
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    public function show(Request $request)
    {
        if (!Auth::user()->can('student-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');

        if(Auth::user()->type == 'Principal'){
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id',$userId)->first();
            $schools = Schools::where('principal_id',$principal->user_id)->pluck('id')->toArray();

            $class_section = ClassSection::SubjectTeacher()->whereHas('section',function ($query) use ($schools){
                $query->whereHas('school');
                $query->whereIn('school_id',$schools);
            })->pluck('id')->toArray();
            $sql = Students::whereHas('user',function ($query){
                $query->whereNull('deleted_at');
            })->whereIn('class_section_id',$class_section)->where('class_status','ACCEPTED')->with('user', 'class_section', 'category', 'father', 'mother', 'guardian')->ofTeacher();
        }else{
            if ($request->school_id){
                $school_id = $request->school_id;
                $class_section = ClassSection::SubjectTeacher()->whereHas('class')->whereHas('section',function ($query) use ($school_id){
                    $query->whereHas('school');
                    $query->where('school_id',$school_id);
                })->with('class', 'section')->pluck('id')->toArray();
                $sql = Students::whereHas('user', function ($query) {
                    $query->whereNull('deleted_at');
                })->whereIn('class_section_id', $class_section)->where('class_status','ACCEPTED')
                    ->with('user', 'class_section', 'category', 'father', 'mother', 'guardian')->ofTeacher();
            }else{
                $sql = Students::whereHas('user', function ($query) {
                    $query->whereNull('deleted_at');
                })->whereNotNull('class_section_id')->where('class_status','ACCEPTED')
                    ->with('user', 'class_section', 'category', 'father', 'mother', 'guardian')->ofTeacher();
            }
        }
            //search query
        $sql->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search){
                    $query->where('user_id', 'LIKE', "%$search%");
//                        ->orWhere('class_section_id', 'LIKE', "%$search%")
//                        ->orWhere('category_id', 'LIKE', "%$search%")
//                        ->orWhere('admission_no', 'LIKE', "%$search%")
                        $query->orWhere('roll_number', 'LIKE', "%$search%");
//                        ->orWhere('caste', 'LIKE', "%$search%")
//                        ->orWhere('religion', 'LIKE', "%$search%")
//                        ->orWhere('admission_date', 'LIKE', date('Y-m-d', strtotime("%$search%")))
//                        ->orWhere('blood_group', 'LIKE', "%$search%")
//                        ->orWhere('height', 'LIKE', "%$search%")
//                        ->orWhere('weight', 'LIKE', "%$search%")
//                        ->orWhere('is_new_admission', 'LIKE', "%$search%");
                        $query->orWhereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'LIKE', "%$search%")
                                ->orwhere('last_name', 'LIKE', "%$search%")
                                ->orwhere('email', 'LIKE', "%$search%")
                                ->orwhere('dob', 'LIKE', "%$search%");
                        });
//                        ->orWhereHas('father', function ($q) use ($search) {
//                            $q->where('first_name', 'LIKE', "%$search%")
//                                ->orwhere('last_name', 'LIKE', "%$search%")
//                                ->orwhere('email', 'LIKE', "%$search%")
//                                ->orwhere('mobile', 'LIKE', "%$search%")
//                                ->orwhere('occupation', 'LIKE', "%$search%")
//                                ->orwhere('dob', 'LIKE', "%$search%");
//                        })
//                        ->orWhereHas('mother', function ($q) use ($search) {
//                            $q->where('first_name', 'LIKE', "%$search%")
//                                ->orwhere('last_name', 'LIKE', "%$search%")
//                                ->orwhere('email', 'LIKE', "%$search%")
//                                ->orwhere('mobile', 'LIKE', "%$search%")
//                                ->orwhere('occupation', 'LIKE', "%$search%")
//                                ->orwhere('dob', 'LIKE', "%$search%");
//                        })
                    $query->orWhereHas('category', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
            //class filter data
            })->when(request('class_id') != null, function ($query) {
                $classId = request('class_id');
                $query->where(function ($query) use ($classId) {
                    $query->where('class_section_id', $classId);
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        $data = getSettings('date_formate');
        foreach ($res as $row) {
            $operate = '';
            if (Auth::user()->hasRole('Principal') && Auth::user()->can('student-edit')) {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center editdata" data-id=' . $row->id . ' data-url=' . url('students') . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            }

            if (Auth::user()->can('student-delete')) {
                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center deletedata" data-id=' . $row->id . ' data-user_id=' . $row->user_id . ' data-url=' . url('students', $row->user_id) . ' title="Delete"><i class="fa fa-trash"></i></a>';
            }

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['user_id'] = $row->user_id;
            $tempRow['first_name'] = $row->user->first_name;
            $tempRow['last_name'] = $row->user->last_name;
            $tempRow['email'] = $row->user->email;
            $tempRow['dob'] = date($row->user->dob);
            $tempRow['mobile'] = $row->user->mobile;
            $tempRow['image'] = $row->user->image;
            $tempRow['image_link'] = $row->user->image;
            $tempRow['class_section_id'] = $row->class_section_id;
            if ($row->class_section) {
                if ($row->class_section->class) {
                    $tempRow['class_section_name'] = $row->class_section->class->name . "-" . $row->class_section->section->name;
                } else {
                    $tempRow['class_section_name'] = '';
                }
            } else {
                $tempRow['class_section_name'] = '';
            }
            $tempRow['category_id'] = $row->category_id;
            $tempRow['category_name'] = $row->category ? $row->category->name : null;
            $tempRow['admission_no'] = $row->admission_no;
            $tempRow['roll_number'] = $row->roll_number;
            $tempRow['caste'] = $row->caste;
            $tempRow['religion'] = $row->religion;
            $tempRow['admission_date'] = date($data['date_formate'], strtotime($row->admission_date));
            $tempRow['blood_group'] = $row->blood_group;
            $tempRow['height'] = $row->height;
            $tempRow['weight'] = $row->weight;
            $tempRow['current_address'] = $row->user->current_address;
            $tempRow['permanent_address'] = $row->user->permanent_address;
            $tempRow['is_new_admission'] = $row->is_new_admission;

            if (!empty($row->father)) {
                //Father Data
                $tempRow['father_id'] = $row->father->id;
                $tempRow['father_email'] = $row->father->email;
                $tempRow['father_first_name'] = $row->father->first_name;
                $tempRow['father_last_name'] = $row->father->last_name;
                $tempRow['father_mobile'] = $row->father->mobile;
                $tempRow['father_dob'] = $row->father->dob;
                $tempRow['father_occupation'] = $row->father->occupation;
                if ( $row->father->user){
                    $tempRow['father_image'] = $row->father->user->image;
                }
                $tempRow['father_image_link'] = $row->father->image;
            }

            if (!empty($row->mother)) {
                //Mother Data
                $tempRow['mother_id'] = $row->mother->id;
                $tempRow['mother_email'] = $row->mother->email;
                $tempRow['mother_first_name'] = $row->mother->first_name;
                Log::info($row->mother->first_name);
                $tempRow['mother_last_name'] = $row->mother->last_name;
                $tempRow['mother_mobile'] = $row->mother->mobile;
                $tempRow['mother_dob'] = $row->mother->dob;
                $tempRow['mother_occupation'] = $row->mother->occupation;
                if ($row->mother->user){
                    $tempRow['mother_image'] = $row->mother->user->image;
                }
                $tempRow['mother_image_link'] = $row->mother->image;
            }

            if (!empty($row->guardian)) {
                //Father Data
                $tempRow['guardian_id'] = $row->guardian->id;
                $tempRow['guardian_email'] = $row->guardian->email;
                $tempRow['guardian_first_name'] = $row->guardian->first_name;
                $tempRow['guardian_last_name'] = $row->guardian->last_name;
                $tempRow['guardian_mobile'] = $row->guardian->mobile;
                $tempRow['guardian_gender'] = $row->guardian->gender;
                $tempRow['guardian_dob'] = $row->guardian->dob;
                $tempRow['guardian_occupation'] = $row->guardian->occupation;
                $tempRow['guardian_image'] = $row->guardian->image;
                $tempRow['guardian_image_link'] = $row->guardian->image;
            }

            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('student-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {

//            $student_id = Students::where('user_id', $id)->pluck('user_id')->first();
//
//            // find that student is associate with other tables ..
//            $assignment_submissions = AssignmentSubmission::where('student_id',$student_id)->count();
//            $attendances = Attendance::where('student_id',$student_id)->count();
//            $exam_marks = ExamMarks::where('student_id',$student_id)->count();
//            $exam_results = ExamResult::where('student_id',$student_id)->count();
//            $fees_choiceables = FeesChoiceable::where('student_id',$student_id)->count();
//            $fees_paids = FeesPaid::where('student_id',$student_id)->count();
//            $online_exam_answers = OnlineExamStudentAnswer::where('student_id',$student_id)->count();
//            $payment_transactions = PaymentTransaction::where('student_id',$student_id)->count();
//            $online_exam_status = StudentOnlineExamStatus::where('student_id',$student_id)->count();
//            $student_sessions = StudentSessions::where('student_id',$student_id)->count();
//            $student_subjects = StudentSubject::where('student_id',$student_id)->count();
//
//            if($assignment_submissions || $attendances || $exam_marks || $exam_results || $fees_choiceables || $fees_paids || $online_exam_answers || $payment_transactions || $online_exam_status || $student_sessions || $student_subjects){
//                $response = array(
//                    'error' => true,
//                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
//                );
//            }else{


            $user = Auth::user();
            $student = Students::where('user_id', $id)->first();
            $classSectionId = $student->class_section_id;



            $class = ClassSection::where('id', $classSectionId)->with('class')->first();

            $isSchoolsAssigned = Students::where('class_section_id',$class->id)->with('user')->where('user_id', $id)->first();
            if($isSchoolsAssigned){
                $isSchoolsAssigned->class_section_id = null;
                $isSchoolsAssigned->class_status = null;
                $isSchoolsAssigned->save();
            }

            $classSubjects = ClassSubject::where('class_id',$class->class_id)->pluck('subject_id')->toArray();
            $session_year_id = Settings::select('message')->where('type', 'session_year')->pluck('message')->first();

            foreach ($classSubjects as $classSubject) {
                StudentSubject::where([
                    'student_id' => $id,
                    'subject_id' => $classSubject,
                    'class_section_id' => $class->id,
                    'session_year_id' => intval($session_year_id)
                ])->delete();
            }

            $meeting = Meetings::where('student_id',$id)->first();
            if($meeting){
                $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
                if($meetingParticipantsCount == 1){
                    MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
                }
                Meetings::where('student_id',$id)->delete();
            }

            $user_student = User::where('id',$id)->first();
            $user_student->deleted_at = Carbon::now();
            $user_student->save();
            $message = "Student ".$isSchoolsAssigned->user->first_name." ".$isSchoolsAssigned->user->last_name.
                " has been removed from ".$class->class->name." by ".Auth::user()->type;
            $this->notificationSender->sendNotification(
                "Student Removed",
                $message,
                [$class->class_teacher_id], "STUDENT_REMOVED", $meeting);
            $this->notificationSender->saveLogs($class->class_teacher_id,$user->id,$message,'STUDENT_REMOVED','Student removed from the class');


            $message = "Student ".$isSchoolsAssigned->user->first_name." ".$isSchoolsAssigned->user->last_name.
                " has been removed from ".$class->class->name." by ".Auth::user()->type;
            $this->notificationSender->sendNotification(
                "Student Removed",
                $message,
                [$isSchoolsAssigned->mother_id,$isSchoolsAssigned->father_id], "STUDENT_REMOVED", $meeting);
            $this->notificationSender->saveLogs($isSchoolsAssigned->mother_id ?? $isSchoolsAssigned->father_id,$user->id,$message,'STUDENT_REMOVED','Student removed from the class');


            $response = [
                'error' => false,
                'message' => trans('data_delete_successfully')
            ];

        } catch (Throwable $e) {
            Log::info('message '.$e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }


    public function reset_password()
    {
        if (!Auth::user()->can('reset-password-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $sql = User::where('reset_request', 1);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")
                ->orwhere('first_name', 'LIKE', "%$search%")
                ->orwhere('last_name', 'LIKE', "%$search%")
                ->orWhereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'");
        }

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = '<button class="btn btn-xs btn-gradient-primary btn-action btn-rounded btn-icon reset_password" data-id=' . $row->id . ' title="Reset-Password"><i class="fa fa-edit"></i></button>&nbsp;&nbsp;';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->first_name . ' ' . $row->last_name;
            $tempRow['dob'] = $row->dob;
            $tempRow['email'] = $row->email;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function change_password(Request $request)
    {
        if (!Auth::user()->can('student-change-password')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {
            $dob = date('mdY', strtotime($request->dob));
            $user = User::find($request->id);
            $user->reset_request = 0;
            $user->password = Hash::make($dob);
            $user->save();

            $response = [
                'error' => false,
                'message' => trans('data_update_successfully')
            ];
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function assignClass()
    {
        //        if (!Auth::user()->can('student-list')) {
        //            $response = array(
        //                'message' => trans('no_permission_message')
        //            );
        //            return redirect(route('home'))->withErrors($response);
        //        }
        $class_section = ClassSection::with('class', 'section')->get();
        $class = ClassSchool::with('medium')->get();
        $category = Category::where('status', 1)->get();
        return view('students.assign-class', compact('class_section', 'class', 'category'));
    }

    public function newStudentList(Request $request)
    {
        //        if (!Auth::user()->can('student-list')) {
        //            $response = array(
        //                'message' => trans('no_permission_message')
        //            );
        //            return response()->json($response);
        //        }
        $sort = 'id';
        $order = 'DESC';

        $class_id = $request->class_id;
        $get_class_section_id = ClassSection::select('id')->where('class_id', $class_id)->get()->pluck('id');
        $sql = Students::with('user:id,first_name,last_name,image', 'class_section')->whereIn('class_section_id', $get_class_section_id)->where('is_new_admission', 1);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orWhere('user_id', 'LIKE', "%$search%")
                ->orWhere('class_section_id', 'LIKE', "%$search%")
                ->orWhere('is_new_admission', 'LIKE', "%$search%")
                ->orWhereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%$search%")
                        ->orwhere('last_name', 'LIKE', "%$search%");
                });
        }
        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        foreach ($res as $row) {
            $assign_student = '<input type="checkbox" class="assign_student"  name="assign_student" value=' . $row->id . '>';
            $data = getSettings('date_formate');
            $tempRow['chk'] = $assign_student;
            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['user_id'] = $row->user_id;
            $tempRow['first_name'] = $row->user->first_name;
            $tempRow['last_name'] = $row->user->last_name;
            $tempRow['image'] = $row->user->image;
            $tempRow['class_section_id'] = $row->class_section_id;
            $tempRow['class_section_name'] = $row->class_section->class->name . "-" . $row->class_section->section->name;
            $tempRow['admission_no'] = $row->admission_no;
            $tempRow['roll_number'] = $row->roll_number;
            $tempRow['admission_date'] = date($data['date_formate'], strtotime($row->admission_date));
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }


    public function assignClass_store(Request $request)
    {
        //        if (!Auth::user()->can('student-list')) {
        //            $response = array(
        //                'message' => trans('no_permission_message')
        //            );
        //            return redirect(route('home'))->withErrors($response);
        //        }
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'selected_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $selected_student = explode(',', $request->selected_id);
            $class_section_id = $request->class_section_id;
            $session_year = getSettings('session_year');
            for ($i = 0; $i < count($selected_student); $i++) {
                $student = Students::find($selected_student[$i]);
                $student->class_section_id = $class_section_id;
                $student->is_new_admission = 0;
                $student->save();
                $student_session = new StudentSessions;
                $student_session->student_id = $student->user_id;
                $student_session->class_section_id = $class_section_id;
                $student_session->session_year_id = $session_year['session_year'];
                $student_session->status = 1;
                $student_session->save();
            }
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }
    public function indexStudentRollNumber()
    {
        if (!Auth::user()->can('student-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class_section = ClassSection::with('class', 'section')->get();

        return view('students.assign_roll_no', compact('class_section'));
    }
    public function listStudentRollNumber(Request $request)
    {
        if (!Auth::user()->can('student-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            if (!Auth::user()->can('student-list')) {
                $response = array(
                    'message' => trans('no_permission_message')
                );
                return response()->json($response);
            }
            $class_section_id = $request->class_section_id;
            $sql = User::with('student');
            $sql = $sql->whereHas('student', function ($q) use ($class_section_id) {
                $q->where('class_section_id', $class_section_id);
            });
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'] ?? '';
                $sql->where('first_name', 'LIKE', "%$search%")
                    ->orwhere('last_name', 'LIKE', "%$search%")
                    ->orwhere('email', 'LIKE', "%$search%")
                    ->orwhere('dob', 'LIKE', "%$search%")
                    ->orWhereHas('student',function($q)use($search){
                        $q->where('id', 'LIKE', "%$search%")
                            ->orWhere('user_id', 'LIKE', "%$search%")
                            ->orWhere('class_section_id', 'LIKE', "%$search%")
                            ->orWhere('admission_no', 'LIKE', "%$search%")
                            ->orWhere('admission_date', 'LIKE', date('Y-m-d', strtotime("%$search%")))
                            ->orWhereHas('user', function ($q) use ($search) {
                            });
                    });
            }
            if ($request->sort_by == 'first_name') {
                $sql = $sql->orderBy('first_name', 'ASC');
            }
            if ($request->sort_by == 'last_name') {
                $sql = $sql->orderBy('last_name', 'ASC');
            }
            $total = $sql->count();

            $res = $sql->get();


            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            $tempRow = array();
            $no = 1;
            $data = getSettings('date_formate');
            $roll = 1;
            $index = 0;
            foreach ($res as $row) {
                $tempRow['no'] = $no++;
                $tempRow['student_id'] = $row->student->id;
                $tempRow['old_roll_number'] = $row->student->roll_number;

                // for edit roll number comment below line
                $tempRow['new_roll_number'] = "<input type='hidden' name='roll_number_data[" . $index . "][student_id]' class='form-control' readonly value=" . $row->student->id . "> <input type='hidden' name='roll_number_data[" . $index . "][roll_number]' class='form-control' value=" . $roll . ">".$roll;

                // and uncomment below line
                // $tempRow['new_roll_number'] = "<input type='hidden' name='roll_number_data[" . $index . "][student_id]' class='form-control' readonly value=" . $row->student->id . "> <input type='text' name='roll_number_data[" . $index . "][roll_number]' class='form-control' value=" . $roll . ">";


                $tempRow['user_id'] = $row->id;
                $tempRow['first_name'] = $row->first_name;
                $tempRow['last_name'] = $row->last_name;
                $tempRow['dob'] = date($data['date_formate'], strtotime($row->dob));
                $tempRow['image'] = $row->image;
                $tempRow['admission_no'] = $row->student->admission_no;
                $tempRow['admission_date'] = date($data['date_formate'], strtotime($row->admission_date));
                $rows[] = $tempRow;
                $index++;
                $roll++;
            }

            $bulkData['rows'] = $rows;
            return response()->json($bulkData);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
            return response()->json($response);
        }
    }
    public function storeStudentRollNumber(Request $request)
    {
        if (!Auth::user()->can('student-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'roll_number_data.*.roll_number' => 'required',
            ],
            [
                'roll_number_data.*.roll_number.required' => trans('please_fill_all_roll_numbers_data')
            ]
        );
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $i = 1;
        foreach ($request->roll_number_data as $data) {
            $student = Students::find($data['student_id']);

            // validation required when the edit of roll number is enabled

            // $class_roll_number_data = Students::where(['class_section_id' => $student->class_section_id,'roll_number' => $data['roll_number']])->whereNot('id',$data['student_id'])->count();
            // if(isset($class_roll_number_data) && !empty($class_roll_number_data)){
            //     $response = array(
            //         'error' => true,
            //         'message' => trans('roll_number_already_exists_of_number').' - '.$i
            //     );
            //     return response()->json($response);
            // }


            $student->roll_number = $data['roll_number'];
            $student->save();
            $i++;
        }
        $response = [
            'error' => false,
            'message' => trans('data_store_successfully')
        ];
        return response()->json($response);
    }

    public function teacherClasses($id){

        $class = ClassSchool::whereHas('sections',function ($query) use ($id){
            $query->where('class_teacher_id',$id);
        })->with('allSubjects.subject')
            ->with(['class_section' => function($query) use($id){
                $query->withCount(['students'=>function($query){
                    $query->whereHas('user');
                }]);
            }])->get();
        return response()->json($class);
    }
    public function classParents($id){

        $class_id = $id;
        $class = ClassSchool::where('id', $class_id)->first();
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'parent');
        })->where('school_id', $class->school_id)->pluck('user_id')->toArray();

        $parents = User::whereHas('parent')
            ->whereIn('id', $userIds)->get();

        $students = Students::where(function ($query) use($userIds){
            $query->whereIn('father_id',$userIds);
            $query->orWhereIn('mother_id',$userIds);
        })->with('user')->get();

        foreach ($parents as $parent){
            $parentStudents = [];
            foreach ($students as $student){
                if($student->father_id == $parent->id || $student->mother_id == $parent->id){
                    $parentStudents[] = $student;
                }
            }
            $parent->parent_students = $parentStudents;
        }

        return response()->json($parents);
    }
    public function classStudents($id){

        $students = User::whereHas('student', function ($query) use ($id) {
            $query->whereHas('class_section', function ($query) use ($id) {
                $query->where('class_id', $id);
            });
        })
            ->with('student')
            ->get();

        return response()->json($students);
    }
}
