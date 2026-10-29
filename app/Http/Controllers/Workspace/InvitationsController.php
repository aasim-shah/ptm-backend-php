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
        abort_unless(config('delivery.email_enabled'),422,'Configure and enable transactional email before sending invitations.');
        $email=strtolower($data['email']); $recipient=app()->environment('production') ? $email : config('delivery.test_email');
        abort_unless($recipient,422,'Configure a nonproduction test email recipient first.');
        $token=bin2hex(random_bytes(32));
        DB::transaction(function () use ($request,$access,$data,$token,$email,$recipient) {
            $id=DB::table('workspace_invitations')->insertGetId(['school_id'=>$data['school_id'],'invited_by'=>$request->user()->id,'email'=>$email,'role'=>$data['role'],
                'permissions'=>json_encode($data['role']==='staff' ? ($data['permissions']??[]) : []),'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addHours(72),'created_at'=>now(),'updated_at'=>now()]);
            $link=URL::temporarySignedRoute('invitation.show',now()->addHours(72),['token'=>$token]);
            $school=DB::table('schools')->where('id',$data['school_id'])->value('school_name');
            Notification::route('mail',$recipient)->notify(new \App\Notifications\SchoolInvitation($link,$school));
            $access->audit($request->user(),$data['school_id'],'invitation.sent',$id);
        });
        return $request->expectsJson() ? response()->json(['message'=>'Invitation queued.'],202) : back()->with('workspace_success','Invitation queued for secure email delivery.');
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
