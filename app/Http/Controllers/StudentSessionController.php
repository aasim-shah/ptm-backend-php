<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\SessionYear;
use App\Models\Students;
use App\Models\StudentSessions;
use App\Models\Subject;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentSessionController extends Controller
{
    public function index() {
        if (!Auth::user()->can('promote-student-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        if(Auth::user()->type == 'Principal'){
            $userId = Auth::user()->id;
            $principal = Principal::where('user_id',$userId)->first();
            $schools = Schools::where('principal_id',$principal->user_id)->pluck('id')->toArray();
            Log::info($principal->user_id);
            $class_sections = ClassSection::whereHas('section',function ($query) use ($schools){
                    $query->whereHas('school');
                    $query->whereIn('school_id',$schools);
                })
                ->with('class.medium', 'section')->get();
        }else{
            $class_sections = ClassSection::with('class', 'section')->get();

        }
        $session_year = SessionYear::select('id', 'name')->where('default', 0)->get();
        return view('promote_student.index', compact('class_sections', 'session_year'));
    }

    public function store(Request $request) {
        if (!Auth::user()->can('promote-student-create') || !Auth::user()->can('promote-student-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'student_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $new_session_year_id = $request->session_year_id;
            $new_class_section_id = $request->new_class_section_id;

            for ($i = 0; $i < count($request->student_id); $i++) {
                $status = "status" . $request->student_id[$i];
                $result = "result" . $request->student_id[$i];

                //fetch student data
                $update_student = Students::find($request->student_id[$i]);

                // check the data student with session year passed exists or not
                $check_student_session_data = StudentSessions::where(['student_id' => $request->student_id[$i] , 'session_year_id' => $request->session_year_id]);

                // if exists then update it
                if($check_student_session_data->count()){

                    //get the data
                    $student_session_data = $check_student_session_data->first();

                    //get particular student session data
                    $promote_student = StudentSessions::findOrFail($student_session_data->id);
                    $promote_student->class_section_id = $new_class_section_id;
                    $promote_student->session_year_id = $new_session_year_id;
                    $promote_student->result = $request->$result;
                    $promote_student->status = $request->$status;

                    //  pass & continue
                    if ($request->$status == 1 && $request->$result == 1) {

                        // change the class in student session data
                        $promote_student->class_section_id = $new_class_section_id;

                        //change the class in student data
                        $update_student->class_section_id = $new_class_section_id;
                        $update_student->save();
                    }

                    // fail & continue
                    if ($request->$status == 1 && $request->$result == 0) {

                        // change the class in student session data
                        $promote_student->class_section_id = $update_student->class_section_id;
                    }
                    // pass & leave
                    if ($request->$status == 0 && $request->$result == 1){

                        // change the class in student session data
                        $promote_student->class_section_id = $new_class_section_id;

                        // make the user inactive
                        $user = User::find($update_student->user_id);
                        $user->status = 0;
                        $user->save();
                    }
                    // fail & leave
                    if ($request->$status == 0 && $request->$result == 0){

                        // change the class in student session data
                        $promote_student->class_section_id = $update_student->class_section_id;

                        // make the user inactive
                        $user = User::find($update_student->user_id);
                        $user->status = 0;
                        $user->save();
                    }

                    // save the data in student session
                    $promote_student->save();

                }else{

                    // make new array for new data
                    $add_new_student_session_data = new StudentSessions();
                    $add_new_student_session_data->student_id = $request->student_id[$i];
                    $add_new_student_session_data->session_year_id = $new_session_year_id;
                    $add_new_student_session_data->result = $request->$result;
                    $add_new_student_session_data->status = $request->$status;
                    //  pass & continue
                    if ($request->$status == 1 && $request->$result == 1) {

                        // change the class in student session data
                        $add_new_student_session_data->class_section_id = $new_class_section_id;

                        //update the data in student session data
                        $update_student->class_section_id = $new_class_section_id;
                        $update_student->save();
                    }

                    // fail & continue
                    if ($request->$status == 1 && $request->$result == 0) {

                        // change the class in student session data
                        $add_new_student_session_data->class_section_id = $update_student->class_section_id;

                    }
                    // pass & leave
                    if ($request->$status == 0 && $request->$result == 1){

                        // change the class in student session data
                        $add_new_student_session_data->class_section_id = $new_class_section_id;

                        // make user inactive
                        $user = User::find($update_student->user_id);
                        $user->status = 0;
                        $user->save();
                    }
                    // fail & leave
                    if ($request->$status == 0 && $request->$result == 0){

                        // change the class in student session data
                        $add_new_student_session_data->class_section_id = $update_student->class_section_id;

                        //make user inactive
                        $user = User::find($update_student->user_id);
                        $user->status = 0;
                        $user->save();
                    }

                    // save in added data in student session
                    $add_new_student_session_data->save();
                }
            }
            $response = [
                'error' => false,
                'message' => trans('data_update_successfully')
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

    public function getPromoteData(Request $request) {
        $response = StudentSessions::where(['class_section_id' => $request->class_section_id])->get();
        return response()->json($response);
    }

    public function show(Request $request) {
        if (!Auth::user()->can('promote-student-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');

        $class_section_id = $request->class_section_id;
        $sql = Students::where('class_section_id', $class_section_id)->with('user');

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
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
            $result = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
            <input required type="radio" class="result"  name="result' . $row->id . '" value="1" checked>Pass
            </label></div>';
            $result .= '<div class="form-check-inline"><label class="form-check-label">
            <input type="radio" class="result"  name="result' . $row->id . '" value="0">Fail
            </label></div></div>';

            $status = '<div class="d-flex"><div class="form-check-inline"><label class="form-check-label">
            <input required type="radio" class="status"  name="status' . $row->id . '" value="1" checked>Continue
            </label></div>';
            $status .= '<div class="form-check-inline"><label class="form-check-label">
            <input type="radio" class="status"  name="status' . $row->id . '" value="0">Leave
            </label></div></div>';


            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['student_id'] = "<input type='text' name='student_id[]' class='form-control' readonly value=" . $row->id . ">";
            $tempRow['admission_no'] = $row->admission_no;
            $tempRow['roll_no'] =  $row->student->roll_number ?? null;
            $tempRow['name'] = $row->user->first_name . ' ' . $row->user->last_name;
            $tempRow['result'] = $result;
            $tempRow['status'] = $status;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
