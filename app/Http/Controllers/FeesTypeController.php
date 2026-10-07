<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use Dompdf\Dompdf;
use App\Models\Mediums;
use App\Models\FeesPaid;
use App\Models\FeesType;
use App\Models\Settings;
use App\Models\Students;
use App\Models\FeesClass;
use App\Models\ClassSchool;
use App\Models\SessionYear;
use App\Models\ClassSection;
use Illuminate\Http\Request;
use App\Models\FeesChoiceable;
use App\Models\InstallmentFee;
use App\Models\PaidInstallmentFee;
use App\Models\Parents;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FeesTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::user()->can('fees-type')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        return view('fees.fees_types');
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('fees-type')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'nullable',
            // 'choiceable' => 'required|in:0,1'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $fees_type = new FeesType();
            $fees_type->name = $request->name;
            $fees_type->description = $request->description;
            // $fees_type->choiceable = $request->choiceable;
            $fees_type->save();
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Auth::user()->can('fees-type')) {
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

        $sql = FeesType::select('*');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%")
                ->orwhere('description', 'LIKE', "%$search%")
                ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%");
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
            $operate = '<a href="#" class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="'.trans('edit').'" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a href=' . route('fees-type.destroy', $row->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" title="'.trans('delete').'" data-id=' . $row->id . '><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['description'] = $row->description;
            // $tempRow['choiceable'] = $row->choiceable;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->can('fees-type')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'edit_name' => 'required',
            'edit_description' => 'nullable',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $fees_type = FeesType::findOrFail($id);
            $fees_type->name = $request->edit_name;
            $fees_type->description = $request->edit_description;
            $fees_type->save();
            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully'),
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('fees-type')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            // check wheather fees type id is associate with other tables...
            $fees_choiceables = FeesChoiceable::where('fees_type_id',$id)->count();
            $fees_classes = FeesClass::where('fees_type_id',$id)->count();

            if($fees_choiceables || $fees_classes){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                FeesType::findOrFail($id)->delete();
                FeesClass::where('fees_type_id', $id)->delete();
                $response = array(
                    'error' => false,
                    'message' => trans('data_delete_successfully'),
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
    public function feesClassListIndex()
    {
        if (!Auth::user()->can('fees-classes')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $classes = ClassSchool::orderByRaw('CONVERT(name, SIGNED) asc')->with('medium', 'sections')->get();
        $fees_type = FeesType::orderBy('id', 'ASC')->pluck('name', 'id');
        $fees_type_data = FeesType::get();
        $mediums = Mediums::orderBy('id', 'ASC')->get();
        return response(view('fees.fees_class', compact('classes', 'fees_type', 'fees_type_data', 'mediums')));
    }
    public function feesClassList()
    {
        if (!Auth::user()->can('fees-classes')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'id';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';


        $sql = ClassSchool::orderByRaw('CONVERT(name, SIGNED) asc')->with('fees_class', 'medium');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%");
        }
        if (isset($_GET['medium_id']) && !empty($_GET['medium_id'])) {
            $sql = $sql->where('medium_id', $_GET['medium_id']);
        }
        $total = $sql->count();

        $sql->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;

        foreach ($res as $row) {

            $row = (object)$row;
            $operate = '<a href=' . route('class.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="'.trans('edit').'" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

            $tempRow['no'] = $no++;
            $tempRow['class_id'] = $row->id;
            $tempRow['class_name'] = $row->name;
            if (sizeof($row->fees_class)) {
                $total_amount = 0;
                $base_amount = 0;
                $fees_type_table = array();
                foreach ($row->fees_class as $fees_details) {
                    $fees_type_table[] = array(
                        'id' => $fees_details->id,
                        'fees_name' => $fees_details->fees_type->name,
                        'amount' => $fees_details->amount,
                        'choiceable' => $fees_details->choiceable,
                        'fees_type_id' => $fees_details->fees_type->id,
                    );
                    if ($fees_details->choiceable == 0) {
                        $base_amount += $fees_details->amount;
                    }
                    $total_amount += $fees_details->amount;
                }
                $tempRow['fees_type'] = isset($fees_type_table) ? $fees_type_table : ' ';
                $tempRow['base_amount'] = round($base_amount,2);
                $tempRow['total_amount'] = round($total_amount,2);
            } else {
                $tempRow['fees_type'] = [];
                $tempRow['base_amount'] = "-";
                $tempRow['total_amount'] = "-";
            }
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
    public function updateFeesClass(Request $request)
    {
        if (!Auth::user()->can('fees-classes')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validation_rules = array(
            'class_id' => 'required|numeric',
            'edit_fees_type.*.fees_type_id' => 'required',
            'edit_fees_type.*.amount' => 'required:edit_fees_type',
            'edit_fees_type.*.choiceable' => 'required|in:0,1'
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
            // //Update Fees Type For Class first
            if ($request->edit_fees_type) {
                foreach ($request->edit_fees_type as $row) {
                    $edit_fees_type = FeesClass::findOrFail($row['fees_class_id']);
                    $edit_fees_type->fees_type_id = $row['fees_type_id'];
                    $edit_fees_type->amount = $row['amount'];
                    $edit_fees_type->choiceable = $row['choiceable'];
                    $edit_fees_type->update();
                }
            }

            //Add New Fees Type For Class
            if ($request->fees_type) {
                $fees_type = array();
                foreach ($request->fees_type as $row) {
                    $fees_type[] = array(
                        'class_id' => $request->class_id,
                        'fees_type_id' => $row['fees_type_id'],
                        'choiceable'=>$row['choiceable'],
                        'amount' => $row['amount'],
                    );
                }
                FeesClass::insert($fees_type);
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
    public function removeFeesClass($id)
    {
        if (!Auth::user()->can('fees-classes')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $fees_class = FeesClass::where('id',$id)->first();

            //check wheather the fees class is associated with other table..
            $fees_choiceable = FeesChoiceable::where(['class_id' => $fees_class->class_id , 'fees_type_id' => $fees_class->fees_type_id])->count();
            if($fees_choiceable){
                $response = array(
                    'error' => true,
                    'message' => trans('cannot_delete_beacuse_data_is_associated_with_other_data')
                );
            }else{
                $fees_type_class = FeesClass::findOrFail($id);
                $fees_type_class->delete();
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

    public function feesPaidListIndex()
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $classes = ClassSchool::orderByRaw('CONVERT(name, SIGNED) asc')->with('medium', 'sections')->get();
        $session_year_all = SessionYear::select('id', 'name', 'default')->get();
        $mediums = Mediums::orderBy('id', 'ASC')->get();
        return response(view('fees.fees_paid', compact('classes', 'mediums','session_year_all')));
    }

    public function feesPaidList()
    {
        if (!Auth::user()->can('fees-paid')) {
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


        //Fetching Students Data on Basis of Class Section ID with Realtion fees paid
        $sql = Students::with(['user:id,first_name,last_name','fees_paid','class_section']);
        $session_year = getSettings('session_year');
        $session_year_id = $session_year['session_year'];

        if (isset($_GET['session_year_id']) && !empty($_GET['session_year_id'])) {
            $sql->whereHas('fees_paid', function ($q) {
                $q->where('session_year_id', $_GET['session_year_id']);
            });
        }

        if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
            $class_id = $_GET['class_id'];
            $class_section_id = ClassSection::where('class_id', $class_id)->pluck('id');
            $sql->whereIn('class_section_id', $class_section_id)
                ->with(['fees_paid' => function ($q) use ($session_year_id) {
                    $q->with('class', 'session_year', 'payment_transaction')
                        ->where('session_year_id', $session_year_id);
                }]);
        } else {
            $sql->has('fees_paid');
        }

        if (isset($_GET['mode']) && ($_GET['mode'] == 0 || $_GET['mode'] == 1 || $_GET['mode'] == 2)) {
            $sql->whereHas('fees_paid', function ($q) {
                $q->where('mode', $_GET['mode']);
            });
        }

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', "%$search%")
                            ->orWhere('last_name', 'LIKE', "%$search%");
                    });
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
            $operate = "";
            $session_year = SessionYear::where('id',$session_year_id)->first();
            $due_date = $session_year->fee_due_date;

            $current_date = Carbon::now()->format('d-m-Y');
            $due_charges = 0;
            $base_amount_with_due_charges = 0;

            $payment_transaction = null;

            if($row->fees_paid){
                $payment_transaction = PaymentTransaction::where(['student_id' => $row->id , 'class_id' => $row->class_section->class_id , 'session_year_id' => $session_year_id])->latest()->first();
            }

            // Base Amount
            $base_amount = FeesClass::where(['class_id' => $row->class_section->class_id , 'choiceable' => 0])->selectRaw('SUM(amount) as base_amount')->first();
            $base_amount = $base_amount['base_amount'];

            // if due charges is applicable
            if (strtotime($current_date) > strtotime($due_date)) {
                $due_charges = $session_year->fee_due_charges;
                $charges = (($due_charges) * ($base_amount) / 100);
                $base_amount_with_due_charges = $base_amount + $charges;
            }

            // Get the fees data
            $compulsory_fees = FeesClass::where(['class_id' => $row->class_section->class_id , 'choiceable' => 0])->with('fees_type')->get();
            $choiceable_fees = FeesClass::where(['class_id' => $row->class_section->class_id , 'choiceable' => 1])->with('fees_type')->get();
            $installment_data = InstallmentFee::where('session_year_id',$session_year_id)->get();

            //Get Paid Fees

            $operate = "";
            // check that fees paid is not empty
            if(isset($row->fees_paid) && !empty($row->fees_paid)){
                // checks that fees paid's session year matches the current session year then allow to modify the fees payments or else show only clear and pdf option
                if($row->fees_paid->session_year_id == $session_year_id){
                    $operate = '<div class="dropdown"><button class="btn btn-xs btn-gradient-success btn-rounded btn-icon dropdown-toggle" type="button" data-toggle="dropdown"><i class="fa fa-dollar"></i></button><div class="dropdown-menu">';
                    $operate .= '<a href="#"class="compulsory-data dropdown-item" data-id=' . $row->id . ' title="' . trans('compulsory') . ' ' . trans('fees') . '" data-toggle="modal" data-target="#compulsoryModal"><i class="fa fa-dollar text-success mr-2"></i>'.trans('compulsory').' '.trans('fees').'</a><div class="dropdown-divider"></div>';
                    $operate .= '<a href="#" class="optional-data dropdown-item" data-id=' . $row->id . ' title="' . trans('optional') . ' ' . trans('fees') . '" data-toggle="modal" data-target="#optionalModal"><i class="fa fa-dollar text-success mr-2"></i>'.trans('optional').' '.trans('fees').'</a>';
                    $operate .= '</div></div>&nbsp;&nbsp;';
                    $operate .= '<a href=' . route('fees.paid.clear.data', $row->fees_paid->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" title="'.trans('clear').'" data-id=' . $row->fees_paid->id . '><i class="fa fa-remove"></i></a>&nbsp;&nbsp;';
                    $operate .= '<a href=' . route('fees.paid.receipt.pdf', $row->fees_paid->id) . ' class="btn btn-xs btn-gradient-info btn-rounded btn-icon generate-paid-fees-pdf" target="_blank" data-id=' . $row->fees_paid->id . ' title="' . trans('generate_pdf') . ' ' . trans('fees') . '"><i class="fa fa-file-pdf-o"></i></a>&nbsp;&nbsp;';
                }else{
                    $operate .= '<a href=' . route('fees.paid.clear.data', $row->fees_paid->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" title="'.trans('clear').'" data-id=' . $row->fees_paid->id . '><i class="fa fa-remove"></i></a>&nbsp;&nbsp;';
                    $operate .= '<a href=' . route('fees.paid.receipt.pdf', $row->fees_paid->id) . ' class="btn btn-xs btn-gradient-info btn-rounded btn-icon generate-paid-fees-pdf" target="_blank" data-id=' . $row->fees_paid->id . ' title="' . trans('generate_pdf') . ' ' . trans('fees') . '"><i class="fa fa-file-pdf-o"></i></a>&nbsp;&nbsp;';
                }
            }else{
                $operate = '<div class="dropdown"><button class="btn btn-xs btn-gradient-success btn-rounded btn-icon dropdown-toggle" type="button" data-toggle="dropdown"><i class="fa fa-dollar"></i></button><div class="dropdown-menu">';
                $operate .= '<a href="#"class="compulsory-data dropdown-item" data-id=' . $row->id . ' title="' . trans('compulsory') . ' ' . trans('fees') . '" data-toggle="modal" data-target="#compulsoryModal"><i class="fa fa-dollar text-success mr-2"></i>'.trans('compulsory').' '.trans('fees').'</a><div class="dropdown-divider"></div>';
                $operate .= '<a href="#" class="optional-data dropdown-item" data-id=' . $row->id . ' title="' . trans('optional') . ' ' . trans('fees') . '" data-toggle="modal" data-target="#optionalModal"><i class="fa fa-dollar text-success mr-2"></i>'.trans('optional').' '.trans('fees').'</a>';
                $operate .= '</div></div>&nbsp;&nbsp;';
            }

            $tempRow['id'] = null;
            $tempRow['student_id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['father_id'] = $row->father_id;
            $tempRow['mother_id'] = $row->mother_id;
            $tempRow['student_name'] = $row->user->first_name . ' ' . $row->user->last_name;
            $tempRow['class_id'] = $row->class_section->class_id;
            $tempRow['class_name'] = $row->class_section->class->name;
            $tempRow['compulsory_fees'] = sizeof($compulsory_fees) ? $compulsory_fees : null;

            $paid_installment_data = PaidInstallmentFee::where(['student_id' => $row->id, 'session_year_id' => $session_year_id])->first();
            $tempRow['is_installment_paid'] = $paid_installment_data ? 1 : 0;

            if(isset($installment_data) && !empty($installment_data)) {
                $tempRow['installment_data'] = array();
                foreach ($installment_data as $data) {
                    // Paid Installment Data
                    $paid_installment = PaidInstallmentFee::where(['student_id' => $row->id, 'installment_fee_id' => $data->id])->first();

                    if(strtotime($current_date) >= strtotime($data->due_date)){
                        $tempRow['installment_data'][] = array(
                            'id' => $data->id,
                            'name' => $data->name,
                            'due_date' => $data->due_date,
                            'due_charges' => $data->due_charges,
                            'due_charges_applicable' => 1,
                            'paid' => $paid_installment ? 1 : 0 ,
                            'paid_id' => $paid_installment ? $paid_installment->id : '',
                            'paid_on' => $paid_installment ? date('d-m-Y',strtotime($paid_installment->date)) : '',
                        );
                    }else{
                        $tempRow['installment_data'][] = array(
                            'id' => $data->id,
                            'name' => $data->name,
                            'due_date' => $data->due_date,
                            'due_charges' => $data->due_charges,
                            'due_charges_applicable' => 0,
                            'paid' => $paid_installment ? 1 : 0 ,
                            'paid_id' => $paid_installment ? $paid_installment->id : '',
                            'paid_on' => $paid_installment ? $paid_installment->date : '',
                        );
                    }
                }
            }
            if(isset($choiceable_fees) && !empty($choiceable_fees)) {
                $tempRow['choiceable_fees'] = array();
                $paid_choiceable_fees_query = FeesChoiceable::where(['class_id' => $row->class_section->class_id , 'student_id' => $row->id , 'session_year_id' => $session_year_id]);
                foreach ($choiceable_fees as $data) {
                    //Clone the Query To Avoid Extra Addition of Where Fees Type ID
                    $paid_choiceable_data = (clone $paid_choiceable_fees_query)->where('fees_type_id', $data->fees_type_id);
                    if($paid_choiceable_data->count()){
                        $tempRow['choiceable_fees'][] = array(
                            'id' => $data->id,
                            'name' => $data->fees_type->name,
                            'class_id' => $data->class_id,
                            'fees_type_id' => $data->fees_type_id,
                            'choiceable' => $data->choiceable,
                            'amount' => $data->amount,
                            'is_paid' => 1,
                            'paid_id' => $paid_choiceable_data->first()->id,
                            'date' => $paid_choiceable_data->first()->date,
                        );
                    }else{
                        $tempRow['choiceable_fees'][] = array(
                            'id' => $data->id,
                            'name' => $data->fees_type->name,
                            'class_id' => $data->class_id,
                            'fees_type_id' => $data->fees_type_id,
                            'choiceable' => $data->choiceable,
                            'amount' => $data->amount,
                            'is_paid' => 0,
                        );
                    }
                }
            }
            $tempRow['base_amount'] = $base_amount;
            $tempRow['base_amount_with_due_charges'] = $base_amount_with_due_charges;
            $tempRow['due_charges'] = array(
                'date' => date('d-m-Y',strtotime($due_date)),
                'charges' => $charges ?? null,
            );
            $tempRow['fees_status'] = $row->fees_paid->is_fully_paid ?? null;
            $tempRow['total_fees'] = $row->fees_paid->total_amount ?? null;
            $tempRow['current_date'] = $current_date;
            $tempRow['date'] = $row->fees_paid->date ?? null;
            $tempRow['session_year_name'] = $row->fees_paid->session_year->name ?? null;
            $tempRow['mode'] = $payment_transaction->mode ?? null;
            $tempRow['type_of_fee'] = $payment_transaction->type_of_fee ?? null;
            $tempRow['cheque_no'] = $payment_transaction->cheque_no ?? null;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function clearFeesPaidData($id)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $fees_paid_data = FeesPaid::find($id);

            // get the ids from fees paid to remove the fees choiced data
            $student_id = $fees_paid_data->student_id;
            $class_id = $fees_paid_data->class_id;
            $session_year_id = $fees_paid_data->session_year_id;

            $fees_paid_data->delete();

            FeesChoiceable::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->delete();
            PaidInstallmentFee::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->delete();
            PaymentTransaction::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->delete();

            $response = array(
                'error' => false,
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
    public function feesConfigIndex()
    {
        if (!Auth::user()->can('fees-config')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $settings = getSettings();
        $domain =  request()->getSchemeAndHttpHost();
        return view('fees.fees_config', compact('settings', 'domain'));
    }

    public function feesConfigUpdate(Request $request)
    {
        if (!Auth::user()->can('fees-config')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'razorpay_status' => 'required',
            'razorpay_secret_key' => 'required_if:razorpay_status,1|nullable',
            'razorpay_api_key' => 'required_if:razorpay_status,1|nullable',
            'razorpay_webhook_secret' => 'required_if:razorpay_status,1|nullable',
            'razorpay_webhook_url' => 'required_if:razorpay_status,1|nullable',
            'stripe_status' => 'required',
            'stripe_publishable_key' => 'required_if:stripe_status,1|nullable',
            'stripe_secret_key' => 'required_if:stripe_status,1|nullable',
            'stripe_webhook_secret' => 'required_if:stripe_status,1|nullable',
            'stripe_webhook_url' => 'required_if:stripe_status,1|nullable',
            'currency_code' => 'required',
            'currency_symbol' => 'required',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {

            //razorpay_status
            if (Settings::where('type', 'razorpay_status')->exists()) {
                $data = [
                    'message' => $request->razorpay_status
                ];
                Settings::where('type', 'razorpay_status')->update($data);
            } else {
                $setting = new Settings();
                $setting->type = 'razorpay_status';
                $setting->message = $request->razorpay_status;
                $setting->save();
            }

            if ($request->razorpay_status) {
                //razorpay_secret_key
                if (Settings::where('type', 'razorpay_secret_key')->exists()) {
                    $data = [
                        'message' => trim($request->razorpay_secret_key)
                    ];
                    Settings::where('type', 'razorpay_secret_key')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'razorpay_secret_key';
                    $setting->message = trim($request->razorpay_secret_key);
                    $setting->save();
                }

                //razorpay_api_key
                if (Settings::where('type', 'razorpay_api_key')->exists()) {
                    $data = [
                        'message' => trim($request->razorpay_api_key)
                    ];
                    Settings::where('type', 'razorpay_api_key')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'razorpay_api_key';
                    $setting->message = trim($request->razorpay_api_key);
                    $setting->save();
                }

                //razorpay_webhook_secret
                if (Settings::where('type', 'razorpay_webhook_secret')->exists()) {
                    $data = [
                        'message' => trim($request->razorpay_webhook_secret)
                    ];
                    Settings::where('type', 'razorpay_webhook_secret')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'razorpay_webhook_secret';
                    $setting->message = trim($request->razorpay_webhook_secret);
                    $setting->save();
                }

                //razorpay_webhook_url
                if (Settings::where('type', 'razorpay_webhook_url')->exists()) {
                    $data = [
                        'message' => trim($request->razorpay_webhook_url)
                    ];
                    Settings::where('type', 'razorpay_webhook_url')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'razorpay_webhook_url';
                    $setting->message = trim($request->razorpay_webhook_url);
                    $setting->save();
                }

                $env_update = changeEnv([
                    'RAZORPAY_SECRET_KEY' => trim($request->razorpay_secret_key),
                    'RAZORPAY_API_KEY' => trim($request->razorpay_api_key),
                    'RAZORPAY_WEBHOOK_SECRET' => trim($request->razorpay_webhook_secret),
                    'RAZORPAY_WEBHOOK_URL' => trim($request->razorpay_webhook_url),
                ]);

                if ($env_update) {
                    $response = array(
                        'error' => false,
                        'message' => trans('data_update_successfully'),
                    );
                }
            }


            //stripe_status
            if (Settings::where('type', 'stripe_status')->exists()) {
                $data = [
                    'message' => $request->stripe_status
                ];
                Settings::where('type', 'stripe_status')->update($data);
            } else {
                $setting = new Settings();
                $setting->type = 'stripe_status';
                $setting->message = $request->stripe_status;
                $setting->save();
            }

            if ($request->stripe_status) {

                //stripe_publishable_key
                if (Settings::where('type', 'stripe_publishable_key')->exists()) {
                    $data = [
                        'message' => trim($request->stripe_publishable_key)
                    ];
                    Settings::where('type', 'stripe_publishable_key')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'stripe_publishable_key';
                    $setting->message = trim($request->stripe_publishable_key);
                    $setting->save();
                }

                //stripe_secret_key
                if (Settings::where('type', 'stripe_secret_key')->exists()) {
                    $data = [
                        'message' => trim($request->stripe_secret_key)
                    ];
                    Settings::where('type', 'stripe_secret_key')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'stripe_secret_key';
                    $setting->message = trim($request->stripe_secret_key);
                    $setting->save();
                }

                //stripe_webhook_secret
                if (Settings::where('type', 'stripe_webhook_secret')->exists()) {
                    $data = [
                        'message' => trim($request->stripe_webhook_secret)
                    ];
                    Settings::where('type', 'stripe_webhook_secret')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'stripe_webhook_secret';
                    $setting->message = trim($request->stripe_webhook_secret);
                    $setting->save();
                }

                //stripe_webhook_url
                if (Settings::where('type', 'stripe_webhook_url')->exists()) {
                    $data = [
                        'message' => trim($request->stripe_webhook_url)
                    ];
                    Settings::where('type', 'stripe_webhook_url')->update($data);
                } else {
                    $setting = new Settings();
                    $setting->type = 'stripe_webhook_url';
                    $setting->message = trim($request->stripe_webhook_url);
                    $setting->save();
                }

                $env_update = changeEnv([
                    'STRIPE_PUBLISHABLE_KEY' => trim($request->stripe_publishable_key),
                    'STRIPE_SECRET_KEY' => trim($request->stripe_secret_key),
                    'STRIPE_WEBHOOK_SECRET' => trim($request->stripe_webhook_secret),
                    'STRIPE_WEBHOOK_URL' => trim($request->stripe_webhook_url),
                ]);

                if ($env_update) {
                    $response = array(
                        'error' => false,
                        'message' => trans('data_update_successfully'),
                    );
                }
            }

            //currency_code
            if (Settings::where('type', 'currency_code')->exists()) {
                $data = [
                    'message' => $request->currency_code
                ];
                Settings::where('type', 'currency_code')->update($data);
            } else {
                $setting = new Settings();
                $setting->type = 'currency_code';
                $setting->message = $request->currency_code;
                $setting->save();
            }

            //currency_symbol
            if (Settings::where('type', 'currency_symbol')->exists()) {
                $data = [
                    'message' => $request->currency_symbol
                ];
                Settings::where('type', 'currency_symbol')->update($data);
            } else {
                $setting = new Settings();
                $setting->type = 'currency_symbol';
                $setting->message = $request->currency_symbol;
                $setting->save();
            }

            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully'),
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function feesTransactionsLogsIndex(Request $request)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $session_year_all = SessionYear::select('id', 'name', 'default')->get();
        $classes = ClassSchool::orderByRaw('CONVERT(name, SIGNED) asc')->with('medium', 'sections')->get();
        $mediums = Mediums::orderBy('id', 'ASC')->get();
        return response(view('fees.fees_transaction_logs', compact('classes', 'mediums', 'session_year_all')));
    }
    public function feesTransactionsLogsList(Request $request)
    {
        if (!Auth::user()->can('fees-paid')) {
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
        //Fetching Students Data on Basis of Class Section ID with Realtion fees paid
        $sql = PaymentTransaction::with('student', 'session_year');

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('order_id', 'LIKE', "%$search%")
                ->orwhere('payment_id', 'LIKE', "%$search%")
                ->orWhereHas('student.user', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%$search%")->orWhere('last_name', 'LIKE', "%$search%");
                });
        }
        if (isset($_GET['session_year_id']) && !empty($_GET['session_year_id'])) {
            $sql = $sql->where('session_year_id', $_GET['session_year_id']);
        }
        if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
            $class_id = $_GET['class_id'];
            $sql = $sql->where('class_id', $class_id);
        }
        if (isset($_GET['payment_status']) && $_GET['payment_status'] == 0 || $_GET['payment_status'] == 1 || $_GET['payment_status'] == 2 ) {
            $sql->where('payment_status',$_GET['payment_status']);
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
            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['student_id'] = $row->student_id;
            $tempRow['student_name'] = $row->student->user->first_name . ' ' . $row->student->user->last_name;
            $tempRow['total_fees'] = $row->total_amount;
            $tempRow['payment_gateway'] = $row->payment_gateway;
            $tempRow['payment_status'] = $row->payment_status;
            $tempRow['order_id'] = $row->order_id;
            $tempRow['mode'] = $row->mode;
            $tempRow['cheque_no'] = $row->cheque_no;
            $tempRow['payment_id'] = $row->payment_id;
            $tempRow['payment_signature'] = $row->payment_signature;
            $tempRow['session_year_id'] = $row->session_year_id;
            $tempRow['session_year_name'] = $row->session_year->name;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function feesPaidReceiptPDF($id)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $logo = config('environment.LOGO2');
            $logo = public_path('/storage/' . $logo);
            $school_name = config('environment.APP_NAME');
            $school_address = getSettings('school_address');
            $school_address = $school_address['school_address'];
            $currency_symbol = getSettings('currency_symbol');
            if (isset($currency_symbol) && sizeof($currency_symbol)) {
                $currency_symbol = $currency_symbol['currency_symbol'];
            } else {
                $currency_symbol = null;
            }

            //Getting the Fees Paid Data
            $fees_paid = FeesPaid::where('id', $id)->with('student.user:id,first_name,last_name', 'class', 'session_year')->get()->first();

            // Variables
            $student_id = $fees_paid->student_id;
            $class_id = $fees_paid->class_id;
            $session_year_id = $fees_paid->session_year_id;

            $optional_fees_type_id = FeesClass::where(['class_id' => $class_id ,'choiceable' => 1])->pluck('fees_type_id');

            // Paid Installment Data
            $paid_installment = PaidInstallmentFee::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->with('installment_fee')->get();

            //Fees Choiceable Data
            $fees_choiceable = FeesChoiceable::whereIn('fees_type_id',$optional_fees_type_id)->where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id])->with('fees_type')->orderby('id', 'asc')->get();

            //Fees Class Data
            $fees_class = FeesClass::where(['class_id' => $class_id ,'choiceable' => 0])->with('fees_type')->get();

            //Session Year Data
            $session_year = SessionYear::where('id',$session_year_id)->first();

            $pdf = Pdf::loadView('fees.fees_receipt', compact('logo', 'school_name', 'fees_paid' , 'paid_installment', 'fees_choiceable', 'currency_symbol', 'school_address' ,'fees_class' , 'session_year'));
            return $pdf->stream('fees-receipt.pdf');
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
            );
            return response()->json($response);
        }
    }

    // Pay Optional Fees Offline
    public function optionalFeesPaidStore(Request $request)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'mode' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $date = date('Y-m-d H:i:s', strtotime($request->date));
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // Get the Father Id for Payment Transaction Table
            $father_id = Students::where('id',$request->student_id)->pluck('father_id')->first();

            // Add Data in Payment Transaction Of Optional Payment Transaction
            $payment_transaction_store = new PaymentTransaction();
            $payment_transaction_store->student_id = $request->student_id;
            $payment_transaction_store->class_id = $request->class_id;
            $payment_transaction_store->parent_id = $father_id;
            $payment_transaction_store->mode = $request->mode;
            $payment_transaction_store->cheque_no = (isset($request->cheque_no) && !empty($request->cheque_no) && $request->mode == 1) ? $request->cheque_no : null;
            $payment_transaction_store->type_of_fee = 2;
            $payment_transaction_store->payment_status = 1;
            $payment_transaction_store->total_amount = $request->total_amount;
            $payment_transaction_store->session_year_id = $session_year_id;
            $payment_transaction_store->save();


            // Add Data in Array of Optional Fees
            $optional_fees_store = array();
            foreach($request->optional_fees_type_data as $fees_type_data){
                if(isset($fees_type_data['id']) && !empty($fees_type_data['id'])){
                    $optional_fees_store[] = array(
                        'student_id' => $request->student_id,
                        'class_id' => $request->class_id,
                        'fees_type_id' => $fees_type_data['id'],
                        'is_due_charges' => 0,
                        'total_amount' => $fees_type_data['amount'],
                        'session_year_id' => $session_year_id,
                        'date' => $date,
                        'payment_transaction_id' => $payment_transaction_store->id
                    );
                }
            }

            // Add Data in Fees Choiceable Of Optional Payment
            FeesChoiceable::insert($optional_fees_store);

            // Add Data in Fees Paid Of Optional Payment Transaction
            $update_fees_paid_query = FeesPaid::where(['student_id' => $request->student_id,'class_id' => $request->class_id,'session_year_id' => $session_year_id]);

            $fees_paid = $update_fees_paid_query->firstOrNew();
            $fees_paid->total_amount += $request->total_amount;
            $fees_paid->date = $date;
            if (!$fees_paid->exists) {
                $fees_paid->student_id = $request->student_id;
                $fees_paid->class_id = $request->class_id;
                $fees_paid->is_fully_paid = 0;
                $fees_paid->session_year_id = $session_year_id;
            }
            $fees_paid->save();

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
    public function feesPaidRemoveChoiceableFees($id)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $fees_choiceable = FeesChoiceable::find($id);

            //Get Detials
            $student_id = $fees_choiceable->student_id;
            $class_id = $fees_choiceable->class_id;
            $session_year_id = $fees_choiceable->session_year_id;
            $fees_choiceable_amount = $fees_choiceable->total_amount;
            $fees_payment_transaction_id = $fees_choiceable->payment_transaction_id;

            // Delete Fees Choiceable Entry
            $fees_choiceable->delete();

            // Check the Payment Transaction ID Is Not Null
            if(isset($fees_payment_transaction_id) && !empty($fees_payment_transaction_id)){
                // Check the Payment Transaction Entry
                $fees_payment_db = PaymentTransaction::where('id',$fees_payment_transaction_id);
                // Get the Payment Transaction Amount
                $fees_transaction_amount = $fees_payment_db->pluck('total_amount')->first();

                // Reduce the amount of Deleted Choiceable Fees in Payment Transaction
                $updated_transaction_fees_amount = $fees_transaction_amount  - $fees_choiceable_amount;

                // If Updated Fees Amount is not Zero then update the Total Amount Else Delete the entry
                if($updated_transaction_fees_amount != 0){
                    $fees_transaction_update = PaymentTransaction::find($fees_payment_transaction_id);
                    $fees_transaction_update->total_amount = $updated_transaction_fees_amount;
                    $fees_transaction_update->save();
                }else{
                    $fees_transaction_update = PaymentTransaction::where('id',$fees_payment_transaction_id)->delete();
                }
            }


            // Check the Fees Paid Entry
            $fees_paid_db = FeesPaid::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id]);
            // Get the Fees Paid ID
            $fees_paid_id = $fees_paid_db->pluck('id')->first();
            // Get the Fees Paid Amount
            $fees_paid_amount = $fees_paid_db->pluck('total_amount')->first();

            // Reduce the amount of Deleted Choiceable Fees in Fees Paid
            $updated_fees_paid_amount = $fees_paid_amount  - $fees_choiceable_amount;

            // If Updated Fees Amount is not Zero then update the Total Amount Else Delete the entry
            if($updated_fees_paid_amount != 0){
                $fees_paid_update = FeesPaid::find($fees_paid_id);
                $fees_paid_update->total_amount = $updated_fees_paid_amount;
                $fees_paid_update->save();
            }else{
                $fees_paid_update = FeesPaid::where('id',$fees_paid_id)->delete();
            }

            $response = array(
                'error' => false,
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

    public function compulsoryFeesPaidStore(Request $request){
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'mode' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $date = date('Y-m-d H:i:s', strtotime($request->date));
            $session_year = getSettings('session_year');
            $session_year_id = $session_year['session_year'];

            // Get the Father Id for Payment Transaction Table
            $father_id = Students::where('id',$request->student_id)->pluck('father_id')->first();

            // Add Data in Payment Transaction Of Optional Payment Transaction
            $payment_transaction_store = new PaymentTransaction();
            $payment_transaction_store->student_id = $request->student_id;
            $payment_transaction_store->class_id = $request->class_id;
            $payment_transaction_store->parent_id = $father_id;
            $payment_transaction_store->mode = $request->mode;
            $payment_transaction_store->cheque_no = (isset($request->cheque_no) && !empty($request->cheque_no)) ? $request->cheque_no : null;
            $payment_transaction_store->type_of_fee = isset($request->installment_fees) && !empty($request->installment_fees) ? 1 : 0;
            $payment_transaction_store->payment_status = 1;
            $payment_transaction_store->date = $date;
            $payment_transaction_store->total_amount = $request->total_amount;
            $payment_transaction_store->session_year_id = $session_year_id;
            $payment_transaction_store->save();


            // Add Data in Array of Optional Fees
            $installment_fees_store = array();
            foreach($request->installment_fees as $data){
                if(isset($data['id']) && !empty($data['id'])){
                    $installment_fees_store[] = array(
                        'student_id' => $request->student_id,
                        'parent_id' => $father_id,
                        'class_id' => $request->class_id,
                        'installment_fee_id' => $data['id'],
                        'amount' => $data['amount'],
                        'session_year_id' => $session_year_id,
                        'date' => $date,
                        'due_charges' => $data['due_charges'] ?? null,
                        'payment_transaction_id' => $payment_transaction_store->id
                    );
                    $is_fully_paid = $data['fully_paid'];
                }
            }

            // Add Data in Fees Choiceable Of Optional Payment
            PaidInstallmentFee::insert($installment_fees_store);


            if($request->installment_mode == 0){
                $is_fully_paid = 1;
            }
            // Add Data in Fees Paid Of Optional Payment Transaction
            $update_fees_paid_query = FeesPaid::where(['student_id' => $request->student_id,'class_id' => $request->class_id,'session_year_id' => $session_year_id]);

            $fees_paid = $update_fees_paid_query->firstOrNew();
            $fees_paid->total_amount += $request->total_amount;
            $fees_paid->date = $date;
            $fees_paid->is_fully_paid = $is_fully_paid;
            if (!$fees_paid->exists) {
                $fees_paid->student_id = $request->student_id;
                $fees_paid->class_id = $request->class_id;
                $fees_paid->session_year_id = $session_year_id;
            }
            $fees_paid->save();

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
    public function feesPaidRemoveInstallmentFees($id)
    {
        if (!Auth::user()->can('fees-paid')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $paid_installment_fee_db = PaidInstallmentFee::find($id);

            //Get Detials
            $student_id = $paid_installment_fee_db->student_id;
            $class_id = $paid_installment_fee_db->class_id;
            $session_year_id = $paid_installment_fee_db->session_year_id;
            $installment_fee_amount = $paid_installment_fee_db->amount;
            $fees_payment_transaction_id = $paid_installment_fee_db->payment_transaction_id;

            // Delete Fees Installment Paid Entry
            $paid_installment_fee_db->delete();

            // Check the Payment Transaction ID Is Not Null
            if(isset($fees_payment_transaction_id) && !empty($fees_payment_transaction_id)){
                // Check the Payment Transaction Entry
                $fees_payment_db = PaymentTransaction::where('id',$fees_payment_transaction_id);
                // Get the Payment Transaction Amount
                $fees_transaction_amount = $fees_payment_db->pluck('total_amount')->first();

                // Reduce the amount of Deleted Choiceable Fees in Payment Transaction
                $updated_transaction_fees_amount = $fees_transaction_amount  - $installment_fee_amount;

                // If Updated Fees Amount is not Zero then update the Total Amount Else Delete the entry
                if($updated_transaction_fees_amount != 0){
                    $fees_transaction_update = PaymentTransaction::find($fees_payment_transaction_id);
                    $fees_transaction_update->total_amount = $updated_transaction_fees_amount;
                    $fees_transaction_update->save();
                }else{
                    $fees_transaction_update = PaymentTransaction::where('id',$fees_payment_transaction_id)->delete();
                }
            }


            // Check the Fees Paid Entry
            $fees_paid_db = FeesPaid::where(['student_id' => $student_id, 'class_id' => $class_id, 'session_year_id' => $session_year_id]);
            // Get the Fees Paid ID
            $fees_paid_id = $fees_paid_db->pluck('id')->first();
            // Get the Fees Paid Amount
            $fees_paid_amount = $fees_paid_db->pluck('total_amount')->first();

            // Reduce the amount of Deleted Choiceable Fees in Fees Paid
            $updated_fees_paid_amount = $fees_paid_amount  - $installment_fee_amount;

            // If Updated Fees Amount is not Zero then update the Total Amount Else Delete the entry
            if($updated_fees_paid_amount != 0){
                $fees_paid_update = FeesPaid::find($fees_paid_id);
                $fees_paid_update->total_amount = $updated_fees_paid_amount;
                $fees_paid_update->is_fully_paid = 0;
                $fees_paid_update->save();
            }else{
                $fees_paid_update = FeesPaid::where('id',$fees_paid_id)->delete();
            }

            $response = array(
                'error' => false,
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
}
