<?php
namespace App\Http\Controllers\Workspace;
use App\Http\Controllers\Controller;
use App\Services\WorkspaceNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
class NotificationsController extends Controller
{
    public function index(Request $request, WorkspaceNotifications $notifications)
    {
        $items = [];
        foreach (DB::table('workspace_notifications')->where('user_id',$request->user()->id)->orderByDesc('id')->limit(100)->get() as $item) {
            if ($link = $notifications->link($request->user(), $item)) { $items[] = ['id'=>$item->id,'title'=>WorkspaceNotifications::TITLES[$item->kind],'url'=>$link,'read_at'=>$item->read_at,'created_at'=>$item->created_at]; }
        }
        return $request->expectsJson() ? response()->json(['notifications'=>$items]) : view('workspace.notifications',['notifications'=>$items]);
    }
    public function read(Request $request, int $id, WorkspaceNotifications $notifications)
    {
        $n = DB::table('workspace_notifications')->where('user_id',$request->user()->id)->find($id);
        abort_unless($n && $notifications->link($request->user(),$n),404);
        DB::table('workspace_notifications')->where('id',$id)->update(['read_at'=>now(),'updated_at'=>now()]);
        return $request->expectsJson() ? response()->json(['message'=>'Notification read.']) : redirect($notifications->link($request->user(),$n));
    }
    public function device(Request $request)
    {
        $data=$request->validate(['token'=>'required|string|max:4096','platform'=>'required|in:ios,android,web']);
        DB::table('workspace_devices')->updateOrInsert(['token_hash'=>hash('sha256',$data['token'])],['user_id'=>$request->user()->id,'platform'=>$data['platform'],
            'token'=>Crypt::encryptString($data['token']),'revoked_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Device registered.']);
    }
}
