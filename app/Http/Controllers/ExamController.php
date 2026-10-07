<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Students;
use App\Models\ExamClass;
use App\Models\ExamMarks;
use App\Models\ExamResult;
use App\Models\ClassSchool;
use App\Models\ExamClasses;
use App\Models\SessionYear;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use Illuminate\Http\Request;
use App\Models\ExamTimetable;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (!Auth::user()->can('exam-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $classes = ClassSchool::with('medium')->get();
        $subjects = Subject::orderBy('id', 'DESC')->get();
        $session_year_all = SessionYear::select('id', 'name', 'default')->get();
        return response(view('exams.index', compact('classes', 'subjects', 'session_year_all')));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('exam-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'class_id' => 'required',
            'name' => 'required',
            'session_year_id' => 'required',
            'description' => 'nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        try {
            $exam = new Exam();
            $exam->name = $request->name;
            $exam->description = $request->description;
            $exam->session_year_id = $request->session_year_id;
            $exam->save();

            if ($request->class_id) {
                $exam_classes = [];
                foreach ($request->class_id as $class_id) {
                    $exam_classes[] = array(
                        'exam_id' => $exam->id,
                        'class_id' => $class_id,
                    );
                }
                ExamClass::insert($exam_classes);
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
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
     * @return JsonResponse
     */
    public function show()
    {
        if (!Auth::user()->can('exam-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
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

        $sql = Exam::with('exam_classes.class.medium', 'session_year', 'timetable');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%")
                ->orwhere('description', 'LIKE', "%$search%")
                ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                ->orWhereHas('session_year', function ($q) use ($search) {
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
            $operate = '';
            if ($row->publish == 0) {
                $operate .= '<a href="#" class="btn btn-xs btn-gradient-success btn-rounded btn-icon publish-exam-result" data-id=' . $row->id . ' title="Publish Exam Result"><i class="fa fa-check-circle"></i></a>&nbsp;&nbsp;';
            } else {
                $operate .= '<a href="#" class="btn btn-xs btn-gradient-warning btn-rounded btn-icon publish-exam-result" data-id=' . $row->id . ' title="Unpublish Exam Result"><i class="fa fa-times-circle"></i></a>&nbsp;&nbsp;';
            }
            if (sizeof($row->timetable)) {
                foreach ($row->exam_classes as $data) {
                    $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $data->exam_id, 'class_id' => $data->class_id])->first();
                    $starting_date = $starting_date_db['min(date)'];
                    $ending_date_db = ExamTimetable::select(DB::raw("max(date)"))->where(['exam_id' => $data->exam_id, 'class_id' => $data->class_id])->first();
                    $ending_date = $ending_date_db['max(date)'];
                    $currentTime = Carbon::now();
                    $current_date = date($currentTime->toDateString());
                    if ($current_date >= $starting_date && $current_date <= $ending_date) {
                        $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    } elseif ($current_date < $starting_date) {
                        $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    } else {
                        $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    }
                }
            }
            if(isset($exam_status)){
                if ($exam_status == 0) {
                    $operate .= '<a href="#" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
                }
            }else {
                $operate .= '<a href="#" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            }
            $operate .= '<a href="' . route('exams.destroy', $row->id) . '" class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" data-id=' . $row->id . '><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['description'] = $row->description;
            $tempRow['class_name'] = [];
            foreach ($row->exam_classes as $exam_class) {
                $tempRow['class_name'][] = $exam_class->class->name.'    ';
            }
            $tempRow['class_id'] = $row->exam_classes->pluck('class.id');
            $tempRow['session_year_name'] = $row->session_year->name;
            $tempRow['timetable'] = $row->timetable;
            $tempRow['publish'] = $row->publish;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->can('exam-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $exam = Exam::with('exam_classes')->find($id);
            $exam->name = $request->name;
            $exam->description = $request->description;
            $exam->save();

            $all_exam_classes_id = ExamClass::whereIn('class_id', $request->class_id)->where('exam_id', $request->edit_id)->pluck('class_id')->toArray();
            $delete_exam_classes = $exam->exam_classes->pluck('class_id')->toArray();
            $exam_classes = array();

            foreach ($request->class_id as $class_id) {
                if (!in_array($class_id, $all_exam_classes_id)) {
                    $exam_classes[] = array(
                        'exam_id' => $exam->id,
                        'class_id' => $class_id
                    );
                } else {
                    unset($delete_exam_classes[array_search($class_id, $delete_exam_classes)]);
                }
            }
            ExamClass::insert($exam_classes);

            // //Remaining Data in $all_exam_classes_id should be deleted
            ExamClass::whereIn('class_id', $delete_exam_classes)->where('exam_id', $id)->delete();

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
            );
        }
        return response()->json($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('exam-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            //check wheather exam id is associate with other tables ..
            $exam_timetables = ExamTimetable::where('exam_id',$id)->count();
            $exam_results = ExamResult::where('exam_id',$id)->count();
            if($exam_timetables || $exam_results){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                $exam = Exam::find($id);
                $exam->delete();
                $exam_timetable_id = ExamTimetable::where('exam_id', $id)->pluck('id');
                ExamClass::where('exam_id', $id)->delete();
                ExamTimetable::whereIn('id', $exam_timetable_id)->delete();
                ExamMarks::whereIn('exam_timetable_id', $exam_timetable_id)->delete();
                ExamResult::where('exam_id', $id)->delete();
                $response = array(
                    'error' => false,
                    'message' => trans('data_delete_successfully')
                );
            }
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function publishExamResult($id)
    {
        try {
            $exam_marks_db = ExamTimetable::where('exam_id', $id)->with('exam_marks')->get();

            foreach ($exam_marks_db as $data) {
                if (sizeof($data->exam_marks) == 0) {
                    $response = array(
                        'error' => true,
                        'message' => trans('marks_are_not_submitted'),
                    );
                    return response()->json($response);
                }
            }

            $exam = Exam::with(['marks' => function ($query) {
                $query->with('student:id,class_section_id')->selectRaw('SUM(obtained_marks) as total_obtained_marks,student_id')->groupBy('student_id');
            }, 'timetable' => function ($query) {
                $query->selectRaw('exam_id,SUM(total_marks) as total_marks')->groupby('class_id');
            }])->with('exam_classes')->where('id', $id)->first();

            foreach ($exam->exam_classes as $data) {
                $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $data->exam_id, 'class_id' => $data->class_id])->first();
                $starting_date = $starting_date_db['min(date)'];
                $ending_date_db = ExamTimetable::select(DB::raw("max(date)"))->where(['exam_id' => $data->exam_id, 'class_id' => $data->class_id])->first();
                $ending_date = $ending_date_db['max(date)'];
                $currentTime = Carbon::now();
                $current_date = date($currentTime->toDateString());
                if ($current_date >= $starting_date && $current_date <= $ending_date) {
                    $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } elseif ($current_date < $starting_date) {
                    $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } else {
                    $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
                }
                break;
            }
            $size_of_timetable_array = sizeof($exam->timetable);
            $size_of_marks_array = sizeof($exam->marks);
            if ($exam_status == 2 && $size_of_timetable_array != 0 && $size_of_marks_array != 0) {
                //If Exam timetable is empty then don't allow to publish function
                if ($exam->publish == 0) {
                    // If exam is Unpublished then Insert ExamResult records and Publish the Exam
                    $exam_result = [];
                    foreach ($exam->marks as $exam_marks) {
                        $percentage = ($exam_marks['total_obtained_marks'] * 100) / $exam->timetable[0]['total_marks'];
                        $grade = findExamGrade($percentage);

                        if ($grade == null) {
                            $response = array(
                                'error' => true,
                                'message' => trans('grades_data_does_not_exists'),
                            );
                            return response()->json($response);
                        }

                        $exam_result[] = [
                            'exam_id' => $exam->id,
                            'class_section_id' => $exam_marks['student']['class_section_id'],
                            'student_id' => $exam_marks['student_id'],
                            'total_marks' => $exam->timetable[0]['total_marks'],
                            'obtained_marks' => $exam_marks['total_obtained_marks'],
                            'percentage' => round($percentage, 2),
                            'grade' => $grade,
                            'session_year_id' => $exam->session_year_id,
                        ];
                    }
                    ExamResult::insert($exam_result);
                    $exam->publish = 1;
                } else {
                    //If Exam is already published then unpublished it and delete Exam Result
                    ExamResult::where('exam_id', $id)->delete();
                    $exam->publish = 0;
                }
                $exam->save();
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                );
            } else {
                $response = array(
                    'error' => true,
                    'message' => trans('exam_not_completed_yet'),
                );
            }
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
            );
        }
        return response()->json($response);
    }

    public function uploadMarks()
    {
        if (!Auth::user()->can('class-teacher')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $teacher = Auth::user()->teacher;
        $class_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('class_id')->first();
        $exam_data = Exam::with(['exam_classes' => function ($q) use ($class_id) {
            $q->where('class_id', $class_id);
        }])->with('timetable')->where('publish', 0)->get();

        foreach ($exam_data as $data) {
            if (sizeof($data->timetable)) {
                $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $data->id, 'class_id' => $class_id])->first();
                $starting_date = $starting_date_db['min(date)'];
                $ending_date_db = ExamTimetable::select(DB::raw("max(date)"))->where(['exam_id' => $data->id, 'class_id' => $class_id])->first();
                $ending_date = $ending_date_db['max(date)'];
                $currentTime = Carbon::now();
                $current_date = date($currentTime->toDateString());

                if ($current_date >= $starting_date && $current_date <= $ending_date) {
                    $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } elseif ($current_date < $starting_date) {
                    $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
                } else {
                    $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
                }

                if ($exam_status == 2) {
                    $exams[] = $data;
                }
            }
        }
        if (isset($exams)) {
            return response(view('exams.upload-marks', compact('exams', 'class_id')));
        } else {
            return response(view('exams.upload-marks'));
        }
    }

    public function getExamSubjects($exam_id)
    {
        try {
            $teacher = Auth::user()->teacher;
            $class_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('class_id');
            $subjects = ExamTimetable::with('subject')->where(['class_id' => $class_id, 'exam_id' => $exam_id])->get();
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $subjects
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function marksList(Request $request)
    {
        if (!Auth::user()->can('class-teacher')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if (!$request->exam_id || !$request->subject_id) {
            return false;
        }
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

        $teacher = Auth::user()->teacher;
        $class_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('class_id');
        $class_section_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('id')->first();
        $exam_timetable_id = ExamTimetable::where(['exam_id' => $request->exam_id, 'class_id' => $class_id, 'subject_id' => $request->subject_id])->pluck('id')->first();

        $starting_date_db = ExamTimetable::select(DB::raw("min(date)"))->where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->first();
        $starting_date = $starting_date_db['min(date)'];
        $ending_date_db = ExamTimetable::select(DB::raw("max(date)"))->where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->first();
        $ending_date = $ending_date_db['max(date)'];
        $currentTime = Carbon::now();
        $current_date = date($currentTime->toDateString());
        if ($current_date >= $starting_date && $current_date <= $ending_date) {
            $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
        } elseif ($current_date < $starting_date) {
            $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
        } else {
            $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
        }

        if ($exam_status != 2) {
            $response = array(
                'error' => true,
                'message' => trans('exam_not_completed_yet'),
            );
            return response()->json($response);
        }

        //Fetching Students Data on Basis of Class Section ID with Realtion Exam Marks
        $sql = Students::with(['user:id,first_name,last_name'])->with(['class_section.class.allSubjects' => function ($q) use ($request) {
            $q->where('subject_id', $request->subject_id)->with('subject');
        }])->with(['exam_marks' => function ($q) use ($exam_timetable_id) {
            $q->where('exam_timetable_id', $exam_timetable_id);
        }])->where('class_section_id', $class_section_id);

        $subject_total_marks = ExamTimetable::where(['exam_id' => $request->exam_id, 'class_id' => $class_id, 'subject_id' => $request->subject_id])->pluck('total_marks');

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%")
                ->orwhere('mobile', 'LIKE', "%$search%");
        }
        $total = $sql->count();

        $sql->orderBy($sort, $order);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = '<a href=' . route('exams.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a href=' . route('exams.destroy', $row->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" data-id=' . $row->id . '><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['student_name'] = $row->user->first_name . ' ' . $row->user->last_name;
            $tempRow['student_id'] = $row->id;
            foreach ($subject_total_marks as $total_marks) {
                $tempRow['total_marks'] = $total_marks;
            }
            foreach ($row->exam_marks as $exam_result) {
                $tempRow['exam_marks_id'] = $exam_result ? $exam_result->id : '';
                $tempRow['obtained_marks'] = $exam_result ? $exam_result->obtained_marks : '';
            }
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function submitMarks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'exam_marks.*.student_id' => 'required|numeric',
            'exam_marks.*.obtained_marks' => 'required|numeric|lte:exam_marks.*.total_marks',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        try {
            $teacher = Auth::user()->teacher;
            $class_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('class_id')->first();

            $exam_timetable = ExamTimetable::where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->where('subject_id', $request->subject_id)->firstOrFail();

            foreach ($request->exam_marks as $exam_marks) {
                $passing_marks = $exam_timetable->passing_marks;
                if ($exam_marks['obtained_marks'] >= $passing_marks) {
                    $status = 1;
                } else {
                    $status = 0;
                }
                $marks_percentage = ($exam_marks['obtained_marks'] / $exam_marks['total_marks']) * 100;
                $exam_grade = findExamGrade($marks_percentage);

                if ($exam_grade == null) {
                    $response = array(
                        'error' => true,
                        'message' => trans('grades_data_does_not_exists'),
                    );
                    return response()->json($response);
                }

                ExamMarks::updateOrInsert(
                    ['id' => isset($exam_marks['exam_marks_id']) ? $exam_marks['exam_marks_id'] : null],
                    ['exam_timetable_id' => $exam_timetable->id, 'student_id' => $exam_marks['student_id'], 'subject_id' => $request->subject_id, 'obtained_marks' => $exam_marks['obtained_marks'], 'passing_status' => $status, 'session_year_id' => $exam_timetable->session_year_id, 'grade' => $exam_grade,]
                );
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function getSubjectByExam($exam_id)
    {
        try {
            $exam_timetable = ExamTimetable::with('subject')->where('exam_id', $exam_id)->get();
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $exam_timetable
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function deleteTimetable($id)
    {
        try {
            $exam_timetable = ExamTimetable::find($id);
            $exam_timetable->delete();
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

    public function indexGrades()
    {
        if (!Auth::user()->can('grade-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $grades = Grade::get();
        return response(view('exams.exam-grade', compact('grades')));
    }

    public function createGrades(Request $request)
    {
        if (!Auth::user()->can('grade-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'grade.*.starting_range' => 'required|numeric|between:0,100',
            'grade.*.ending_range' => 'required|numeric|between:0,100|gt:grade.*.starting_range',
            'grade.*.grades' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            foreach ($request->grade as $grade) {
                Grade::updateOrInsert(
                    ['id' => isset($grade['id']) ? $grade['id'] : null],
                    ['starting_range' => $grade['starting_range'], 'ending_range' => $grade['ending_range'], 'grade' => $grade['grades']]
                );
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function destroyGrades($id)
    {
        if (!Auth::user()->can('grade-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $grade = Grade::find($id);
            $grade->delete();
            $response = array(
                'error' => false,
                'message' => trans('data_delete_successfully'),
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function getExamResultIndex()
    {
        if (!Auth::user()->can('class-teacher')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $exams = Exam::where('publish', 1)->get();
        return view('exams.show_exam_result', compact('exams'));
    }

    public function showExamResult(Request $request)
    {
        if (!Auth::user()->can('class-teacher')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if ($request->exam_id) {
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

            $teacher = Auth::user()->teacher;
            $class_section_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('id')->first();
            $exam_timetable_id = ExamTimetable::where('exam_id', $request->exam_id)->pluck('id');

            $sql = ExamResult::with('student.user:id,first_name,last_name', 'session_year:id,name')->with(['student.exam_marks' => function ($q) use ($exam_timetable_id) {
                $q->whereIn('exam_timetable_id', $exam_timetable_id)->with('timetable', 'subject:id,name');
            }])->where(['exam_id' => $request->exam_id, 'class_section_id' => $class_section_id]);


            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'] ?? '';
                $sql = $sql->where('id', 'LIKE', "%$search%")
                    ->orwhere('total_marks', 'LIKE', "%$search%")
                    ->orwhere('grade', 'LIKE', "%$search%")
                    ->orwhere('obtained_marks', 'LIKE', "%$search%")
                    ->orwhere('percentage', 'LIKE', "%$search%")
                    ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                    ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                    ->orWhereHas('student.user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', "%$search%")->orWhere('last_name', 'LIKE', "%$search%");
                    })->where('exam_id', $request->exam_id)
                    ->orWhereHas('session_year', function ($q) use ($search) {
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
                $operate = '';
                $operate .= '<a href="#" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id="' . $row->id . '" data-student_id ="' . $row->student_id . '" title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

                $tempRow['id'] = $row->id;
                $tempRow['no'] = $no++;
                $tempRow['student_id'] = $row->student_id;
                $tempRow['student_name'] = $row->student->user->first_name . ' ' . $row->student->user->last_name;
                $tempRow['total_marks'] = $row->total_marks;
                $tempRow['obtained_marks'] = $row->obtained_marks;
                $tempRow['percentage'] = $row->percentage;
                $tempRow['grade'] = $row->grade;
                $tempRow['session_year_name'] = $row->session_year->name;
                $tempRow['created_at'] = $row->created_at;
                $tempRow['updated_at'] = $row->updated_at;
                $tempRow['operate'] = $operate;
                $tempRow['data'] = $row->student->exam_marks;
                $rows[] = $tempRow;
            }

            $bulkData['rows'] = $rows;
            return response()->json($bulkData);
        }
    }
    public function updateExamResultMarks(Request $request)
    {
        if (!Auth::user()->can('class-teacher')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'edit.*.marks_id' => 'required|numeric',
            'edit.*.obtained_marks' => 'required|numeric|lte:edit.*.total_marks',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $teacher = Auth::user()->teacher;
            $class_id = ClassSection::where('class_teacher_id', $teacher->user_id)->pluck('class_id')->first();

            foreach ($request->edit as $data) {
                $marks_db = ExamMarks::find($data['marks_id']);
                $marks_db->obtained_marks = $data['obtained_marks'];


                $passing_marks = $data['passing_marks'];
                if ($data['obtained_marks'] >= $passing_marks) {
                    $marks_db->passing_status = 1;
                } else {
                    $marks_db->passing_status = 0;
                }

                $marks_percentage = ($data['obtained_marks'] / $data['total_marks']) * 100;

                $grade = findExamGrade($marks_percentage);
                if ($grade == null) {
                    $response = array(
                        'error' => true,
                        'message' => trans('grades_data_does_not_exists'),
                    );
                    return response()->json($response);
                }

                $marks_db->save();

                $exam_result_id = ExamResult::where(['exam_id' => $data['exam_id'], 'student_id' => $data['student_id']])->pluck('id');

                $exam = Exam::with(['marks' => function ($query) use ($data) {
                    $query->with('student:id,class_section_id')->selectRaw('SUM(obtained_marks) as total_obtained_marks,student_id')->where('student_id', $data['student_id'])->groupBy('student_id');
                }, 'timetable' => function ($query) use ($data, $class_id) {
                    $query->selectRaw('exam_id,SUM(total_marks) as total_marks')->where(['exam_id' => $data['exam_id'], 'class_id' => $class_id]);
                }])->where('id', $data['exam_id'])->first();

                foreach ($exam->marks as $exam_marks) {
                    $percentage = ($exam_marks['total_obtained_marks'] * 100) / $exam->timetable[0]['total_marks'];

                    $grade = findExamGrade($percentage);
                    if ($grade == null) {
                        $response = array(
                            'error' => true,
                            'message' => trans('grades_data_does_not_exists'),
                        );
                        return response()->json($response);
                    }

                    $exam_result_db = ExamResult::find($exam_result_id)->first();
                    $exam_result_db->obtained_marks = $exam_marks['total_obtained_marks'];
                    $exam_result_db->percentage = round($percentage, 2);
                    $exam_result_db->grade = $grade;
                    $exam_result_db->save();

                    $response = array(
                        'error' => false,
                        'message' => trans('data_update_successfully'),
                    );
                }
            }
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
}
