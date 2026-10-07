<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\UserSchools;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct()
    {
    }

    public function announcement(Request $request){
        $user = $request->user();
        $search_param = $request->search_param ?? null;
        $userSchool = UserSchools::where('user_id',$user->id)->pluck('school_id')->toArray();
        $announcement = Announcement::where('created_at','>',$user->created_at)
        ->with('file')->where(function ($query) use ($user,$userSchool,$search_param){
            if($search_param){
                $query->where('title', 'LIKE', '%' . $search_param . '%');
            }
            if($user->type == 'parent'){
                $query->where(function ($query) use ($userSchool){
                    $query->where('to_user','parent');
                    $query->orWhere('to_user',null);
                });
                $query->where(function ($query) use ($userSchool){
                    $query->whereIn('school_id',$userSchool);
                    $query->orWhere('school_id',null);
                });
            }
            if($user->type == 'teacher'){
                $query->where(function ($query) use ($userSchool){
                    $query->where('to_user','teacher');
                    $query->orWhere('to_user',null);
                });
                $query->where(function ($query) use ($userSchool){
                    $query->whereIn('school_id',$userSchool);
                    $query->orWhere('school_id',null);
                });
            }

        })->with('school')->orderBy('created_at','DESC')->paginate(10);

        $response = array(
            'error' => false,
            'message' => 'Announcement fetched Successfully.',
            'data' => $announcement,
            'code' => 200,
        );
        return response()->json($response);
    }

}