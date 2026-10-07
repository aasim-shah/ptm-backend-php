<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\UserSchools;
use Illuminate\Http\Request;

class SchoolController extends Controller
{

    public function __construct()
    {
    }

    public function schoolSection(Request $request){
        $user = $request->user();
        if($user->type != 'teacher'){
            $response = array(
                'error' => true,
                'message' => 'Failed to create school sections.',
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }

        $isTeacherSchool = UserSchools::where('user_id',$user->id)->where('school_id',$request->school_id)->first();
        if(!$isTeacherSchool){
            $response = array(
                'error' => true,
                'message' => "Invalid school detected. This school is not related to you",
                'data' => []
            );
            return response()->json($response);
        }
        $section = new Section();
        $section->name = $request->name;
        $section->school_id = $request->school_id;
        $section->save();

        $response = array(
            'error' => false,
            'message' => 'School sections created Successfully.',
            'data' => $section->refresh(),
            'code' => 200,
        );
        return response()->json($response);
    }

}