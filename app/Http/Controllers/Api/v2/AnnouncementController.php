<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\UserSchools;
use App\Support\Access;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct()
    {
    }

    public function announcement(Request $request){
        $user = $request->user();
        $search_param = $request->search_param ?? null;
        // With a role context: that context's schools and role. Without one: every linked
        // school and users.type, as before.
        $userSchool = Access::schoolIds($user)
            ?? UserSchools::where('user_id',$user->id)->pluck('school_id')->toArray();
        $role = Access::role($user);
        $audience = in_array($role, ['parent', 'teacher'], true) ? $role : null;
        $announcement = Announcement::where('created_at','>',$user->created_at)
        ->with('file')->where(function ($query) use ($user,$userSchool,$search_param,$audience,$role){
            if($search_param){
                $query->where('title', 'LIKE', '%' . $search_param . '%');
            }
            if ($audience || in_array($role, ['staff', 'school_admin', 'district_admin'], true)) {
                // to_user targets 'parent' or 'teacher'; staff and admins see the
                // announcements sent to everyone (to_user NULL) or to their role name.
                $query->where(function ($query) use ($audience, $role){
                    $query->where('to_user', $audience ?? $role);
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