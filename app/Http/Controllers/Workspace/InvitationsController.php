<?php
namespace App\Http\Controllers\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class InvitationsController extends Controller
{
    public function create(Request $request, WorkspaceAccess $access)
    {
        $context=$access->resolve($request->user(),$request->input('context'));
        abort_unless($access->can($context,'memberships.manage'),403);
        $data=$request->validate(['school_id'=>['required','integer',Rule::in(array_keys($context['schools']))],
            'email'=>'required|email:rfc|max:191','role'=>'required|in:parent,teacher,staff','permissions'=>'nullable|array','permissions.*'=>[Rule::in(WorkspaceAccess::STAFF_PERMISSIONS)]]);
        abort_unless($access->mayDelegate($context, $data['permissions'] ?? []),403);
        app(\App\Services\WorkspaceInvitations::class)->send($request->user(), (int) $data['school_id'], $data['email'], $data['role'], $data['permissions'] ?? []);
        return $request->expectsJson() ? response()->json(['message'=>'Invitation queued.'],202) : back()->with('workspace_success','Invitation queued for secure email delivery.');
    }

    public function revoke(Request $request, int $id, WorkspaceAccess $access)
    {
        $context=$access->resolve($request->user(),$request->input('context'));
        abort_unless($access->can($context,'memberships.manage'),403);
        $updated=DB::table('workspace_invitations')->where('id',$id)->whereIn('school_id',array_keys($context['schools']))
            ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated,404);
        $access->audit($request->user(),DB::table('workspace_invitations')->where('id',$id)->value('school_id'),'invitation.revoked',$id);
        return $request->expectsJson() ? response()->json(['message'=>'Invitation withdrawn.']) : back()->with('workspace_success','Invitation withdrawn. The link no longer works.');
    }

    private function invitation(string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token),404);
        return DB::table('workspace_invitations')->where('token_hash',hash('sha256',$token))->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at','>',now());
    }

    public function show(Request $request,string $token)
    {
        $invitation=$this->invitation($token)->first(); abort_unless($invitation,404,'Invitation expired or already used.');
        return response()->view('workspace.invitation',['invitation'=>$invitation,'token'=>$token,
            'existing'=>User::where('email',$invitation->email)->exists()])->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
    }

    public function accept(Request $request,string $token,WorkspaceAccess $access)
    {
        $request->validate(['first_name'=>'required|string|max:100','last_name'=>'required|string|max:100','password'=>'nullable|confirmed|max:128']);
        $user=DB::transaction(function () use ($request,$token,$access) {
            $invite=$this->invitation($token)->lockForUpdate()->first(); abort_unless($invite,404,'Invitation expired or already used.');
            $inviter=User::find($invite->invited_by);
            // A revoked administrator cannot leave behind authority-bearing links.
            $authorized=false;
            if ($inviter && $inviter->status && $inviter->email_verified_at) {
                foreach ($access->contexts($inviter) as $c) { if (isset($c['schools'][$invite->school_id]) && $access->can($c,'memberships.manage') && $access->mayDelegate($c,json_decode($invite->permissions ?? '[]',true) ?: [])) { $authorized=true; break; } }
            }
            abort_unless($authorized,403,'The inviting administrator no longer has authority for this school.');
            $user=User::where('email',$invite->email)->lockForUpdate()->first();
            if ($user) {
                abort_unless($user->status && $user->email_verified_at && (($request->user() && (int)$request->user()->id===(int)$user->id) || Hash::check((string)$request->password,$user->password)),403,'Enter the invited account’s current password to accept.');
            } else {
                $request->validate(['password'=>'required|confirmed|min:12|max:128']);
                $user=User::forceCreate(['first_name'=>$request->first_name,'last_name'=>$request->last_name,'email'=>$invite->email,
                    'password'=>Hash::make($request->password),'email_verified_at'=>now(),'status'=>1,'type'=>'parent']);
            }
            DB::table('workspace_memberships')->updateOrInsert(['user_id'=>$user->id,'school_id'=>$invite->school_id,'role'=>$invite->role],
                ['status'=>'active','permissions'=>$invite->permissions,'approved_by'=>$invite->invited_by,'starts_at'=>now(),'ends_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('workspace_invitations')->where('id',$invite->id)->update(['accepted_at'=>now(),'accepted_by'=>$user->id,'updated_at'=>now()]);
            $access->audit($user,$invite->school_id,'invitation.accepted',$invite->id); return $user;
        });
        if (!$access->webContexts($user)) { return redirect()->route('login')->with('workspace_success', 'Invitation accepted. Continue in the mobile app.'); }
        Auth::guard('web')->login($user); $request->session()->regenerate();
        $request->session()->put('account_session_version',app(\App\Services\Security\AccountSecurity::class)->state($user)->session_version??1);
        return redirect()->route('workspace.home');
    }
}
