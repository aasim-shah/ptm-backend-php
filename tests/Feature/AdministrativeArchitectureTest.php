<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrativeArchitectureTest extends TestCase
{
    use RefreshDatabase;
    public function actingAs($user, $guard = null)
    {
        $this->flushSession();
        return parent::actingAs($user, $guard ?? 'web');
    }
    private function user(): User
    {
        return User::forceCreate(['first_name'=>'Admin', 'last_name'=>'Test', 'type'=>'parent', 'email'=>uniqid().'@example.com',
            'password'=>Hash::make('secret123'), 'status'=>1, 'email_verified_at'=>now()]);
    }
    private function school(string $name): int
    {
        return DB::table('schools')->insertGetId(['user_id'=>1, 'country_id'=>1, 'school_name'=>$name, 'address'=>'Road', 'image'=>'', 'post_code'=>'00000']);
    }
    private function membership(User $user, int $school, string $role, array $permissions=[]): string
    {
        return 'm'.DB::table('workspace_memberships')->insertGetId(['user_id'=>$user->id,'school_id'=>$school,'role'=>$role,'status'=>'active','permissions'=>json_encode($permissions)]);
    }
    public function test_mobile_roles_cannot_sign_in_or_reach_any_web_workspace(): void
    {
        foreach (['parent','teacher','staff'] as $role) {
            $u=$this->user(); $c=$this->membership($u,$this->school($role),$role);
            $this->post('/login-web',['email'=>$u->email,'password'=>'secret123'])->assertSessionHasErrors();
            $this->assertGuest();
            $this->actingAs($u)->getJson('/dashboard?context='.$c)->assertForbidden();
            $this->getJson('/workspace/notifications')->assertForbidden();
            $this->postJson('/workspace/profile',['context'=>$c])->assertForbidden();
            $this->actingAs($u, 'sanctum')->getJson('/api/dashboard?context='.$c)->assertOk();
            auth('web')->logout(); auth()->shouldUse('web'); $this->flushSession();
        }
    }
    public function test_staff_web_authorization_can_be_revoked_without_removing_mobile_membership(): void
    {
        $u=$this->user();$s=$this->school('Assigned');$c=$this->membership($u,$s,'staff',['web.access']);
        $this->actingAs($u)->get('/dashboard?context='.$c)->assertOk();
        $this->postJson('/workspace/posts',['context'=>$c,'school_id'=>$s,'kind'=>'notice','title'=>'No','body'=>'No','audience'=>'all'])->assertForbidden();
        DB::table('workspace_memberships')->where('user_id',$u->id)->update(['permissions'=>'[]']);
        $this->getJson('/dashboard?context='.$c)->assertForbidden();
        $this->getJson('/api/dashboard?context='.$c)->assertOk();
    }
    public function test_super_admin_dashboard_delegation_export_and_revocation(): void
    {
        $super=$this->user();$super->assignRole(Role::findOrCreate('Super Admin','web'));$sub=$this->user();
        $a=$this->school('Allowed School');$b=$this->school('Unrelated School');
        $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee('Super Admin');
        $this->get('/dashboard/administration?context=platform')->assertOk()->assertSee('Unrelated School');
        $this->post('/workspace/delegations',['context'=>'platform','email'=>$sub->email,'school_id'=>$a,'permissions'=>['reports.export','audit.view']])->assertRedirect();
        $id=DB::table('workspace_memberships')->where('user_id',$sub->id)->value('id');$c='m'.$id;
        $this->actingAs($sub)->get('/dashboard/administration?context='.$c)->assertOk()->assertSee('Allowed School')->assertDontSee('Unrelated School');
        $this->get('/workspace/reports/schools.csv?context='.$c)->assertOk()->assertDownload('ptm-schools.csv');
        $this->postJson('/workspace/delegations',['context'=>$c])->assertForbidden();
        $this->get('/roles')->assertRedirect(route('login'));
        $this->actingAs($super)->post('/workspace/delegations/'.$id.'/revoke',['context'=>'platform'])->assertRedirect();
        $this->actingAs($sub)->getJson('/dashboard?context='.$c)->assertForbidden();
    }
    public function test_sub_super_scope_permissions_and_no_privilege_escalation(): void
    {
        $u=$this->user();$v=$this->user();$a=$this->school('Allowed');$b=$this->school('Outside');
        $c=$this->membership($u,$a,'sub_super_admin',['memberships.manage']);
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('Sub-Super Admin');
        $this->getJson('/workspace/reports/schools.csv?context='.$c)->assertForbidden();
        $this->postJson('/workspace/memberships',['context'=>$c,'school_id'=>$a,'email'=>$v->email,'role'=>'staff','permissions'=>['web.access']])->assertForbidden();
        $this->postJson('/workspace/memberships',['context'=>$c,'school_id'=>$b,'email'=>$v->email,'role'=>'staff'])->assertUnprocessable();
        $this->getJson('/api/dashboard?context=platform')->assertForbidden();
        $this->getJson('/dashboard?context='.$c.'s'.$b)->assertForbidden();
    }
    public function test_district_management_and_exports_never_include_unrelated_schools(): void
    {
        $u=$this->user();$target=$this->user();$a=$this->school('Allowed');$b=$this->school('Secret unrelated school');
        $district=DB::table('workspace_districts')->insertGetId(['name'=>'Assigned district','market'=>'US']);
        DB::table('workspace_district_schools')->insert(['district_id'=>$district,'school_id'=>$a]);
        $id=DB::table('workspace_memberships')->insertGetId(['user_id'=>$u->id,'district_id'=>$district,'role'=>'district_admin','status'=>'active']);$c='m'.$id;
        $this->actingAs($u)->get('/dashboard/administration?context='.$c)->assertOk()->assertDontSee('Secret unrelated school');
        $response=$this->get('/workspace/reports/schools.csv?context='.$c)->assertOk();
        $this->assertStringNotContainsString('Secret unrelated school',$response->streamedContent());
        $this->post('/workspace/school-admin',['context'=>$c,'school_id'=>$a,'email'=>$target->email])->assertRedirect();
        $this->assertDatabaseHas('workspace_memberships',['user_id'=>$target->id,'school_id'=>$a,'role'=>'school_admin']);
        $this->postJson('/workspace/school-admin',['context'=>$c,'school_id'=>$b,'email'=>$target->email])->assertUnprocessable();
        $this->postJson('/workspace/school-profile',['context'=>$c,'school_id'=>$b,'school_name'=>'Hijack','address'=>'Road','post_code'=>'00000'])->assertUnprocessable();
        $this->post('/workspace/ad-free',['context'=>$c,'reason'=>'District coverage'])->assertRedirect();
        $this->assertDatabaseHas('workspace_ad_free_requests',['district_id'=>$district,'school_id'=>null,'status'=>'pending']);
    }

    public function test_school_requests_require_super_approval_and_are_isolated(): void
    {
        $a=$this->user();$b=$this->user();$super=$this->user();$super->assignRole(Role::findOrCreate('Super Admin','web'));
        $s=$this->school('A');$other=$this->school('B');$ac=$this->membership($a,$s,'school_admin');$bc=$this->membership($b,$other,'school_admin');
        $this->actingAs($a)->post('/workspace/ad-free',['context'=>$ac,'school_id'=>$s,'reason'=>'Institutional coverage'])->assertRedirect();
        $id=DB::table('workspace_ad_free_requests')->value('id');
        $this->actingAs($b)->get('/dashboard/administration?context='.$bc)->assertOk()->assertDontSee('Institutional coverage');
        $this->postJson('/workspace/ad-free/'.$id.'/review',['context'=>$bc,'status'=>'approved'])->assertForbidden();
        $this->actingAs($super)->post('/workspace/ad-free/'.$id.'/review',['context'=>'platform','status'=>'approved'])->assertRedirect();
        $this->assertDatabaseHas('workspace_ad_free_requests',['id'=>$id,'status'=>'approved','reviewed_by'=>$super->id]);
        $this->postJson('/workspace/ad-free/'.$id.'/review',['context'=>'platform','status'=>'rejected'])->assertStatus(409);
    }
}
