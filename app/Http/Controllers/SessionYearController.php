<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Exam;
use App\Models\FeesPaid;
use App\Models\ExamMarks;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\OnlineExam;
use App\Models\SessionYear;
use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Models\ExamTimetable;
use App\Models\FeesChoiceable;
use App\Models\StudentSubject;
use App\Models\StudentSessions;
use App\Models\PaymentTransaction;
use App\Models\AssignmentSubmission;
use App\Models\InstallmentFee;
use App\Models\PaidInstallmentFee;
use Illuminate\Support\Facades\Auth;

class SessionYearController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index()
    {
        if (!Auth::user()->can('session-year-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);

        }
        return view('session_years.index');
    }

    /**
    * Store a newly created resource in storage.
    *
    * @param \Illuminate\Http\Request $request
    * @return \Illuminate\Http\Response
    */
    public function store(Request $request)
    {
        if (!Auth::user()->can('session-year-create')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $request->validate([
            'name' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'fees_due_date' => 'required|date|after_or_equal:start_date|before_or_equal:end_date',
            'fees_due_charges' => 'required',
            'fees_installment' => 'required|in:0,1',
            'installment_data.*.name' => 'required_if:fees_installment,1|nullable',
            'installment_data.*.due_date' => 'required_if:fees_installment,1|nullable|date|after_or_equal:start_date|before_or_equal:end_date',
            'installment_data.*.due_charges' => 'required_if:fees_installment,1|nullable|numeric|gt:0'
        ],[
            'installment_data.*.name.required_if' => trans('name_is_required_at_row').' :index',

            'installment_data.*.due_date.required_if' => trans('name_is_required_at_row').' :index',
            'installment_data.*.due_date.date' => trans('due_date_should_be_date_at_row').' :index',
            'installment_data.*.due_date.after_or_equal' => trans('due_date_should_be_after_or_equal_session_year_start_date_at_row').' :index',
            'installment_data.*.due_date.before_or_equal' => trans('due_date_should_be_before_or_equal_session_year_end_date_at_row').' :index',

            'installment_data.*.due_charges.required_if' => trans('due_charges_required_at_row').' :index',
            'installment_data.*.due_charges.numeric' => trans('due_charges_should_be_number_at_row').' :index',
        ]);

        try {
            $session_year = new SessionYear();
            $session_year->name = $request->name;
            $session_year->start_date = date('Y-m-d',strtotime($request->start_date));
            $session_year->end_date = date('Y-m-d',strtotime($request->end_date));
            $session_year->include_fee_installments = $request->fees_installment;
            $session_year->fee_due_date = date('Y-m-d',strtotime($request->fees_due_date));
            $session_year->fee_due_charges = $request->fees_due_charges;
            $session_year->save();

            if($request->fees_installment){
                $installment_data_array = array();
                foreach ($request->installment_data as $data) {
                    $installment_data_array[] = array(
                        'name' => $data['name'],
                        'due_date' =>date('Y-m-d',strtotime($data['due_date'])),
                        'due_charges' => $data['due_charges'],
                        'session_year_id' => $session_year->id
                    );
                }
                InstallmentFee::insert($installment_data_array);
            }
            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
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


    public function update(Request $request)
    {
        if (!Auth::user()->can('session-year-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $request->validate([
            'id' => 'required',
            'name' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'fees_due_date' => 'required|date|after_or_equal:start_date|before_or_equal:end_date',
            'fees_due_charges' => 'required|gt:0',
            'installment_data.*.name' => 'required_if:edit_include_fee_installments,1',
            'installment_data.*.due_date' => 'required_if:edit_include_fee_installments,1|date|after_or_equal:start_date|before_or_equal:end_date',
            'installment_data.*.due_charges' => 'required_if:edit_include_fee_installments,1|numeric|gt:0'
        ],[
            'installment_data.*.name.required_if' => trans('name_is_required_at_row').' :index',
            'installment_data.*.due_date.required_if' => trans('name_is_required_at_row').' :index',
            'installment_data.*.due_date.date' => trans('due_date_should_be_date_at_row').' :index',
            'installment_data.*.due_date.after_or_equal' => trans('due_date_should_be_after_or_equal_session_year_start_date_at_row').' :index',
            'installment_data.*.due_date.before_or_equal' => trans('due_date_should_be_before_or_equal_session_year_end_date_at_row').' :index',
            'installment_data.*.due_charges.required_if' => trans('due_charges_required_at_row').' :index',
            'installment_data.*.due_charges.numeric' => trans('due_charges_should_be_number_at_row').' :index',
        ]);

        try {
            $session_year = SessionYear::find($request->id);
            $session_year->name = $request->name;
            $session_year->start_date = date('Y-m-d',strtotime($request->start_date));
            $session_year->end_date = date('Y-m-d',strtotime($request->end_date));
            $session_year->fee_due_date = date('Y-m-d',strtotime($request->fees_due_date));
            $session_year->fee_due_charges = $request->fees_due_charges;
            $session_year->save();

            if(isset($request->installment_data) && !empty($request->installment_data)){
                foreach ($request->installment_data as $data) {
                    if($data['id']){
                        $installment_update = InstallmentFee::findOrFail($data['id']);
                        $installment_update->name = $data['name'];
                        $installment_update->due_date = date('Y-m-d',strtotime($data['due_date']));
                        $installment_update->due_charges = $data['due_charges'];
                        $installment_update->save();
                    }else{
                        $installment_store = new InstallmentFee();
                        $installment_store->name = $data['name'];
                        $installment_store->due_date = date('Y-m-d',strtotime($data['due_date']));
                        $installment_store->due_charges = $data['due_charges'];
                        $installment_store->session_year_id = $request->id;
                        $installment_store->save();
                    }
                }
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
    * Display the specified resource.
    *
    * @param int $id
    * @return \Illuminate\Http\Response
    */
    public function show()
    {
        if (!Auth::user()->can('session-year-list')) {
            $response = array(
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

        $sql = SessionYear::with('fee_installments')->where('id','!=',0);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
            ->orwhere('name', 'LIKE', "%$search%")
            ->orwhere('start_date', 'LIKE', "%$search%")
            ->orwhere('end_date', 'LIKE', "%$search%")
            ->orwhere('default', 'LIKE', "%$search%");
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
            $operate = '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' data-url=' . url('session-years') . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id=' . $row->id . ' data-url=' . url('session-years', $row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';

            $data=getSettings('date_formate');

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['default'] = $row->default;
            $tempRow['start_date'] = date($data['date_formate'],strtotime($row->start_date));
            $tempRow['end_date'] = date($data['date_formate'],strtotime($row->end_date));
            $tempRow['fees_due_date'] = date($data['date_formate'],strtotime($row->fee_due_date));
            $tempRow['fees_due_charges'] = $row->fee_due_charges;
            $tempRow['include_fee_installments'] = $row->include_fee_installments;
            $tempRow['fee_installments'] = $row->fee_installments;
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
        $session_year = SessionYear::find($id);
        return response($session_year);
    }


    /**
    * Remove the specified resource from storage.
    *
    * @param int $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($id)
    {
        if (!Auth::user()->can('session-year-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {

            //check wheather session year id is associated with other table..
            $announcements = Announcement::where('session_year_id',$id)->count();
            $assignment_submissions = AssignmentSubmission::where('session_year_id',$id)->count();
            $assignments = Assignment::where('session_year_id',$id)->count();
            $attendances = Attendance::where('session_year_id',$id)->count();
            $exam_marks = ExamMarks::where('session_year_id',$id)->count();
            $exam_results = ExamResult::where('session_year_id',$id)->count();
            $exam_timetables = ExamTimetable::where('session_year_id',$id)->count();
            $exams = Exam::where('session_year_id',$id)->count();
            $fees_choiceables = FeesChoiceable::where('session_year_id',$id)->count();
            $fees_paids = FeesPaid::where('session_year_id',$id)->count();
            $online_exams = OnlineExam::where('session_year_id',$id)->count();
            $payment_transactions = PaymentTransaction::where('session_year_id',$id)->count();
            $student_sessions = StudentSessions::where('session_year_id',$id)->count();
            $student_subjects = StudentSubject::where('session_year_id',$id)->count();
            $fees_installments = InstallmentFee::where('session_year_id',$id)->count();

            if($announcements || $assignment_submissions || $assignments || $attendances || $exam_marks || $exam_results || $exam_timetables || $exams || $fees_choiceables || $fees_paids || $online_exams || $payment_transactions || $student_sessions || $student_subjects || $fees_installments){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                $year = SessionYear::find($id);
                if($year->default == 1){
                    $response = array(
                        'error' => true,
                        'message' => trans('default_session_year_cannot_delete')
                    );
                }else{
                    $year->delete();
                    $response = [
                        'error' => false,
                        'message' => trans('data_delete_successfully')
                    ];
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

    public function deleteInstallmentData($id){
        if (!Auth::user()->can('session-year-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {
            $installment_data_exists = PaidInstallmentFee::where('installment_fee_id',$id)->count();
            if($installment_data_exists){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                InstallmentFee::where('id',$id)->delete();
                $response = [
                    'error' => false,
                    'message' => trans('data_delete_successfully')
                ];
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
