<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Mediums;
use App\Models\Schools;
use App\Models\SchoolSubjects;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\Subject;
use App\Models\ClassSchool;
use App\Models\ClassSubject;
use App\Models\ExamMarks;
use App\Models\ExamTimetable;
use App\Models\StudentSubject;
use App\Models\SubjectTeacher;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
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
    public function index()
    {
        if (Auth::user()->type != 'Principal') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $school = principalSchoolOrFail(Auth::user()->id);

        $subjects = Subject::orderBy('id', 'DESC')->get();
        $mediums = Mediums::orderBy('id', 'DESC')->get();
        $classes = ClassSchool::where('school_id',$school->id)->get();
        return response(view('subject.index', compact('subjects', 'mediums','classes')));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'image' => 'max:2048',
        ])->setAttributeNames(
            ['bg_color' => 'Background Color'],
        );;

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        $schools = principalSchoolOrFail($user->id);
        try {
            $subject = Subject::where(['name' => $request->name, 'medium_id' => $request->medium_id, 'type' => $request->type])->whereNot('id',$id)->whereHas('schoolSubjects',function ($query) use ($schools){
                $query->where('school_id',$schools->id);
            })->count();
            if ($subject) {
                $response = array(
                    'error' => true,
                    'message' => trans('subject_already_exists')
                );
                return response()->json($response);
            } else {
                $validator = Validator::make($request->all(), [
                    'code' => 'nullable|unique:subjects,code,' . $id.',id,deleted_at,NULL'
                ]);
                if ($validator->fails()) {
                    $response = array(
                        'error' => true,
                        'message' => $validator->errors()->first()
                    );
                    return response()->json($response);
                }

                $image = $request->file('image');
                $file_path = null;
                if($image){
                    // made file name with combination of current time
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    //made file path to store in database
                    $file_path = 'subjects/' . $file_name;
                    if($image->getClientMimeType() != 'image/svg+xml' && $image->getClientMimeType() != 'image/svg'){
                        //resized image
                        resizeImage($image);
                    }
                    //stored image to storage/public/subjects folder
                    $destinationPath = storage_path('app/public/subjects');
                    $image->move($destinationPath, $file_name);
                }
                $subject = Subject::find($id);
                $subject->name = $request->name;
                $subject->image = $file_path;
                $subject->save();

                $response = array(
                    'error' => false,
                    'message' => trans('data_update_successfully'),
                );
            }
        } catch (\Throwable $e) {
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
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'medium_id' => 'numeric',
            'name' => 'required',
            'class_id'=>'required',
        ]);
        $messages = [
            'class_id' => 'The class field is required.',
        ];
        $validator->setCustomMessages($messages);
        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        $isSectionClass = null;
        if($request->class_id){
            $isSectionClass = ClassSchool::where('id',$request->class_id)->whereHas('sections',function ($query) use ($user){
                $query->where('class_teacher_id',$user->id);
            })->first();
        }
        $schools = principalSchoolOrFail($user->id);
        try {
            $subject = Subject::where(
                ['name' => $request->name])
                ->whereHas('schoolSubjects',function ($query) use ($schools){
                    $query->where('school_id',$schools->id);
                })
                ->count();
            if ($subject) {
                $response = array(
                    'error' => true,
                    'message' => trans('subject_already_exists')
                );
                return response()->json($response,400);
            } else {

                $subject = new Subject();
                $subject->medium_id = $request->medium_id ?? null;
                $subject->name = $request->name;
                $subject->principal_id = $user->id;
                $subject->save();
                $subject = $subject->refresh();

                if($classSection){
                    $classSubject = new ClassSubject();
                    $classSubject->subject_id = $subject->id;
                    $classSubject->class_id = $request->class_id ?? null;
                    $classSubject->type = "Compulsory";
                    $classSubject->save();
                }

                $isSchoolSubject = SchoolSubjects::where('school_id',$schools->id)->where('subject_id',$subject->id)->first();

                if(!$isSchoolSubject){
                    $isSchoolSubject = new SchoolSubjects();
                    $isSchoolSubject->id = Utility::getUUID();
                    $isSchoolSubject->school_id = $schools->id;
                    $isSchoolSubject->subject_id = $subject->id;
                    $isSchoolSubject->save();
                }
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                );
            }
        } catch (\Throwable $e) {
            Log::info($e);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($subject_id)
    {
        try {
        $canDelete = Subject::where('id',$subject_id)->first();

        $subjectTeacher = SubjectTeacher::where('subject_id', $subject_id)->first();
        $classSectionId = null;
        $classSection = null;
        $subjectStudents = [];
        if($subjectTeacher){
            $classSectionId = SubjectTeacher::where('subject_id', $subject_id)->first()->class_section_id;
            $classSection = ClassSection::where('id',$classSectionId)->first();
            StudentReportCards::where('subject_id',$subject_id)->where('class_id',$classSection->class_id)->delete();
            SubjectTeacher::where('subject_id',$subject_id)
                ->where('class_section_id',$classSection->id)
                ->where('teacher_id',Auth::user()->id)->delete();
            $subjectStudents = StudentSubject::where('subject_id',$subject_id)->where('class_section_id',$classSection->id)
                ->pluck('student_id')->toArray();

            StudentSubject::where('subject_id',$subject_id)
                ->where('class_section_id',$classSection->id)->delete();
            Attendance::where('class_section_id',$classSection->id)->where('subject_id',$subject_id)->delete();
            ClassSubject::where('subject_id',$subject_id)->where('class_id',$classSection->class_id)->delete();

        }


        foreach ($subjectStudents as $subjectStudent){
            $student = Students::where('user_id',$subjectStudent)->first();
            $message = "Subject ".$canDelete->name ." has been removed by the principal";
            $this->notificationSender->sendNotification(
                "Subject Removed",
                $message,
                [$student->mother_id,$student->father_id], "SUBJECT_REMOVED", $canDelete);
            $this->notificationSender->saveLogs($student->mother_id ?? $student->father_id,Auth::user()->id,$message,'SUBJECT_REMOVED','Subject removed by the principal');
        }
        
        Subject::where("id",$subject_id)->delete();
        } catch (\Throwable $e) {
            Log::info($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json([
            'error' => false,
            'status' => 200,
            'message' => 'Subject deleted successfully.',
            'data' => []
        ]);
    }

    public function show(Request $request)
    {
        $principalId = $request->user()->id;
        $class_id = $request->class_id;
        $schoolId = principalSchoolOrFail($principalId)->id;
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

        if ($class_id == null){
            $sql = Subject::with('medium');
        }else{
            $sql = Subject::with('medium')
                ->whereHas('class_subject',function ($query) use ($class_id){
                    $query->where('class_id',$class_id);
                });
        }
        $sql = $sql->whereHas('schoolSubjects', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        });

        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql = $sql->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%")
                    ->orWhere('code', 'LIKE', "%$search%")
                    ->orWhere('type', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();

        $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;

        foreach ($res as $row) {
            $operate = '<a href=' . route('subject.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a href=' . route('subject.destroy', $row->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" data-id=' . $row->id . ' title="Delete"><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['image'] = $row->image;
            $tempRow['created_at'] = $row->created_at->toDateString();
            $tempRow['updated_at'] = $row->updated_at->toDateString();
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
