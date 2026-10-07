<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Mail\SampleMail;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\Section;
use App\Models\UserSchools;
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

class SchoolsController extends Controller
{


    public function schoolsView(Request $request)
    {
        $principals = User::whereHas('principal',function ($query){
            $query->whereDoesntHave('schools');
        })->get();

        return view('schools.index', compact('principals'));
    }

    public function schoolsList(Request $request)
    {
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');
        $school_id = request('id');

        if (Auth::user()->type == 'Principal') {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            $schools = Schools::where('principal_id', $principal->user_id)->pluck('id')->toArray();

            if ($search) {
                $sql = Schools::where(function ($query) use ($search) {
                    $query->where('school_name', 'LIKE', "%$search%");
                    $query->orWhere('address', 'LIKE', "%$search%");
                    $query->orWhere('locality', 'LIKE', "%$search%");
                })->where('principal_id', $principal->user_id);
            } else {
                $sql = Schools::where('principal_id', $principal->user_id);
            }
            $total = $sql->count();

            $sql->orderBy('created_at', 'DESC')->skip($offset)->take($limit);
            $res = $sql->get();
        } else {
            $total = Schools::count();
            if ($search) {
                $res = Schools::where(function ($query) use ($search) {
                    $query->where('school_name', 'LIKE', "%$search%");
                    $query->orWhere('address', 'LIKE', "%$search%");
                    $query->orWhere('locality', 'LIKE', "%$search%");
                })
                    ->orderBy('name', 'asc')->skip($offset)->take($limit)->get();
            } else {
                $res = Schools::orderBy('created_at', 'DESC')->skip($offset)->take($limit)->get();
            }
        }


        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        foreach ($res as $row) {
            $operate = '';
            if (Auth::user()->hasRole('Principal')) {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' data-url=' . url('schools') . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
//                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id=' . $row->id . ' data-user_id=' . $row->user_id . ' data-url=' . url('schools', $row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';

            }

            if (Auth::user()->hasRole('Super Admin')) {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' data-url="' . url('update-school') . '" title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

            }
            $tempRow['id'] = $row->id;
            $tempRow['school_name'] = $row->school_name;
            $tempRow['address'] = $row->address;
            $tempRow['website'] = $row->website;
            $tempRow['locality'] = $row->locality;
            $tempRow['post_town'] = $row->post_town;
            $tempRow['post_code'] = $row->post_code;
            $tempRow['email'] = $row->email;
            $tempRow['phone'] = $row->phone;
            $tempRow['image'] = $row->image;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }


    public function index()
    {
        if (!Auth::user()->can('student-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if (Auth::user()->type == 'Principal') {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            $schools = Schools::where('principal_id', $principal->user_id)->pluck('id')->toArray();
            $class_section = ClassSection::SubjectTeacher()->whereHas('section', function ($query) use ($schools) {
                $query->whereHas('school');
                $query->whereIn('school_id', $schools);
            })->with('class', 'section')->get();
            $schools = null;
        } else {
            $class_section = [];
            $schools = Schools::all();
        }
        $category = Category::where('status', 1)->get();
        return view('students.details', compact('class_section', 'category', 'schools'));
    }

    public function update(Request $request,$school_id)
    {
        $school_id = $request->edit_school_id;
        if (!Auth::user()->can('student-create') || !Auth::user()->can('student-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
            'school_name' => 'required|max:100',
            'address' => 'required|max:100',
            'post_code' => 'required|min:4|max:8',
            'email' => 'required|email|regex:/^[^@]+@[^@]+\.[^@]+$/|unique:schools,email,' . $request->edit_school_id,
            'phone' => 'nullable|regex:/^[0-9\s\-\+\(\)]+$/|max:20|unique:schools,phone,' . $request->edit_school_id,
            'website' => 'nullable|max:100',
            'post_town' => 'nullable|max:20',
            'locality' => 'nullable|max:20',
        ]);
        $messages = [
            'image.max' => 'The image must not be greater than 5 MB.',
        ];
        $validator->setCustomMessages($messages);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        $school = Schools::where('id', $school_id)->first();
        try {
            $school->school_name = $request->school_name;
            $school->email = $request->email;
            $school->post_town = $request->post_town;
            $school->post_code = $request->post_code;
            $school->locality = $request->locality;
            $school->phone = $request->phone;
            $school->address = $request->address;
            $school->website = $request->website;
            if ($request->hasFile('image')) {
                if (Storage::disk('public')->exists($school->getRawOriginal('image'))) {
                    Storage::disk('public')->delete($school->getRawOriginal('image'));
                }

                $image = $request->file('image');

                // made file name with combination of current time
                $file_name = time() . '-' . $image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'schools/' . $file_name;
                if ($image->getClientMimeType() != 'image/svg+xml' && $image->getClientMimeType() != 'image/svg') {
                    //resized image
                    resizeImage($image);
                }
                //stored image to storage/public/subjects folder
                $destinationPath = storage_path('app/public/schools');
                $image->move($destinationPath, $file_name);
                //saved file path to database
                $school->image = $file_path;
            }
            $school->save();
            $response = $school->refresh();
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
            'country' => 'required|max:20',
            'school_name' => 'required|max:100',
            'address' => 'required|max:100',
            'post_code' => 'required|min:4|max:8',
            'email' => 'required|email|unique:schools,email|regex:/^[^@]+@[^@]+\.[^@]+$/',
            'phone' => 'nullable|regex:/^[0-9\s\-\+\(\)]+$/|max:20|unique:schools,phone',
            'website' => 'nullable|max:100',
            'post_town' => 'nullable|max:20',
            'locality' => 'nullable|max:20',
        ]);
        $messages = [
            'image.max' => 'The image must not be greater than 5 MB.',
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

            $school = new Schools();
            $school->school_name = $request->school_name;
            $school->country_id = $request->country;

            $school->email = $request->email;
            $school->website = $request->website;
            $school->principal_id = $request->principal_id;
            $school->user_id = $request->user()->id;
            $school->address = $request->address;
            $image = '';
            if (!empty($request->image)) {
                $image = $request->image;
                $file_name = time() . '-' . $image->getClientOriginalName();
                $file_path = 'schools/' . $file_name;
                $destinationPath = storage_path('app/public/schools');
                $image->move($destinationPath, $file_name);

                $image = $file_path;
            }
            $school->image = $image;
            $school->post_code = $request->post_code;
            $school->state = $request->state;
            $school->post_town = $request->post_town;
            $school->phone = $request->phone;
            $school->locality = $request->locality;
            $school->save();
            $school = $school->refresh();



            foreach (Section::default_sections as $default_section){
                $section = new Section();
                $section->name = $default_section;
                $section->school_id = $school->id;
                $section->save();
            }

            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
            return response()->json($response);
        } catch (Exception $exception) {
            Log::error($exception);
            $response = [
                'error' => true,
                'message' => trans('error_occurred')
            ];
            return response()->json($response);
        }

    }


}
