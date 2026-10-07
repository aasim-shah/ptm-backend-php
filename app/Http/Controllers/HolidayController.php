<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class HolidayController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index()
    {
        if (!Auth::user()->can('holiday-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        return view('holiday.index');
    }
    
    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function store(Request $request)
    {
        if (!Auth::user()->can('holiday-create')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'date' => 'required',
            'title' => 'required',
        ]);
        
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $holiday = new Holiday();
            $holiday->date = date('Y-m-d', strtotime($request->date));
            $holiday->title = $request->title;
            $holiday->description = $request->description;
            $holiday->save();
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
    
    public function update(Request $request)
    {
        if (!Auth::user()->can('holiday-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'date' => 'required',
            'title' => 'required',
        ]);
        
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        try {
            $holiday = Holiday::find($request->id);
            $holiday->date = date('Y-m-d', strtotime($request->date));
            $holiday->title = $request->title;
            $holiday->description = $request->description;
            $holiday->save();
            $response = array(
                'error' => false,
                'message' => trans('data_update_successfully')
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
    
    public function holiday_view()
    {
        return view('holiday.list');
    }
    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show()
    {
        if (!Auth::user()->can('holiday-list')) {
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
        
        $sql = Holiday::where('id','!=',0);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
            ->orwhere('title', 'LIKE', "%$search%")
            ->orwhere('description', 'LIKE', "%$search%")
            ->orwhere('date', 'LIKE', "%$search%");
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
            $operate .= '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            
            $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id=' . $row->id . ' data-url=' . url('holiday', $row->id) . ' title="Delete"><i class="fa fa-trash"></i></a>';
            
            
            $data = getSettings('date_formate');
            
            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['date'] = date($data['date_formate'], strtotime($row->date));
            $tempRow['title'] = $row->title;
            $tempRow['description'] = $row->description;
            
            $tempRow['operate'] = $operate;
            
            $rows[] = $tempRow;
        }
        
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
    
    /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($id)
    {
        if (!Auth::user()->can('holiday-delete')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        try {
            Holiday::find($id)->delete();
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
