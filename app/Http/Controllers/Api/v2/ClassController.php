<?php

namespace App\Http\Controllers\Api\v2;

use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController
{
    public function __construct()
    {
    }

    public function getTeacherClassList(Request $request){
        $user = $request->user();
        $class = ClassSchool::whereHas('sections',function ($query) use ($user){
            $query->where('class_teacher_id',$user->id);
        })->with('allSubjects.subject')
            ->with(['class_section' => function($query) use($user){
                $query->withCount(['students'=>function($query){
                    $query->where('class_status','ACCEPTED');
                    $query->whereHas('user');
                }]);
            }])->get();
        $response = array(
            'error' => false,
            'message' => 'Teacher Classes Fetched Successfully.',
            'data' => $class,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function getTeacherClasses(Request $request){
        $user = $request->user();
        $teacher_id = $request->teacher_id;
        $class = ClassSchool::whereHas('sections',function ($query) use ($teacher_id){
            $query->where('class_teacher_id',$teacher_id);
        })->get();
        $response = array(
            'error' => false,
            'message' => 'Teacher Classes Fetched Successfully.',
            'data' => $class,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function subjects(Request $request)
    {

        try {
            $user = $request->user();
            $class_id = $request->class_id;
            $classSection =  ClassSection::where('class_id',$class_id)->first();
            $subjects = null;
            if ($classSection) {
                $subjects = Subject::whereHas('subject_teacher',function ($query) use ($user,$classSection){
                    $query->where('teacher_id',$user->id);
                    $query->where('class_section_id',$classSection->id);
                })->get();
            }

            $response = array(
                'error' => false,
                'message' => 'Teacher Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }
    public function subjects2(Request $request)
    {

        try {
            $user = $request->user();
            $class_id = $request->class_id;
            $classSection =  ClassSection::where('class_id',$class_id)->first();
            $subjects = null;
            if ($classSection) {
                $subjects = ClassSubject::where('class_id',$class_id)->with('subject')->get();
            }

            $response = array(
                'error' => false,
                'message' => 'Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }
}