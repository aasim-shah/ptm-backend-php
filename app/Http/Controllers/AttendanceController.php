<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Students;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::user()->can('attendance-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class_sections = ClassSection::ClassTeacher()->with('class', 'section')->get();
        return view('attendance.index', compact('class_sections'));
    }


    public function view()
    {
        if (!Auth::user()->can('attendance-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class_sections = ClassSection::ClassTeacher()->with('class', 'section')->get();
        return view('attendance.view', compact('class_sections'));
    }

    public function getAttendanceData(Request $request)
    {
        $response = Attendance::select('type')->where(['date' => date('Y-m-d', strtotime($request->date)), 'class_section_id' => $request->class_section_id])->pluck('type')->first();
        return response()->json($response);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('attendance-create') || !Auth::user()->can('attendance-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'student_id' => 'required',
            'date' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {

            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];
            $class_section_id = $request->class_section_id;
            $date = date('Y-m-d', strtotime($request->date));
            $getid = Attendance::select('id')->where(['date' => $date, 'class_section_id' => $class_section_id])->get();

            for ($i = 0; $i < count($request->student_id); $i++) {

                if (count($getid) > 0) {
                    $attendance = Attendance::find($getid[$i]['id']);
                    $a = "type" . $request->student_id[$i];
                } else {
                    $attendance = new Attendance();
                    $a = "type" . $request->student_id[$i];
                }
                $attendance->class_section_id = $class_section_id;
                $attendance->student_id = $request->student_id[$i];
                $attendance->session_year_id = $session_year_id;
                if ($request->holiday != '' && $request->holiday == 3) {
                    $attendance->type = $request->holiday;
                } else {
                    $attendance->type = $request->$a;
                }
                $attendance->date = $date;
                $attendance->save();
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

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!Auth::user()->can('attendance-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'roll_number';
        $order = 'ASC';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $class_section_id = $request->class_section_id;
        $date = date('Y-m-d', strtotime($request->date));
        $session_year = getSettings('session_year');
        $session_year_id = $session_year['session_year'];
        $chk = Attendance::with('student')->where(['date' => $date, 'class_section_id' => $class_section_id, 'session_year_id' => $session_year_id])->count();

        if (isset($request->date) && $date != '' && $chk > 0) {
            $sql2 = Attendance::with('student')->where(['date' => $date, 'class_section_id' => $class_section_id, 'session_year_id' => $session_year_id]);
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'] ?? '';
                $sql2->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('student.user', function ($q) use ($search) {
                        $q->whereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'")->orwhere('users.first_name', 'LIKE', "%$search%")->orwhere('users.last_name', 'LIKE', "%$search%");
                    });
            }
            $total = $sql2->count();
            $res = $sql2->get();
            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            $tempRow = array();
            $no = 1;
            foreach ($res as $row) {
                $get_type = $row->type;
                if ($get_type == 1) {
                    $type = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
                    <input required type="radio" class="type"  name="type' . $row->student_id . '" value="1" checked>Present
                    </label></div>';
                    $type .= '<div class="form-check-inline"><label class="form-check-label">
                    <input type="radio" class="type"  name="type' . $row->student_id . '" value="0">Absent
                    </label></div></div>';
                } else if ($get_type == 0) {
                    $type = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
                    <input required type="radio" class="type"  name="type' . $row->student_id . '" value="1">Present
                    </label></div>';
                    $type .= '<div class="form-check-inline"><label class="form-check-label">
                    <input type="radio" class="type"  name="type' . $row->student_id . '" value="0" checked>Absent
                    </label></div></div>';
                } else {
                    $type = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
                    <input required type="radio" class="type"  name="type' . $row->student_id . '" value="1">Present
                    </label></div>';
                    $type .= '<div class="form-check-inline"><label class="form-check-label">
                    <input type="radio" class="type"  name="type' . $row->student_id . '" value="0">Absent
                    </label></div></div>';
                }
                $tempRow['id'] = $row->id;
                $tempRow['no'] = $no++;
                $tempRow['student_id'] = "<input type='text' name='student_id[]' class='form-control' readonly value=" . $row->student_id . ">";
                $tempRow['admission_no'] = $row->student->admission_no;
                $tempRow['roll_no'] = $row->student->roll_number;
                $tempRow['name'] = $row->student->user->first_name . ' ' . $row->student->user->last_name;
                $tempRow['type'] = $type;
                $rows[] = $tempRow;
            }
        } else {
            $sql = Students::where('class_section_id', $class_section_id)->with('user');
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'] ?? '';
                $sql->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->whereRaw("concat(first_name,' ',last_name) LIKE '%" . $search . "%'")->orwhere('first_name', 'LIKE', "%$search%")->orwhere('last_name', 'LIKE', "%$search%");
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
                $type = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
                <input required type="radio" class="type"  name="type' . $row->id . '" value="1">Present
                </label></div>';
                $type .= '<div class="form-check-inline"><label class="form-check-label">
                <input type="radio" class="type"  name="type' . $row->id . '" value="0">Absent
                </label></div></div>';

                $tempRow['id'] = $row->id;
                $tempRow['no'] = $no++;
                $tempRow['student_id'] = "<input type='text' name='student_id[]' class='form-control' readonly value=" . $row->id . ">";
                $tempRow['admission_no'] = $row->admission_no;
                $tempRow['roll_no'] = $row->roll_number;
                $tempRow['name'] = $row->user->first_name . ' ' . $row->user->last_name;
                $tempRow['type'] = $type;
                $rows[] = $tempRow;
            }
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }


    public function attendance_show(Request $request)
    {
        if (!Auth::user()->can('attendance-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'student_id';
        $order = 'ASC';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $class_section_id = $request->class_section_id;
        $attendance_type = $request->attendance_type;
        $date = date('Y-m-d', strtotime($request->date));

        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'date' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }

        $sql = Attendance::where('date', $date)->where('class_section_id', $class_section_id)->with('student');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('student_id', 'LIKE', "%$search%")
                ->orWhereHas('student.user', function ($q) use ($search) {
                    $q->whereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'")
                        ->orwhere('users.first_name', 'LIKE', "%$search%")
                        ->orwhere('users.last_name', 'LIKE', "%$search%");
                })
                ->orWhereHas('student', function ($q) use ($search) {
                    $q->where('admission_no', 'LIKE', "%$search%")
                        ->orwhere('id', 'LIKE', "%$search%")
                        ->orwhere('user_id', 'LIKE', "%$search%")
                        ->orwhere('roll_number', 'LIKE', "%$search%");
                });
        }
        if (isset($attendance_type) && $attendance_type != '') {
            $sql->where('type', $attendance_type);
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
            $type = $row->type;
            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['student_id'] = $row->student_id;
            $tempRow['user_id'] = $row->student->user_id;
            $tempRow['admission_no'] = $row->student->admission_no;
            $tempRow['roll_no'] = $row->student->roll_number;
            $tempRow['name'] = $row->student->user->first_name . ' ' . $row->student->user->last_name;
            $tempRow['type'] = ($type == 1) ? '<label class="badge badge-info"> Present</label>' : (($type == 3) ? '<label class="badge badge-success"> Holiday</label>' : '<label class="badge badge-danger"> Absent</label>');
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
