<?php

namespace App\Http\Controllers\V2;

use App\Exports\AnnouncementsExport;
use App\Exports\ParentsExport;
use App\Exports\PrincipalsExport;
use App\Exports\SchoolsExport;
use App\Exports\StudentsExport;
use App\Exports\TeachersExport;
use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\Parents;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\Students;
use App\Models\User;
use App\Models\UserSchools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExcelController extends Controller
{

    public function __construct()
    {
    }

    public function exportTeachers(Request $request)
    {
        $school_id = $request->school_id;
        $user = $request->user();

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
            }
        }
        $res = $sql->get();
        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new TeachersExport($res), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

    public function exportParents(Request $request)
    {
        $school_id = $request->school_id;
        $user = $request->user();

        if (Auth::user()->type == 'Principal') {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            $schools = Schools::where('principal_id', $principal->user_id)->pluck('id')->toArray();
            $schoolParents = UserSchools::whereIn('school_id', $schools)->pluck('user_id')->toArray();

            $sql = Parents::with('schools')->whereIn('user_id', $schoolParents)->with('user:id,current_address,permanent_address');

        } else {
            if ($request->school_id) {
                $schoolParents = UserSchools::where('school_id', $request->school_id)->pluck('user_id')->toArray();
                $sql = Parents::with('schools')->whereIn('user_id', $schoolParents)->with('user:id,current_address,permanent_address');
            } else {
                $sql = Parents::with('schools')->with('user:id,current_address,permanent_address');
            }
        }
        $res = $sql->get();
        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new ParentsExport($res), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

    public function exportStudents(Request $request)
    {
        $school_id = $request->school_id;
        $class_id = $request->class_id;
        $search = request('search');

        if (Auth::user()->type == 'Principal') {

            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            $schools = Schools::where('principal_id', $principal->user_id)->pluck('id')->toArray();

            $class_section = ClassSection::SubjectTeacher()->whereHas('section', function ($query) use ($schools) {
                $query->whereHas('school');
                $query->whereIn('school_id', $schools);
            })->pluck('id')->toArray();
            $sql = Students::whereHas('user', function ($query) {
                $query->whereNull('deleted_at');
            })->whereIn('class_section_id', $class_section)->where('class_status','ACCEPTED')->with('user', 'class_section', 'category', 'father', 'mother', 'guardian')->ofTeacher();
        } else {
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

        $sql->when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('user_id', 'LIKE', "%$search%");
                $query->orWhere('roll_number', 'LIKE', "%$search%");
                $query->orWhereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%$search%")
                        ->orwhere('last_name', 'LIKE', "%$search%")
                        ->orwhere('email', 'LIKE', "%$search%")
                        ->orwhere('dob', 'LIKE', "%$search%");
                });
                $query->orWhereHas('category', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                });
            });
        })->when(request('class_id') != null, function ($query) {
            $classId = request('class_id');
            $query->where(function ($query) use ($classId) {
                $query->where('class_section_id', $classId);
            });
        });

        $res = $sql->get();

        $rows = array();
        $tempRow = array();
        $no = 1;
        $data = getSettings('date_formate');
        foreach ($res as $row) {

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['user_id'] = $row->user_id;
            $tempRow['first_name'] = $row->user->first_name;
            $tempRow['last_name'] = $row->user->last_name;
            $tempRow['email'] = $row->user->email;
            $tempRow['dob'] = date($data['date_formate'], strtotime($row->user->dob));
            $tempRow['mobile'] = $row->user->mobile;
            $tempRow['image'] = $row->user->image;
            $tempRow['gender'] = $row->user->gender;
            $tempRow['image_link'] = $row->user->image;
            $tempRow['class_section_id'] = $row->class_section_id;
            $tempRow['class_section_name'] = $row->class_section ? $row->class_section->class->name . "-" . $row->class_section->section->name : '';
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
                $tempRow['father_image'] = $row->father->image;
                $tempRow['father_image_link'] = $row->father->image;
            }

            if (!empty($row->mother)) {
                //Mother Data
                $tempRow['mother_id'] = $row->mother->id;
                $tempRow['mother_email'] = $row->mother->email;
                $tempRow['mother_first_name'] = $row->mother->first_name;
                $tempRow['mother_last_name'] = $row->mother->last_name;
                $tempRow['mother_mobile'] = $row->mother->mobile;
                $tempRow['mother_dob'] = $row->mother->dob;
                $tempRow['mother_occupation'] = $row->mother->occupation;
                $tempRow['mother_image'] = $row->mother->image;
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

            $rows[] = $tempRow;
        }
        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new StudentsExport($rows), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

    public function exportAnnouncement(Request $request){
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
        $sql->orderBy($sort, $order);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        $user = Auth::user();
        foreach ($res as $row) {

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['title'] = $row->title;
            $tempRow['description'] = $row->description;
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
            $rows[] = $tempRow;
        }
        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new AnnouncementsExport($rows), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

    public function exportSchools(Request $request){
        $search = request('search');

        if (Auth::user()->type == 'Principal') {
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id', $userId)->first();
            if ($search) {
                $sql = Schools::where(function ($query) use ($search) {
                    $query->where('school_name', 'LIKE', "%$search%");
                    $query->orWhere('address', 'LIKE', "%$search%");
                    $query->orWhere('locality', 'LIKE', "%$search%");
                })->where('principal_id', $principal->user_id);
            } else {
                $sql = Schools::where('principal_id', $principal->user_id);
            }
            $res = $sql->get();
        } else {
            if ($search) {
                $res = Schools::where(function ($query) use ($search) {
                    $query->where('school_name', 'LIKE', "%$search%");
                    $query->orWhere('address', 'LIKE', "%$search%");
                    $query->orWhere('locality', 'LIKE', "%$search%");
                })->get();
            } else {
                $res = Schools::all();
            }
        }

        $rows = array();
        $tempRow = array();

        foreach ($res as $row) {
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
            $rows[] = $tempRow;
        }

        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new SchoolsExport($rows), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

    public function exportPrincipals(Request $request){
        $search = $request->search ?? null;
        if($search){
            $response = User::where(function ($query) use ($search){
                $query->where('first_name', 'LIKE', "%$search%");
                $query->orWhere('last_name', 'LIKE', "%$search%");
            })->where('type', 'Principal')->with('principal.schools');
        }else{
            $response = User::where('type', 'Principal')->with('principal.schools');
        }

        $res = $response->get();
        $rows = array();
        $tempRow = array();

        foreach ($res as $row) {

            $tempRow['school_name'] = '';
            $tempRow['school_id'] = '';

            if($row->principal->schools){
                $tempRow['school_name'] = $row->principal->schools->school_name;
                $tempRow['school_id'] = $row->principal->schools->id;
            }
            $tempRow['id'] = $row->id;
            $tempRow['user_id'] = $row->id;
            $tempRow['first_name'] = $row->first_name;
            $tempRow['last_name'] = $row->last_name;
            $tempRow['gender'] = $row->gender;
            $tempRow['mobile'] = $row->mobile;
            $tempRow['email'] = $row->email;
            $tempRow['dob'] = $row->dob;
            $rows[] = $tempRow;
        }
        $fileName = Utility::getUUID() . ".xlsx";
        Excel::store(new PrincipalsExport($rows), "excel/" . $fileName);
        $path = storage_path('app/excel/' . $fileName);
        return response()->download($path);
    }

}