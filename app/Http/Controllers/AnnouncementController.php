<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Models\Announcement;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\File;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\Settings;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AnnouncementController extends Controller
{

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        if (!Auth::user()->can('announcement-list')) {
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
            })
                ->with('class.medium', 'section')->get();
            $schools = Schools::where('principal_id',$principal->user_id)->get();

        }else{
            $class_section = ClassSection::whereHas('class')->SubjectTeacher()->with('class.medium', 'section')->get();
            $schools = Schools::orderBy('school_name','ASC')->get();

        }
        return view('announcement.index', compact('class_section','schools'));
    }

    public function getAssignData(Request $request) {
        $data = $request->data;
        $class_id = $request->class_id;
        if ($data == 'class_section' && $class_id != '') {
            $info = ClassSubject::where('class_id', $class_id)->with('subject')->get();
        } elseif ($data == 'class') {
            $info = ClassSchool::get();
        } else {
            $info = '';
        }
        return response()->json($info);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        if (!Auth::user()->can('announcement-create')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:100',
            'description' => 'required|max:500',
            'school_id' => 'required',
            'to_user' => 'required',
            'file.*' => 'mimes:jpeg,png|max:5120',
        ]);
            $messages = [
                'title.required' => 'The Title field is required.',
                'title.max' => 'The Title must not exceed 100 characters.',
                'description.required' => 'The Description field is required.',
                'description.max' => 'The Description must not exceed 500 characters.',
                'school_id.required' => 'The School field is required.',
                'to_user.required' => 'The User Type field is required.',
                'file.*.mimes' => 'Each file must be a JPEG or PNG image.',
                'file.*.max' => 'The image must not be greater than 5 MB.',
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
            $user = array();
            $data = getSettings('session_year');
            if (!empty($request->get_data)) {
                $getdata = count($request->get_data);
            } else {
                $getdata = 1;
            }
            $school_id = null;
            $title = null;
            $body = null;
            if($request->school_id != 'ALL_SCHOOL'){
                $school_id = $request->school_id;
                $title = 'New announcement from ' .Schools::where('id',$school_id)->first()->school_name;
            }
            for ($i = 0; $i < $getdata; $i++) {
                $announcement = new Announcement();
                $announcement->title = $request->title;
                $announcement->description = $request->description;
                if($request->school_id != 'ALL_SCHOOL'){
                    $announcement->school_id = $request->school_id;
                }
                if($request->to_user != 'ALL'){
                    $announcement->to_user = $request->to_user;
                }
                $announcement->session_year_id = $data['session_year'];
                if (!empty($request->set_data)) {
                    if ($request->set_data == 'class_section') {
                        $teacher_id = Auth::user()->id;
                        $subject_teacher_id = SubjectTeacher::select('id')->where(['class_section_id' => $request->class_section_id,'teacher_id' => $teacher_id ,'subject_id' => $request->get_data[$i]])->get()->pluck('id');
                        $subject_name = SubjectTeacher::where(['teacher_id' => $teacher_id,'class_section_id' => $request->class_section_id,'subject_id' => $request->get_data[$i]])->with('subject')->get();
                        if(count($subject_name)){
                            if (count($subject_teacher_id) != 0) {
                                for ($j = 0; $j < count($subject_teacher_id); $j++) {
                                    $subject_teacher = SubjectTeacher::find($subject_teacher_id[$j]);
                                    $announcement->table()->associate($subject_teacher);
                                    $user = Students::select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id');
                                }
                            }
                            $title = 'New announcement in ' . $subject_name[0]->subject->name;
                            $body = $request->title;
                        }
                        else{
                            $response = array(
                                'error' => true,
                                'message' => trans('no_data_found')
                            );
                            return response()->json($response);
                        }
                    }
                    if ($request->set_data == 'class') {
                        $class = ClassSchool::find($request->get_data[$i]);
                        $announcement->table()->associate($class);
                        $get_class = ClassSection::select('id')->where('class_id', $request->get_data[$i])->get()->pluck('id');
                        $user = Students::select('user_id')->where('class_section_id', $get_class[$i])->get()->pluck('user_id');
                        $title = $request->title;
                        $body = $request->description;
                    }
                    if ($request->set_data == 'general') {
                        $announcement->table_id = null;
                        $announcement->table_type = "";
                        $user = Students::select('user_id')->get()->pluck('user_id');
                        $title = 'Noticeboard updated';
                        $body = $request->title;
                    }
                }
                $type = $request->set_data;
                $announcement->save();
                if ($request->hasFile('file')) {
                    foreach ($request->file as $file_upload) {
                        $file = new File();
                        $file->file_name = $file_upload->getClientOriginalName();
                        $file->type = 1;
                        $file->file_url = $file_upload->store('announcement', 'public');
                        $file->modal()->associate($announcement);
                        $file->save();
                    }
                }
                if($request->school_id != 'ALL_SCHOOL'){
                    if($request->to_user == 'ALL'){
                        $users = UserSchools::where('school_id', $request->school_id)->pluck('user_id')->toArray();
                    }else{
                        $users = UserSchools::whereHas('user',function ($query) use ($request){
                            $query->where('type',$request->to_user);
                        })->where('school_id', $request->school_id)->pluck('user_id')->toArray();
                    }
                }else{
                    $users = UserSchools::pluck('user_id')->toArray();
                }
                $this->notificationSender->sendNotification(
                    "Announcement",
                    $announcement->title,
                    array_unique($users), "ANNOUNCEMENT", $announcement);
                $notificationLogArray =[];
                foreach ($users as $userId) {
                    $notificationLogArray [] = [
                        "id" => Utility::getUUID(),
                        "user_id" => $userId,
                        "from_user_id" => Auth::user()->id,
                        "message" => $announcement->description,
                        "type" => $type,
                        "title" => $announcement->title,
                        'created_at' => Carbon::now()
                    ];
                }
                $this->notificationSender->insertLogs($notificationLogArray);
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (\Throwable $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function update(Request $request) {
        if (!Auth::user()->can('announcement-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $request->validate([
            'title' => 'required'
        ]);
        try {
            $user = array();
            $data = getSettings('session_year');
            if(Auth::user()->teacher){
                $teacher_id = Auth::user()->id;
            }
            $announcement = Announcement::find($request->id);
            $announcement->title = $request->title;
            $announcement->description = $request->description;
            $announcement->session_year_id = $data['session_year'];
            $title ='';
            $body = '';
            if (!empty($request->set_data)) {
                if ($request->set_data == 'class_section') {
                    $subject_teacher_id = SubjectTeacher::select('id')->where(['class_section_id' => $request->class_section_id, 'subject_id' => $request->get_data,'teacher_id'=>$teacher_id])->get()->pluck('id');
                    $subject_name = SubjectTeacher::where(['class_section_id' => $request->class_section_id, 'subject_id' => $request->get_data,'teacher_id'=>$teacher_id])->with('subject')->get();
                    if (count($subject_teacher_id) != 0) {
                        for ($j = 0; $j < count($subject_teacher_id); $j++) {
                            $subject_teacher = SubjectTeacher::find($subject_teacher_id[$j]);
                            $announcement->table()->associate($subject_teacher);
                            $user = Students::select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id');
                        }
                        $title = 'Update announcement in ' . $subject_name[0]->subject->name;
                        $body = $request->title;
                    }
                }
                if ($request->set_data == 'class') {
                    $class = ClassSchool::find($request->get_data);
                    $announcement->table()->associate($class);
                    $get_class = ClassSection::select('id')->where('class_id', $request->get_data)->get()->pluck('id');
                    $user = Students::select('user_id')->where('class_section_id', $get_class)->get()->pluck('user_id');
                    $title = $request->title;
                    $body = $request->description;
                }
                if ($request->set_data == 'general') {
                    $announcement->table_id = null;
                    $announcement->table_type = "";
                    $user = Students::select('user_id')->get()->pluck('user_id');
                    $title = 'Noticeboard updated';
                    $body = $request->title;
                }
            }
            $type = $request->set_data;
            $announcement->save();
            send_notification($user, $title, $body, $type);
            if ($request->hasFile('file')) {
                foreach ($request->file as $file_upload) {
                    $file = new File();
                    $file->file_name = $file_upload->getClientOriginalName();
                    $file->type = 1;
                    $file->file_url = $file_upload->store('announcement', 'public');
                    $file->modal()->associate($announcement);
                    $file->save();
                }
            }
            $response = [
                'error' => false,
                'message' => trans('data_update_successfully'),
            ];
        } catch (Throwable $e) {
            $response = [
                'error' => true,
                'message' => trans('error_occurred'),
            ];
        }
        return response()->json($response);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show() {
        if (!Auth::user()->can('announcement-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        // $announcement=Announcement::get();
        // return view('announcement.list',compact('announcement'));
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
        if(Auth::user()->type == 'Principal'){
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id',$userId)->first();
            $schools = Schools::where('principal_id',$principal->user_id)->pluck('id')->toArray();
            $sql = Announcement::whereIn('school_id',$schools)->with('table', 'file');
        }else{
            $sql = Announcement::with('table', 'file');
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('title', 'LIKE', "%$search%")
                ->orwhere('description', 'LIKE', "%$search%");
        }
        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        $user = Auth::user();
        foreach ($res as $row) {
            $operate = '';
            if ($user->hasRole('Super Admin') && $row->table_type == "") {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center editdata" data-id="' . $row->id . '"  title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center deletedata" data-id="' . $row->id . '" data-url="' . url('announcement', $row->id) . '" title="Delete"><i class="fa fa-trash"></i></a>';
            }
            elseif ($user->hasRole('Principal') && $row->table_type == "") {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center editdata" data-id="' . $row->id . '"  title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center deletedata" data-id="' . $row->id . '" data-url="' . url('announcement', $row->id) . '" title="Delete"><i class="fa fa-trash"></i></a>';
            }
            elseif ($user->hasRole('Teacher') && $row->table_type == "App\\Models\\SubjectTeacher") {
                $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id="' . $row->id . '"  title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
                $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id="' . $row->id . '" data-url="' . url('announcement', $row->id) . '" title="Delete"><i class="fa fa-trash"></i></a>';
            }

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['title'] = $row->title;
            $tempRow['description'] = $row->description;
            if ($row->school) {
                $tempRow['school'] = $row->school->school_name;
            }else{
                $tempRow['school'] = 'All Schools';
            }
            $tempRow['type'] = $row->table_type;
            if ($tempRow['type'] == "App\\Models\\ClassSection") {
                $assign = 'class_section';
                $class = $row->table->class->name . ' - ' . $row->table->section->name;
                $class1 = $class;
            }
            if ($tempRow['type'] == "App\\Models\\ClassSchool") {
                $assign = 'class';
                $class = $row->table->name;
                $class1 = $class;
            }
            if ($tempRow['type'] == "App\\Models\\SubjectTeacher") {
                $assign = 'Subject';
                $class = $row->table;
                $class1 = $row->table->class_section->class->name . '-' . $row->table->class_section->section->name . '  ' . $row->table->subject->name;
            }
            if ($tempRow['type'] == "") {
                $assign = 'general';
                $class = trans("general");
                $class1 = $class;
            }
            $tempRow['assign'] = $assign;
            $tempRow['assign_to'] = $class;
            $tempRow['assignto'] = $class1;
            $tempRow['get_data'] = $row->table_id;
            $tempRow['file'] = $row->file;
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
    public function destroy($id) {
        if (!Auth::user()->can('announcement-delete')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {
            Announcement::find($id)->delete();
            $response = array(
                'error' => false,
                'message' => trans('data_delete_successfully')
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
}
