<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Throwable;

class LanguageController extends Controller
{
    public function index() {
        if (!Auth::user()->can('language-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        return view('settings.language_setting');
    }

    public function language_sample() {
        if (!Auth::user()->can('language-create')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $filePath = base_path("resources/lang/en.json");
        $headers = ['Content-Type: application/json'];
        $fileName = 'language.json';
        if (File::exists(base_path("resources/lang/en.json"))) {
            return response()->download($filePath, $fileName, $headers);
        } else {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
            return response()->json($response);
        }
    }

    public function store(Request $request) {
        if (!Auth::user()->can('language-create')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }

        $request->validate([
            'name' => 'required',
            'code' => 'required',
            'file' => 'required|mimes:json',
        ]);

        try {
            $language = new Language();
            $language->name = $request->name;
            $language->code = $request->code;
            $language->status = 0;
            if(isset($request->rtl)){
                $language->is_rtl = $request->rtl;
            }else{
                $language->is_rtl = 0;
            }
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = $request->code . '.' . $file->getClientOriginalExtension();
                $file->move(base_path('resources/lang/'), $filename);
                $language->file = $filename;
            }
            $language->save();


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

    public function show() {
        if (!Auth::user()->can('language-list')) {
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

        $sql = Language::where('id', '!=', 0);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%")
                ->orwhere('code', 'LIKE', "%$search%")
                ->orwhere('status', 'LIKE', "%$search%");
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
            $operate = '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a class="btn btn-xs btn-gradient-danger btn-rounded btn-icon deletedata" data-id=' . $row->id . '" data-url="' . url('language', $row->id) . '" title="Delete"><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['code'] = $row->code;
            $tempRow['rtl'] = $row->is_rtl;
            $tempRow['status'] = $row->status;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function update(Request $request) {
        if (!Auth::user()->can('language-edit')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $request->validate([
            'name' => 'required',
            'code' => 'required'
        ]);

        try {
            $language = Language::find($request->id);
            $language->name = $request->name;
            $language->code = $request->code;
            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => 'required|mimes:json',
                ]);
                if (File::exists(base_path("resources/lang/") . $language->file)) {
                    File::delete(base_path("resources/lang/") . $language->file);
                }
                $file = $request->file('file');
                $filename = $request->code . '.' . $file->getClientOriginalExtension();
                $file->move(base_path('resources/lang/'), $filename);
                $language->file = $filename;
            }
            if($request->rtl){
                $language->is_rtl = 1;
            }else{
                $language->is_rtl = 0;
            }
            $language->save();
            $response = [
                'error' => false,
                'message' => trans('data_update_successfully'),
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

    public function destroy($id) {
        if (!Auth::user()->can('language-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            Language::find($id)->delete();
            $response = [
                'error' => false,
                'message' => trans('data_delete_successfully')
            ];
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }

    public function set_language(Request $request) {
        Session::put('locale', $request->lang);
        $language = Language::where('code',$request->lang)->first();
        Session::save();
        Session::put('language', $language);
        app()->setLocale(Session::get('locale'));
        return redirect()->back();
    }
}
