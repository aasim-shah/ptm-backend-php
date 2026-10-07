<?php

namespace App\Http\Controllers;

use App\Models\Principal;
use App\Models\Schools;
use App\Models\UserSchools;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\User;
use App\Models\Teacher;
use App\Models\ClassSection;
use Illuminate\Http\Request;
use App\Models\SubjectTeacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeacherController extends Controller
{

    public function details_view($id)
    {
        $user = User::where('type', 'teacher')->where('id', $id)->with('teacher')->first();
        return view('users.details')
            ->with('type', "teacher")
            ->with('user', $user);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::user()->can('teacher-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if (Auth::user()->type == 'Principal') {
            $schools = null;
        } else {
            $schools = Schools::all();
        }
        return view('teacher.index')->with('schools', $schools);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('teacher-create') || !Auth::user()->can('teacher-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'mobile' => 'required|digits:10',
            'dob' => 'required|date',
            'qualification' => 'nullable',
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
            // check if email exists in deleted_at records
            $check_user = User::where('email', $request->email)->onlyTrashed();
            if ($check_user->count()) {
                $user_exists = $check_user->first();
                DB::table('users')->where('id', $user_exists->id)->update(['deleted_at' => null]);
                $user = User::findOrFail($user_exists->id);
                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    // made file name with combination of current time
                    $file_name = time() . '-' . $image->getClientOriginalName();
                    //made file path to store in database
                    $file_path = 'teachers/' . $file_name;
                    //resized image
                    resizeImage($image);
                    //stored image to storage/public/teachers folder
                    $destinationPath = storage_path('app/public/teachers');
                    $image->move($destinationPath, $file_name);

                    $user->image = $file_path;
                } else {
                    $user->image = "";
                }
                $teacher_plain_text_password = str_replace('-', '', date('d-m-Y', strtotime($request->dob)));
                $user->password = Hash::make($teacher_plain_text_password);

                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->gender = $request->gender;
                $user->current_address = $request->current_address;
                $user->permanent_address = $request->permanent_address;
                $user->email = $request->email;
                $user->mobile = $request->mobile;
                $user->dob = date('Y-m-d', strtotime($request->dob));
                $user->update();

                $check_teacher = DB::table('teachers')->where('user_id', $user->id)->whereNotNull('deleted_at');
                if ($check_teacher->count()) {
                    $teacher_exists = $check_teacher->first();
                    DB::table('teachers')->where('id', $teacher_exists->id)->update(['deleted_at' => null]);
                    $teacher = Teacher::findOrFail($teacher_exists->id);
                    $teacher->user_id = $user->id;
                    $teacher->qualification = $request->qualification;
                    $teacher->update();
                }
                if ($request->grant_permission) {
                    $user->givePermissionTo([
                        'student-create',
                        'student-list',
                        'student-edit',
                        'student-delete',
                        'parents-create',
                        'parents-list',
                        'parents-edit'
                    ]);
                } else {
                    $user->revokePermissionTo([
                        'student-create',
                        'student-list',
                        'student-edit',
                        'student-delete',
                        'parents-create',
                        'parents-list',
                        'parents-edit'
                    ]);
                }
                $user->assignRole([2]);
                $school_name = getSettings('school_name');
                $data = [
                    'subject' => 'Welcome to ' . $school_name['school_name'],
                    'name' => $request->first_name,
                    'email' => $request->email,
                    'password' => $teacher_plain_text_password,
                    'school_name' => $school_name['school_name']
                ];

                Mail::send('teacher.email', $data, function ($message) use ($data) {
                    $message->to($data['email'])->subject($data['subject']);
                });
            } else {
                $user = new User();
                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    // made file name with combination of current time
                    $file_name = time() . '-' . $image->getClientOriginalName();
                    //made file path to store in database
                    $file_path = 'teachers/' . $file_name;
                    //resized image
                    resizeImage($image);
                    //stored image to storage/public/teachers folder
                    $destinationPath = storage_path('app/public/teachers');
                    $image->move($destinationPath, $file_name);

                    $user->image = $file_path;
                } else {
                    $user->image = "";
                }
                $teacher_plain_text_password = str_replace('-', '', date('d-m-Y', strtotime($request->dob)));
                $user->password = Hash::make($teacher_plain_text_password);

                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->gender = $request->gender;
                $user->current_address = $request->current_address;
                $user->permanent_address = $request->permanent_address;
                $user->email = $request->email;
                $user->mobile = $request->mobile;
                $user->dob = date('Y-m-d', strtotime($request->dob));
                $user->save();


                $teacher = new Teacher();
                $teacher->user_id = $user->id;
                $teacher->qualification = $request->qualification;
                $teacher->save();
                if ($request->grant_permission) {
                    $user->givePermissionTo([
                        'student-create',
                        'student-list',
                        'student-edit',
                        'student-delete',
                        'parents-create',
                        'parents-list',
                        'parents-edit'
                    ]);
                } else {
                    $user->revokePermissionTo([
                        'student-create',
                        'student-list',
                        'student-edit',
                        'student-delete',
                        'parents-create',
                        'parents-list',
                        'parents-edit'
                    ]);
                }
                $user->assignRole([2]);
                $school_name = getSettings('school_name');
                $data = [
                    'subject' => 'Welcome to ' . $school_name['school_name'],
                    'name' => $request->first_name,
                    'email' => $request->email,
                    'password' => $teacher_plain_text_password,
                    'school_name' => $school_name['school_name']
                ];

                Mail::send('teacher.email', $data, function ($message) use ($data) {
                    $message->to($data['email'])->subject($data['subject']);
                });
            }
            $response = [
                'error' => false,
                'message' => trans('data_store_successfully')
            ];
        } catch (Throwable $e) {
            Log::error($e);
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
        Log::info($request);
        if (!Auth::user()->can('teacher-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'ASC';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        if (Auth::user()->type == 'Principal') {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            $schools = Schools::where('principal_id', $principal->user_id)->pluck('id')->toArray();
            $class_teacher_ids = ClassSection::SubjectTeacher()->whereHas('section', function ($query) use ($schools) {
                $query->whereHas('school');
                $query->whereIn('school_id', $schools);
            })->pluck('class_teacher_id')->toArray();

            $sql = User::whereHas('teacher', function ($query) use ($class_teacher_ids){
                $query->whereIn('user_id', $class_teacher_ids)->with(['class_sections' => function ($query) {
                    $query->with('class');
                }]);
            })->with('teacher');
        } else {

            if ($request->school_id) {
                $class_teacher_ids = UserSchools::where('school_id',$request->school_id)->get()->pluck('user_id');
                $sql = User::whereHas('teacher', function ($query) use ($class_teacher_ids) {
                    $query->whereIn('user_id', $class_teacher_ids)->with(['class_sections' => function ($query) {
                        $query->with('class');
                    }]);
                })->with('teacher');
            } else {
                $sql = User::whereHas('teacher', function ($query) {
                    $query->with(['class_sections' => function ($query) {
                        $query->with('class');
                    }]);
                })->with('teacher');
                Log::info($sql->get());
            }
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orwhere('last_name', 'LIKE', "%$search%");
            });
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
//            $operate = '';

//            $operate = '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' data-url=' . url('teachers') . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
//            $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center deletedata" data-id=' . $row->id . ' data-user_id=' . $row->id . ' data-url=' . url('teachers', $row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';

            $operate = '';
            if (Auth::user()->can('teacher-edit')) {
                $operate .= '<a href="' . route('teacher_details_view', $row->id) . '" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center" data-id=' . $row->id . '  title="View" ><i class="fa fa-eye"></i></a>&nbsp;&nbsp;';
            }

            if (Auth::user()->can('teacher-delete')) {
                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center deletedata" data-id=' . $row->id . ' data-user_id=' . $row->id . ' data-url=' . url('teachers', $row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';
            }
            $data = getSettings('date_formate');

//            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['user_id'] = $row->id;
            $tempRow['first_name'] = $row->first_name;
            $tempRow['last_name'] = $row->last_name;
            $classNames = null;

            foreach ($row->teacher->class_sections as $class_sections) {
                if ($class_sections->class !=null) {
                    if ($classNames) {
                        $classNames = $classNames . " , " . $class_sections->class->name;
                    } else {
                        $classNames = $class_sections->class->name;
                    }
                }
            }
            $tempRow['class'] = $classNames;
            $tempRow['gender'] = $row->gender;
            $tempRow['current_address'] = $row->current_address;
            $tempRow['permanent_address'] = $row->permanent_address;
            $tempRow['email'] = $row->email;
            //$tempRow['dob'] = $row->dob ? Carbon::createFromFormat('d-m-Y', $row->dob)->format('Y-m-d') : '-';
            $tempRow['mobile'] = $row->mobile;
            $tempRow['image'] = $row->image;
            $tempRow['qualification'] = $row->teacher->qualification;
//            $call = '<a href="' . route('teacher_details_view', $row->id) . '" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center" data-id=' . $row->id . '  title="Edit" ><i class="fa fa-eye"></i></a>&nbsp;&nbsp;';
//            $tempRow['callPage'] = $call;
            if ($row->can('student-create', 'student-list', 'student-edit', 'parents-create', 'parents-list', 'parents-edit')) {
                $tempRow['has_student_permissions'] = 1;
            } else {
                $tempRow['has_student_permissions'] = 0;
            }

            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $teacher = Teacher::find($id);
        return response($teacher);
    }


    public function update(Request $request)
    {
        if (!Auth::user()->can('teacher-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'email' => 'required|email|unique:users,email,' . $request->user_id,
            'mobile' => 'required|digits:10',
            'dob' => 'required|date',
            'qualification' => 'nullable',
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
            $user = User::find($request->user_id);
            if ($request->hasFile('image')) {
                if (Storage::disk('public')->exists($user->getRawOriginal('image'))) {
                    Storage::disk('public')->delete($user->getRawOriginal('image'));
                }
                $image = $request->file('image');
                // made file name with combination of current time
                $file_name = time() . '-' . $image->getClientOriginalName();
                //made file path to store in database
                $file_path = 'teachers/' . $file_name;
                //resized image
                resizeImage($image);
                //stored image to storage/public/teachers folder
                $destinationPath = storage_path('app/public/teachers');
                $image->move($destinationPath, $file_name);

                $user->image = $file_path;
            }
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->gender = $request->gender;
            $user->current_address = $request->current_address;
            $user->permanent_address = $request->permanent_address;
            $user->email = $request->email;
            $user->mobile = $request->mobile;
            $user->dob = date('Y-m-d', strtotime($request->dob));
            $user->save();

            $teacher = Teacher::find($request->id);
            $teacher->user_id = $user->id;
            $teacher->qualification = $request->qualification;
            $teacher->save();

            if ($request->edit_grant_permission) {
                $user->givePermissionTo([
                    'student-create',
                    'student-list',
                    'student-edit',
                    'student-delete',
                    'parents-create',
                    'parents-list',
                    'parents-edit'
                ]);
            } else {
                $user->revokePermissionTo([
                    'student-create',
                    'student-list',
                    'student-edit',
                    'student-delete',
                    'parents-create',
                    'parents-list',
                    'parents-edit'
                ]);
            }

            $response = [
                'error' => false,
                'message' => trans('data_update_successfully')
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
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('teacher-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {
            $teacher_id = Teacher::where('user_id', $id)->with('user')->get()->first();

            //check wheather the teacher exists in other table
            $subject_teacher = SubjectTeacher::where('teacher_id', $teacher_id->id)->count();
            $class_section = ClassSection::where('class_teacher_id', $teacher_id->id)->count();
            if ($subject_teacher || $class_section) {
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );

            } else {
                $class_section_id = ClassSection::where('class_teacher_id', $teacher_id->id)->pluck('id')->first();
                if ($class_section_id) {
                    $class_teacher = ClassSection::find($class_section_id);
                    $class_teacher->class_teacher_id = null;
                    $class_teacher->save();
                    $teacher_id->user->revokePermissionTo('class-teacher');
                }
                $user = User::find($id);
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->delete();

                Teacher::where('user_id', $id)->delete();
                $response = [
                    'error' => false,
                    'message' => trans('data_delete_successfully')
                ];
            }
        } catch (Throwable $e) {
            Log::info('Delete Teacher '.$e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
}
