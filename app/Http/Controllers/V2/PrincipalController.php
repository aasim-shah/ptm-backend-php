<?php

namespace App\Http\Controllers\V2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\MainMeeting;
use App\Models\Meetings;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrincipalController extends Controller
{

    public function index()
    {
        if (Auth::user()->type != 'admin') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }

        $principals = null;
        $schools = Schools::whereDoesntHave('principal')->get();
        return response(view('principal.index', compact('principals', 'schools')));

    }

    public function unlink($user_id,$school_id = null){
        $school = Schools::where('id',$school_id)->where('principal_id',$user_id)->first();
        if($school){
            $school->principal_id = null;
            $school->save();
            $response = [
                'error' => false,
                'message' => 'Data unlinked successfully',
            ];
        }else{
            $response = [
                'error' => true,
                'message' => trans('no_school_assigned')
            ];
        }
        return response()->json($response);
    }

    public function remove($user_id,){
        $removed = User::where('id',$user_id)->delete();
        $school = Schools::where('principal_id',$user_id)->first();
        if($school){
            $school->principal_id = null;
            $school->save();
        }
        $meeting = Meetings::where('meeting_created_user_type',"Principal")->where('user_id',$user_id)->first();
        if($meeting){
            $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
            if($meetingParticipantsCount == 1){
                MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
            }
            Meetings::where('meeting_created_user_type',"Principal")->where('user_id',$user_id)->delete();
        }

        if($removed){
            $response = [
                'error' => false,
                'message' => trans('data_delete_successfully')
            ];
        }else{
            $response = [
                'error' => true,
                'message' => trans('error_occurred')
            ];
        }
        return response()->json($response);
    }

    public function store(Request $request)
    {
        Log::info($request);
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'password' => 'required|min:6|max:8',
            'gender' => 'required',
            'email' => [
                'required',
                'email','regex:/^[^@]+@[^@]+\.[^@]+$/',
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                }),
            ],
            'dob' => 'required|date',
            'qualification' => 'required|max:500',
            'school_id' => 'required',
            'current_address' => 'required|max:100',
            'permanent_address' => 'required|max:100',
            'mobile' => 'required|regex:/^[0-9\s\-\+\(\)]+$/|min:4|max:15',
            'image' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);
        $messages = [
            'mobile.max' => 'The mobile must not be greater than 15 digits.',
            'mobile.min' => 'The mobile must not be less than 4 digits.',
            'image.max' => 'The image must not be greater than 5 MB.',
            'school_id'=> 'The schools field is required'
        ];
        $validator->setCustomMessages($messages);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'fcm_id' => $request->fcm_id,
                'email' => $request->email,
                'dob' => Carbon::createFromFormat('d-m-Y', $request->dob)->format('Y-m-d'),
                'mobile' => $request->mobile,
                'current_address' => $request->current_address,
                'permanent_address' => $request->permanent_address,
                'gender' => $request->gender,
                'email_verified_at' => null,
                'password' => Hash::make($request->password),
                'title' => (isset($request->title) ? $request->title : ''),
                'language' => (isset($request->language) ? $request->language : ''),
                'type' => 'Principal',
                'hear_about_us' => (isset($request->hear_about_us) ? $request->hear_about_us : ''),
            ]);
            $principalPermissions = [
                'medium-list',
                'section-list',

                'class-list',

                'subject-list',

                'teacher-list',
                'teacher-create',
                'teacher-edit',
                'teacher-delete',

                'class-teacher-list',
                'class-teacher-create',
                'class-teacher-edit',
                'class-teacher-delete',

                'parents-list',
                'parents-create',
                'parents-edit',
                'parents-delete',

                'session-year-list',
                'session-year-create',
                'session-year-edit',
                'session-year-delete',

                'student-list',
                'student-create',
                'student-edit',
                'student-delete',

                'category-list',
                'category-create',
                'category-edit',
                'category-delete',

                'subject-teacher-list',
                'subject-teacher-create',
                'subject-teacher-edit',
                'subject-teacher-delete',

                'timetable-list',
                'timetable-create',
                'timetable-edit',
                'timetable-delete',

                'attendance-list',

                'holiday-list',
                'holiday-create',
                'holiday-edit',
                'holiday-delete',

                'announcement-list',
                'announcement-create',
                'announcement-edit',
                'announcement-delete',

                'slider-list',
                'slider-create',
                'slider-edit',
                'slider-delete',

                'class-timetable',
                'teacher-timetable',
                'student-assignment',
                'subject-lesson',
                'class-attendance',

                'exam-create',
                'exam-list',
                'exam-edit',
                'exam-delete',
                'exam-timetable-create',
                'grade-create',

                'setting-create',
                'fcm-setting-create',

                'assignment-submission',

                'email-setting-create',
                'privacy-policy',
                'terms-condition',
                'contact-us',
                'about-us',

                'student-reset-password',
                'reset-password-list',
                'student-change-password',

                'promote-student-list',
                'promote-student-create',
                'promote-student-edit',
                'promote-student-delete',

                'assign-class-to-new-student',

                'language-list',
                'language-create',
                'language-edit',
                'language-delete',

                'update-admin-profile',

                'fees-type',
                'fees-classes',
                'fees-paid',
                'fees-config',

            ];

            $user->givePermissionTo($principalPermissions);
            $user->assignRole('Principal');
            $principal = new Principal();
            $principal->id = Utility::getUUID();
            $principal->user_id = $user->id;
            $principal->qualification = $request->qualification ?? '';
            $principal->save();

            $school = Schools::where('id', $request->school_id)->first();
            if($school){
                $school->principal_id = $principal->user_id;
                $school->save();
            }

            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (\Exception $exception) {
            Log::error($exception);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $exception
            );
        }

        return response()->json($response);

    }

    public function get()
    {
        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'ASC';
        $search = $_GET['search'] ?? '';
        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        if($search){
            $response = User::where(function ($query) use ($search){
                $query->where('first_name', 'LIKE', "%$search%");
                $query->orWhere('last_name', 'LIKE', "%$search%");
            })->where('type', 'Principal')->with('principal.schools');
        }else{
            $response = User::where('type', 'Principal')->with('principal.schools');
        }
        $total = $response->count();
        $bulkData = array();
        $bulkData['total'] = $total;

        $response->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $response->get();
        $rows = array();
        $tempRow = array();

        foreach ($res as $row) {
            $operate = '';
            $delete = '';
            $tempRow['school_name'] = '';
            $tempRow['school_id'] = '';

            if($row->principal->schools){
                $tempRow['school_name'] = $row->principal->schools->school_name;
                $tempRow['school_id'] = $row->principal->schools->id;
            }
            $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon unlinkdata" data-id=' . $row->id. ' data-school_id=' .  $tempRow['school_id'] . ' ' . ' data-user_id=' . $row->id . ' data-url=' . route('principal.unlink',[$row->id, $tempRow['school_id']]) . ' title="Delete"><i class="fa fa-unlink"></i></a>';
            $delete .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id=' . $row->id. ' data-school_id=' .  $tempRow['school_id'] . ' ' . ' data-user_id=' . $row->id . ' data-url=' . route('principal.remove',$row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['user_id'] = $row->id;
            $tempRow['first_name'] = $row->first_name;
            $tempRow['last_name'] = $row->last_name;
            $tempRow['gender'] = $row->gender;
            $tempRow['mobile'] = $row->mobile;
            $tempRow['email'] = $row->email;
            $tempRow['dob'] = $row->dob;

            $tempRow['delete'] = $delete;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);

    }
}