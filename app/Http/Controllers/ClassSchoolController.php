<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClassSubjectCollection;
use App\Http\Resources\User;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ClassQuarter;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\ElectiveSubject;
use App\Models\ElectiveSubjectGroup;
use App\Models\ExamClass;
use App\Models\ExamResult;
use App\Models\FeesClass;
use App\Models\Lesson;
use App\Models\MainMeeting;
use App\Models\Mediums;
use App\Models\Meetings;
use App\Models\OnlineExam;
use App\Models\OnlineExamQuestion;
use App\Models\Schools;
use App\Models\Section;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\StudentSessions;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Timetable;
use App\Services\NotificationSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClassSchoolController extends Controller
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
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $school = Schools::where('principal_id',Auth::user()->id)->first();
        if(!$school){
            $response = array(
                'message' => "You don't have a school"
            );
            return redirect(route('home'))->withErrors($response);
        }
        $sections = Section::where('school_id', $school->id)->get();

        $classes = ClassSchool::orderBy('id', 'DESC')->with('medium', 'sections')->get();
        return response(view('class.index', compact('classes','sections')));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (Auth::user()->type != 'Principal') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'grade' => 'required|max:255',
        ]);
        $school = Schools::where('principal_id',Auth::user()->id)->first();
        if(!$school){
            $response = array(
                'message' => "You don't have a school"
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class = ClassSchool::where('name', $request->name)
            ->where('school_id', $school->id)
            ->where('grade', $request->grade)
            ->first();
        if($class){
            $response = array(
                'error' => true,
                'message' => "Failed to create class. Duplicate class detected",
                'data' => null
            );
            return response()->json($response,400);
        }

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $class = new ClassSchool();
            $class->name = $request->name;
            $class->school_id = $school->id;
            $class->grade = $request->grade;
            $class->save();
            $class_section = array();
            foreach ($request->section_id as $section_id) {
                $class_section[] = array(
                    'class_id' => $class->id,
                    'section_id' => $section_id
                );
            }
            ClassSection::insert($class_section);
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
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
        if (Auth::user()->type != 'Principal') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|regex:/^[A-Za-z0-9_]+$/',
            'grade' => 'required|regex:/^[A-Za-z0-9_]+$/',
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $class = ClassSchool::find($id);
            $class->name = $request->name;
            $class->grade = $request->grade;
            $class->save();
//            $class_section = ClassSection::where('class_id',$class->id)->first();
//            $all_section_ids = ClassSection::whereIn('section_id', $request->section_id)->where('class_id', $id)->pluck('section_id')->toArray();
//            $delete_class_section = $class->sections->pluck('id')->toArray();
//            $class_section = array();
//            foreach ($request->section_id as $key => $section_id) {
//                if (!in_array($section_id, $all_section_ids)) {
//                    $class_section[] = array(
//                        'class_id' => $class->id,
//                        'section_id' => $section_id
//                    );
//                } else {
//                    unset($delete_class_section[array_search($section_id, $delete_class_section)]);
//                }
//            }
//            ClassSection::insert($class_section);

//            // check wheather the id in $delete_class_section is assosiated with other data ..
//            $assignemnts = Assignment::whereIn('class_section_id',$delete_class_section)->count();
//            $attendances = Attendance::whereIn('class_section_id',$delete_class_section)->count();
//            $exam_result = ExamResult::whereIn('class_section_id',$delete_class_section)->count();
//            $lessons = Lesson::whereIn('class_section_id',$delete_class_section)->count();
//            $student_session = StudentSessions::whereIn('class_section_id',$delete_class_section)->count();
//            $students = Students::whereIn('class_section_id',$delete_class_section)->count();
//            $subject_teachers = SubjectTeacher::whereIn('class_section_id',$delete_class_section)->count();
//            $timetables = Timetable::whereIn('class_section_id',$delete_class_section)->count();
//
//            if($assignemnts || $attendances || $exam_result || $lessons || $student_session || $students || $subject_teachers || $timetables){
//                $response = array(
//                    'error' => true,
//                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
//                );
//                return response()->json($response);
//            }else{
//                //Remaining Data in $delete_class_section should be deleted
//                ClassSection::whereIn('section_id', $delete_class_section)->where('class_id', $id)->delete();
//            }

            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully'),
            );
        } catch (\Throwable $e) {
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
     * @param \App\Models\ClassSchool $classSchool
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if ($user->type != 'Principal') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $school = Schools::where('principal_id',Auth::user()->id)->first();

        try {
            $classSection = ClassSection::where('class_id', $id);
//            SubjectTeacher::where('class_section_id',$classSection->first()->id)->delete();
//            ClassSubject::where('class_id',$id)->delete();
//            StudentReportCards::where('class_id',$id)->delete();
//            StudentQuarter::where('class_id',$id)->delete();
//            ClassQuarter::where('class_id',$id)->delete();
//            Attendance::where('class_section_id',$classSection->first()->id)->delete();
            Students::where('class_section_id',$classSection->first()->id)->update([
                'class_status' => null,
                'class_section_id' => null,
            ]);

            $classStudent_ids = Students::where('class_section_id',$classSection->first()->id)->pluck('user_id')->toArray();
            $meeting = Meetings::whereIn('student_id',$classStudent_ids)->first();
            if($meeting){
                $meetingParticipantsCount = Meetings::where('meeting_hash',$meeting->meeting_hash)->count();
                if($meetingParticipantsCount == 1){
                    MainMeeting::where('meeting_hash',$meeting->meeting_hash)->delete();
                }
                Meetings::whereIn('student_id',$classStudent_ids)->delete();
            }

            $class =ClassSchool::where('id',$id)->first();
            $classStudents = Students::where('class_section_id',$classSection->first()->id)->with('user')->get();

            foreach ($classStudents as $classStudent){
                $message = "Class ".$class->name ." has been removed by the principal";
                $this->notificationSender->sendNotification(
                    "Class Removed",
                    $message,
                    [$classStudent->mother_id,$classStudent->father_id], "CLASS_REMOVED");
                $this->notificationSender->saveLogs($classStudent->mother_id ?? $classStudent->father_id,$user->id,$message,'CLASS_REMOVED','Class removed by the Principal');
            }

            ClassSchool::where('id', $id)->delete();
            $classSection->delete();

            $response = array(
                'error' => false,
                'message' => trans('data_delete_successfully')
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

    public function show()
    {

        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';
        $school = principalSchoolOrFail(Auth::user()->id);

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $sql = ClassSchool::where('school_id',$school->id)->with('sections', 'medium');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")
                ->orWhereHas('sections', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('medium', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
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
            $operate = '<a href=' . route('class.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon d-flex justify-content-center align-items-center edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a href=' . route('class.destroy', $row->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon d-flex justify-content-center align-items-center delete-form" data-id=' . $row->id . ' title="Delete"><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['grade'] = $row->grade;
            $tempRow['medium_name'] = null;
            $sections = $row->sections;
            $tempRow['sections'] = $sections;
            $tempRow['section_names'] = $sections->pluck('name');
            $tempRow['created_at'] = $row->created_at->toDateString();
            $tempRow['updated_at'] = $row->updated_at->toDateString();
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function subject()
    {
        if (!Auth::user()->can('class-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }

        $classes = ClassSchool::orderBy('id', 'DESC')->with('medium', 'sections')->get();
        $subjects = Subject::orderBy('id', 'ASC')->get();
        $mediums = Mediums::orderBy('id', 'ASC')->get();



        return response(view('class.subject', compact('classes', 'subjects', 'mediums')));
    }

    public function update_subjects(Request $request, $id)
    {
        //        dd($request->all());
        //        if (!Auth::user()->can('class-create')) {
        //            $response = array(
        //                'error' => true,
        //                'message' => trans('no_permission_message')
        //            );
        //            return response()->json($response);
        //        }
        $validation_rules = array(
            'class_id' => 'required|numeric',
            'edit_core_subject' => 'nullable|array',
            'edit_core_subject.*' => 'nullable|array|required_array_keys:class_subject_id,subject_id',
            'core_subject' => 'nullable|array',
            'elective_subject_id' => 'array',
            'elective_subjects' => 'nullable|array',
            'elective_subjects.*.subject_id' => 'required|array',
            'elective_subjects.*.total_selectable_subjects' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $validation_rules);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            //Update Core Subjects first
            if ($request->edit_core_subject) {
                foreach ($request->edit_core_subject as $row) {
                    $edit_core_subject = ClassSubject::findOrFail($row['class_subject_id']);
                    $edit_core_subject->subject_id = $row['subject_id'];
                    $edit_core_subject->save();
                }
            }

            //Add New Core subjects
            if ($request->core_subject_id) {
                $core_subjects = array();
                foreach ($request->core_subject_id as $row) {
                    $core_subjects[] = array(
                        'class_id' => $request->class_id,
                        'type' => "Compulsory",
                        'subject_id' => $row,
                    );
                }
                ClassSubject::insert($core_subjects);
            }

            //Create Subject group for Elective Subjects
            if ($request->edit_elective_subjects) {
                foreach ($request->edit_elective_subjects as $subject_group) {
                    //Create Subject Group
                    $elective_subject_group = ElectiveSubjectGroup::findOrFail($subject_group['subject_group_id']);
                    $elective_subject_group->total_subjects = count($subject_group['subject_id']);
                    $elective_subject_group->total_selectable_subjects = $subject_group['total_selectable_subjects'];
                    $elective_subject_group->class_id = $request->class_id;
                    $elective_subject_group->save();

                    //Assign Elective Subjects to this Subject Group
                    foreach ($subject_group['subject_id'] as $key => $subject_id) {
                        if (isset($subject_group['class_subject_id'][$key]) && !empty($subject_group['class_subject_id'][$key])) {
                            //If class_subject_id exists then its old subject so edit that row
                            $elective_subject = ClassSubject::findOrFail($subject_group['class_subject_id'][$key]);
                        } else {
                            //Else class_subject_id does not exists then its new subject so create new record
                            $elective_subject = new ClassSubject();
                        }
                        $elective_subject->class_id = $request->class_id;
                        $elective_subject->type = "Elective";
                        $elective_subject->subject_id = $subject_id;
                        $elective_subject->elective_subject_group_id = $elective_subject_group->id;
                        $elective_subject->save();
                    }
                }
            }

            //Create Subject group for Elective Subjects
            if ($request->elective_subjects) {
                foreach ($request->elective_subjects as $subject_group) {
                    //Create Subject Group
                    $elective_subject_group = new ElectiveSubjectGroup();
                    $elective_subject_group->total_subjects = count($subject_group['subject_id']);
                    $elective_subject_group->total_selectable_subjects = $subject_group['total_selectable_subjects'];
                    $elective_subject_group->class_id = $request->class_id;
                    $elective_subject_group->save();

                    //Assign Elective Subjects to this Subject Group
                    foreach ($subject_group['subject_id'] as $subject_id) {
                        $elective_subject = array(
                            'class_id' => $request->class_id,
                            'type' => "Elective",
                            'subject_id' => $subject_id,
                            'elective_subject_group_id' => $elective_subject_group->id,
                        );
                        ClassSubject::insert($elective_subject);
                    }
                }
            }

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function subject_list()
    {
        if (!Auth::user()->can('class-list')) {
            $response = array(
                'error' => true,
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

        $sql = ClassSchool::with('sections', 'medium', 'coreSubject', 'electiveSubjectGroup.electiveSubjects.subject');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%");
        }
        if (isset($_GET['medium_id']) && !empty($_GET['medium_id'])) {
            $sql = $sql->where('medium_id', $_GET['medium_id']);
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

            $row = (object)$row;
            $operate = '<a href=' . route('class.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['medium_id'] = $row->medium->id;
            $tempRow['medium_name'] = $row->medium->name;
            $tempRow['section_names'] = $row->sections->pluck('name');
            $tempRow['core_subjects'] = $row->coreSubject;
            $tempRow['elective_subject_groups'] = $row->electiveSubjectGroup;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function subject_destroy($id)
    {
        // if (!Auth::user()->can('class-delete')) {
        //     $response = array(
        //         'error' => true,
        //         'message' => trans('no_permission_message')
        //     );
        //     return response()->json($response);
        // }
        try {
            //check wheather the class subject exists in other table
            $online_exam_questions = OnlineExamQuestion::where('class_subject_id',$id)->count();
            $online_exams = OnlineExam::where('class_subject_id',$id)->count();
            if($online_exam_questions || $online_exams){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                $class_subject = ClassSubject::findOrFail($id);
                if ($class_subject->type == "Elective"  ) {
                    $subject_group = ElectiveSubjectGroup::findOrFail($class_subject->elective_subject_group_id);
                    $subject_group->total_subjects = $subject_group->total_subjects - 1;
                    if ($subject_group->total_subjects > 0) {
                        $subject_group->save();
                    } else {
                        $subject_group->delete();
                    }
                }
                $class_subject->delete();
                $response = array(
                    'error' => false,
                    'message' => trans('data_delete_successfully')
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

    public function subject_group_destroy($id)
    {
        // if (!Auth::user()->can('class-delete')) {
        //     $response = array(
        //         'error' => true,
        //         'message' => trans('no_permission_message')
        //     );
        //     return response()->json($response);
        // }
        try {
            $subject_group = ElectiveSubjectGroup::findOrFail($id);

            // check wheather the class subject exists in other table..
            $class_subject_id = ClassSubject::where('elective_subject_group_id',$id)->pluck('id');
            $online_exam_questions = OnlineExamQuestion::whereIn('class_subject_id',$class_subject_id)->count();
            $online_exams = OnlineExam::whereIn('class_subject_id',$class_subject_id)->count();
            if($online_exam_questions || $online_exams){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                $subject_group->electiveSubjects()->delete();
                $subject_group->delete();
                $response = array(
                    'error' => false,
                    'message' => trans('data_delete_successfully')
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
    public function getSubjectsByMediumId($medium_id)
    {
        try {
            $subjects = Subject::where('medium_id', $medium_id)->get();
            $response = array(
                'error' => false,
                'data' => $subjects,
                'message' => trans('data_delete_successfully')
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function classSectionsBySchool($school_id,$type = null){
        $class_section = ClassSection::SubjectTeacher()->whereHas('class')->whereHas('section',function ($query) use ($school_id){
            $query->whereHas('school');
            $query->where('school_id',$school_id);
        })->with('class', 'section')->get();
        return response()->json($class_section);

    }
}
