<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Exam;
use App\Models\ExamClass;
use App\Models\ClassSchool;
use App\Models\ClassSubject;
use Illuminate\Http\Request;
use App\Models\ExamTimetable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ExamTimetableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $exams = Exam::where('publish', 0)->get();
        $class_name = ClassSchool::with('medium')->get();
        return response(view('exams.exam-timetable', compact('exams', 'class_name')));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**class
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required',
            'class_id' => 'required',
            'timetable.*.passing_marks' => 'required|lte:timetable.*.total_marks',
            'timetable.*.end_time' => 'required|after:timetable.*.start_time',
            'timetable.*.date' => 'required|date',
        ],
        [
            'timetable.*.passing_marks.lte' => trans('passing_marks_should_less_than_or_equal_to_total_marks'),
            'timetable.*.end_time.after' => trans('end_time_should_be_greater_than_start_time')
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $session_year_id = Exam::with('session_year')->where('id', $request->exam_id)->pluck('session_year_id')->first();

            foreach ($request->timetable as $timetable) {
                $date = date('Y-m-d', strtotime($timetable['date']));
                $exam_timetable[] = array(
                    'exam_id' => $request->exam_id,
                    'class_id' => $request->class_id,
                    'subject_id' => $timetable['subject_id'],
                    'total_marks' => $timetable['total_marks'],
                    'passing_marks' => $timetable['passing_marks'],
                    'start_time' => $timetable['start_time'],
                    'end_time' => $timetable['end_time'],
                    'date' => $date,
                    'session_year_id' => $session_year_id
                );
            }
            ExamTimetable::insert($exam_timetable);
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
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
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

        $sql = ExamClass::with(['exam.session_year:id,name', 'class_timetable.subject:id,name,type', 'class']);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->whereHas('class_timetable', function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")
                    ->orWhere('total_marks', 'LIKE', "%$search%")
                    ->orWhere('passing_marks', 'LIKE', "%$search%")
                    ->orWhere('start_time', 'LIKE', "%$search%")
                    ->orWhere('end_time', 'LIKE', "%$search%")
                    ->orWhere('date', 'LIKE', "%$search%")
                    ->orWhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                    ->orWhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%");
            })->orWhereHas('exam', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            })->orWhereHas('class', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            })->orWhereHas('class_timetable.subject', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            })->orWhereHas('exam.session_year', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            });
        }
        if (isset($_GET['exam_id']) && $_GET['exam_id'] != null) {
            $sql->orWhere('exam_id', $_GET['exam_id']);
        }
        if (isset($_GET['class_id']) && $_GET['class_id'] != null) {
            $sql->where('class_id', $_GET['class_id']);
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

            $class_subjects = '';
            $class_subjects = ClassSubject::with('subject')->where('class_id', $row->class_id)->get();
            $operate = '';
            $operate = '<a href="#" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['exam_name'] = $row->exam->name;
            $tempRow['class_name'] = $row->class->name;
            $tempRow['exam_id'] = $row->exam_id;
            $tempRow['class_id'] = $row->class_id;
            $tempRow['subjects'] = null;
            foreach ($class_subjects as $subjects) {
                $tempRow['subjects'][] = array(
                    'id' => $subjects->subject->id,
                    'name' => $subjects->subject->name,
                    'type' => $subjects->subject->type
                );
            }
            $tempRow['timetable'] = $row->class_timetable;
            $tempRow['session_year_id'] = $row->exam->session_year->id;
            $tempRow['session_year'] = $row->exam->session_year->name;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            if ($row->exam->publish == 0) {
                $tempRow['operate'] = $operate;
            } else {
                $tempRow['operate'] = '';
            }
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $exam = ExamTimetable::find($id);
            $exam->delete();
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

    public function getClassesByExam($exam_id)
    {
        try {
            $exam_classes = ExamClass::with('class.medium')->where('exam_id', $exam_id)->get();
            $response = array(
                'error' => false,
                'data' => $exam_classes
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function getSubjectsByClass($class_id)
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $exam_subjects = ClassSubject::with('subject')->where('class_id', $class_id)->get();
            $response = array(
                'error' => false,
                'data' => $exam_subjects
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }


    public function updateTimetable(Request $request)
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'edit_timetable.*.subject_id' => 'required',
            'edit_timetable.*.total_marks' => 'required',
            'edit_timetable.*.passing_marks' => 'required|lte:edit_timetable.*.total_marks',
            'edit_timetable.*.start_time' => 'required',
            'edit_timetable.*.end_time' => 'required|after:edit_timetable.*.start_time',
            'edit_timetable.*.date' => 'required',
        ],
        [
            'edit_timetable.*.passing_marks.lte' => trans('passing_marks_should_less_than_or_equal_to_total_marks'),
            'edit_timetable.*.end_time.after' => trans('end_time_should_be_greater_than_start_time')
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            foreach ($request->edit_timetable as $timetable) {
                if (isset($timetable['timetable_id']) && $timetable['timetable_id'] != null) {
                    $timetable_db = ExamTimetable::find($timetable['timetable_id']);
                    $timetable_db->subject_id = $timetable['subject_id'];
                    $timetable_db->total_marks = $timetable['total_marks'];
                    $timetable_db->passing_marks = $timetable['passing_marks'];
                    $timetable_db->start_time = $timetable['start_time'];
                    $timetable_db->end_time = $timetable['end_time'];
                    $date = date('Y-m-d', strtotime($timetable['date']));
                    $timetable_db->date = $date;
                    $timetable_db->save();
                    $response = array(
                        'error' => false,
                        'message' => trans('data_update_successfully'),
                        'status' => 200
                    );
                } else {
                    $date = date('Y-m-d', strtotime($timetable['date']));
                    $insert_data[] = array(
                        'exam_id' => $request->exam_id,
                        'class_id' => $request->class_id,
                        'subject_id' => $timetable['subject_id'],
                        'total_marks' => $timetable['total_marks'],
                        'passing_marks' => $timetable['passing_marks'],
                        'start_time' => $timetable['start_time'],
                        'end_time' => $timetable['end_time'],
                        'session_year_id' => $request->session_year_id,
                        'date' => $date,
                    );
                }
            }
            if (isset($insert_data)) {
                ExamTimetable::insert($insert_data);
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                    'status' => 200
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

    public function deleteTimetable($id)
    {
        if (!Auth::user()->can('exam-timetable-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $exam_timetable = ExamTimetable::find($id);
            $exam_timetable->delete();
            $response = array(
                'error' => false,
                'message' => trans('data_delete_successfully'),
                'status' => 200
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
