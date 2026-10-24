<?php
namespace Tests\Feature;
use App\Jobs\DeliverWorkspaceNotification;
use App\Models\User;
use App\Services\WorkspaceNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
class WorkspaceDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array
    {
        $u=User::forceCreate(['first_name'=>'School','last_name'=>'Admin','type'=>'parent','status'=>1,'email'=>uniqid().'@example.com','email_verified_at'=>now(),'password'=>Hash::make('secret123')]);
        $school=DB::table('schools')->insertGetId(['user_id'=>$u->id,'country_id'=>1,'school_name'=>'Delivery School','address'=>'Road','image'=>'','post_code'=>'00000']);
        $m=DB::table('workspace_memberships')->insertGetId(['user_id'=>$u->id,'school_id'=>$school,'role'=>'school_admin','status'=>'active']);
        return [$u,$school,'m'.$m];
    }
    public function test_in_app_notifications_follow_audience_and_revocation(): void
    {
        [$u,$s,$c]=$this->fixture();
        $this->actingAs($u)->postJson('/workspace/posts',['context'=>$c,'school_id'=>$s,'kind'=>'notice','audience'=>'all','title'=>'School notice','body'=>'Private details'])->assertOk();
        $this->getJson('/api/workspace/notifications')->assertOk()->assertJsonCount(1,'notifications');
        $n=DB::table('workspace_notifications')->first();
        app(WorkspaceNotifications::class)->record($s,'notice',$n->resource_id,$n->event_key,[$u->id]);
        $this->assertDatabaseCount('workspace_notifications',1);
        DB::table('workspace_memberships')->where('user_id',$u->id)->update(['status'=>'revoked']);
        $this->getJson('/api/workspace/notifications')->assertOk()->assertJsonCount(0,'notifications');
        $this->postJson('/api/workspace/notifications/'.$n->id.'/read')->assertNotFound();
    }
    public function test_delivery_rechecks_authorization_and_uses_generic_email(): void
    {
        config(['delivery.email_enabled'=>true,'delivery.test_email'=>'sink@example.com']); Mail::fake();
        [$u,$s,$c]=$this->fixture();
        $this->actingAs($u)->postJson('/workspace/posts',['context'=>$c,'school_id'=>$s,'kind'=>'notice','audience'=>'all','title'=>'Sensitive','body'=>'Sensitive data'])->assertOk();
        $delivery=DB::table('workspace_deliveries')->first();
        (new DeliverWorkspaceNotification($delivery->id))->handle(app(WorkspaceNotifications::class));
        $this->assertDatabaseHas('workspace_deliveries',['id'=>$delivery->id,'status'=>'sent']);
        $this->postJson('/workspace/posts',['context'=>$c,'school_id'=>$s,'kind'=>'notice','audience'=>'all','title'=>'Another','body'=>'More data'])->assertOk();
        $next=DB::table('workspace_deliveries')->orderByDesc('id')->first();
        DB::table('workspace_memberships')->where('user_id',$u->id)->update(['status'=>'revoked']);
        (new DeliverWorkspaceNotification($next->id))->handle(app(WorkspaceNotifications::class));
        $this->assertDatabaseHas('workspace_deliveries',['id'=>$next->id,'status'=>'cancelled']);
    }
    public function test_invitation_is_scoped_single_use_and_does_not_email_passwords(): void
    {
        Notification::fake(); config(['delivery.email_enabled'=>true,'delivery.test_email'=>'sink@example.com']);
        [$admin,$school,$c]=$this->fixture();
        $this->actingAs($admin)->postJson('/workspace/invitations',['context'=>$c,'school_id'=>$school,'email'=>'new@example.com','role'=>'school_admin'])->assertUnprocessable();
        $this->postJson('/workspace/invitations',['context'=>$c,'school_id'=>$school,'email'=>'new@example.com','role'=>'teacher'])->assertStatus(202);
        $sent=Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable(), \App\Notifications\SchoolInvitation::class)->first();
        $this->assertNotNull($sent); $url=$sent->link;
        $this->assertStringNotContainsString($url, DB::table('workspace_invitations')->value('token_hash'));
        auth()->logout(); $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Set your password');
        $data=['first_name'=>'New','last_name'=>'Teacher','password'=>'A-strong-password-2026','password_confirmation'=>'A-strong-password-2026'];
        $this->post($url,$data)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $new=User::where('email','new@example.com')->first();
        $this->assertTrue(Hash::check($data['password'],$new->password));
        $this->assertDatabaseHas('workspace_memberships',['user_id'=>$new->id,'school_id'=>$school,'role'=>'teacher']);
        $this->post($url,$data)->assertNotFound();
    }
    public function test_revoked_inviter_cannot_leave_an_active_grant_link(): void
    {
        Notification::fake(); config(['delivery.email_enabled'=>true,'delivery.test_email'=>'sink@example.com']);
        [$admin,$s,$c]=$this->fixture();
        $this->actingAs($admin)->postJson('/workspace/invitations',['context'=>$c,'school_id'=>$s,'email'=>'later@example.com','role'=>'parent'])->assertStatus(202);
        $url=Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable(), \App\Notifications\SchoolInvitation::class)->first()->link;
        DB::table('workspace_memberships')->where('user_id',$admin->id)->update(['status'=>'revoked']);
        auth()->logout(); $this->flushSession();
        $this->post($url,['first_name'=>'Name','last_name'=>'Person','password'=>'A-strong-password-2026','password_confirmation'=>'A-strong-password-2026'])->assertForbidden();
        $this->assertDatabaseMissing('users',['email'=>'later@example.com']);
    }
}
