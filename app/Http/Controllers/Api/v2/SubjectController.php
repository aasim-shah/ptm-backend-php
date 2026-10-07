<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\SchoolSubjects;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\UserSchools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubjectController
{
    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'bg_color' => 'required|not_in:transparent',
            'image' => 'max:2048',
            'class_id' => 'nullable',
        ])->setAttributeNames(
            ['bg_color' => 'Background Color'],
        );
        $classSection = ClassSection::where('class_id', $request->class_id)->first();

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response,400);
        }

        $isSectionClass = null;
        if($request->class_id){
            $isSectionClass = ClassSchool::where('id',$request->class_id)->whereHas('sections',function ($query) use ($user){
                $query->where('class_teacher_id',$user->id);
            })->first();
        }
        $userSchools = UserSchools::where('user_id',$user->id)->first();
        try {
            $subject = Subject::where(
                ['name' => $request->name])
                ->whereHas('schoolSubjects',function ($query) use ($userSchools){
                    $query->where('school_id',$userSchools->school_id);
                })
                ->count();
            if ($subject) {
                $response = array(
                    'error' => true,
                    'message' => trans('subject_already_exists')
                );
                return response()->json($response,400);
            } else {

                $image = $request->file('image');
                $file_path = null;
                if($image){
                    // made file name with combination of current time
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    //made file path to store in database
                    $file_path = 'subjects/' . $file_name;
                    if($image->getClientMimeType() != 'image/svg+xml' && $image->getClientMimeType() != 'image/svg'){
                        //resized image
                        resizeImage($image);
                    }
                    //stored image to storage/public/subjects folder
                    $destinationPath = storage_path('app/public/subjects');
                    $image->move($destinationPath, $file_name);
                }

                $subject = new Subject();
                $subject->medium_id = $request->medium_id ?? null;
                $subject->name = $request->name;
                $subject->bg_color = $request->bg_color;
                $subject->teacher_id = $user->id;
                $subject->image = $file_path;
                $subject->save();
                $subject = $subject->refresh();

                if($classSection){
                    $subjectTeacher = new SubjectTeacher();
                    $subjectTeacher->class_section_id = $classSection->id;
                    $subjectTeacher->subject_id = $subject->id;
                    $subjectTeacher->teacher_id = $user->id;
                    $subjectTeacher->save();
                    $subjectTeacher = $subjectTeacher->refresh();

                    $classSubject = new ClassSubject();
                    $classSubject->subject_id = $subject->id;
                    $classSubject->class_id = $request->class_id ?? null;
                    $classSubject->type = "Compulsory";
                    $classSubject->save();
                }


                $isSchoolSubject = SchoolSubjects::where('school_id',$userSchools->school_id)->where('subject_id',$subject->id)->first();

                if(!$isSchoolSubject){
                    $isSchoolSubject = new SchoolSubjects();
                    $isSchoolSubject->id = Utility::getUUID();
                    $isSchoolSubject->school_id = $userSchools->school_id;
                    $isSchoolSubject->subject_id = $subject->id;
                    $isSchoolSubject->save();
                }
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                    'data' => $subject
                );
                return response()->json($response);

            }
        } catch (\Throwable $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
            return response()->json($response,400);
        }
    }
    public function update(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'name' => 'required',
            'bg_color' => 'required|not_in:transparent',
            'image' => 'max:2048',
            'class_id' => 'nullable',
        ])->setAttributeNames(
            ['bg_color' => 'Background Color'],
        );

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response,400);
        }

        $userSchools = UserSchools::where('user_id',$user->id)->first();
        try {
            $subject = Subject::where(
                ['name' => $request->name])
                ->whereHas('schoolSubjects',function ($query) use ($userSchools){
                    $query->where('school_id',$userSchools->school_id);
                })
                ->count();
            if ($subject) {
                $response = array(
                    'error' => true,
                    'message' => trans('subject_already_exists')
                );
                return response()->json($response,400);
            } else {

                $image = $request->file('image');
                $file_path = null;
                if($image){
                    // made file name with combination of current time
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    //made file path to store in database
                    $file_path = 'subjects/' . $file_name;
                    if($image->getClientMimeType() != 'image/svg+xml' && $image->getClientMimeType() != 'image/svg'){
                        //resized image
                        resizeImage($image);
                    }
                    //stored image to storage/public/subjects folder
                    $destinationPath = storage_path('app/public/subjects');
                    $image->move($destinationPath, $file_name);
                }

                $subject = Subject::where('id',$request->id)->first();
                $subject->medium_id = $request->medium_id ?? null;
                $subject->name = $request->name;
                $subject->bg_color = $request->bg_color;
                if($file_path){
                    $subject->image = $file_path;
                }
                $subject->save();
                $subject = $subject->refresh();

                $response = array(
                    'error' => false,
                    'message' => trans('data_update_successfully'),
                    'data' => $subject
                );
                return response()->json($response);

            }
        } catch (\Throwable $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
            return response()->json($response,400);
        }
    }

    public function subjects(){

        $user = Auth::user();
        $userSchools = UserSchools::where('user_id',$user->id)->first();
        Log::info($userSchools);

        $subject = Subject::whereHas('schoolSubjects',function ($query) use ($userSchools){
                $query->where('school_id',$userSchools->school_id);
            })->orderBy('name','ASC')->get();
        $response = array(
            'error' => false,
            'message' => "Subject fetched successfully.",
            'data' => $subject
        );
        return response()->json($response);

    }
    public function assign(Request $request){
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'subject_id' => 'required',
            'class_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if(!$classSection){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSectionClass = ClassSchool::where('id',$request->class_id)->whereHas('sections',function ($query) use ($user){
            $query->where('class_teacher_id',$user->id);
        })->first();

        if(!$isSectionClass){
            $response = array(
                'error' => true,
                'message' => "This class is not associate with your school sections or you",
                'data' => [
                    'class_id' => $request->class_id
                ]
            );
            return response()->json($response);
        }
        $subject_id = $request->subject_id;
        $user_id = $user->id;

        $isSubject = Subject::whereIn('id',$subject_id)->first();
        if(!$isSubject){
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => $isSubject
            );
            return response()->json($response);
        }

        foreach($subject_id as $subject){
            $isExists = ClassSubject::where('class_id',$classSection->class_id)->where('subject_id',$subject)->first();
            if(!$isExists){
                $classSubject = new ClassSubject();
                $classSubject->subject_id = $subject;
                $classSubject->class_id = $classSection->class_id;
                $classSubject->type = "Compulsory";
                $classSubject->save();
            }
            $classAssigned = SubjectTeacher::where('class_section_id',$classSection->id)
                ->where('subject_id',$subject)->first();
            if(!$classAssigned){
                $subjectTeacher = new SubjectTeacher();
                $subjectTeacher->class_section_id = $classSection->id;
                $subjectTeacher->subject_id = $subject;
                $subjectTeacher->teacher_id = $user_id;
                $subjectTeacher->save();
                $subjectTeacher = $subjectTeacher->refresh();
            }else{
                $hasAssigned = SubjectTeacher::where('subject_id',$subject)
                    ->where('class_section_id',$classSection->id)
                    ->where('teacher_id',$user_id)->first();
                if(!$hasAssigned){
                    $subjectTeacher = new SubjectTeacher();
                    $subjectTeacher->class_section_id = $classSection->id;
                    $subjectTeacher->subject_id = $subject;
                    $subjectTeacher->teacher_id = $user_id;
                    $subjectTeacher->save();
                    $subjectTeacher = $subjectTeacher->refresh();
                }
            }

        }

        $response = array(
            'error' => false,
            'message' => trans('data_store_successfully'),
            'data' => null
        );
        return response()->json($response);

    }

}