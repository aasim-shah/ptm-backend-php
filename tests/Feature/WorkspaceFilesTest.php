<?php
namespace Tests\Feature;
use App\Jobs\ScanWorkspaceFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
class WorkspaceFilesTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array
    {
        $u=User::forceCreate(['first_name'=>'File','last_name'=>'Admin','type'=>'parent','status'=>1,'email'=>uniqid().'@example.com','email_verified_at'=>now(),'password'=>Hash::make('secret123')]);
        $s=DB::table('schools')->insertGetId(['user_id'=>$u->id,'country_id'=>1,'school_name'=>'File School','address'=>'Road','image'=>'','post_code'=>'00000']);
        $m=DB::table('workspace_memberships')->insertGetId(['user_id'=>$u->id,'school_id'=>$s,'role'=>'school_admin','status'=>'active']);
        $post=DB::table('workspace_posts')->insertGetId(['school_id'=>$s,'author_id'=>$u->id,'kind'=>'notice','audience'=>'all','title'=>'Document','body'=>'A document','created_at'=>now()]);
        return [$u,$s,'m'.$m,$post];
    }
    public function test_upload_is_private_quarantined_and_download_checks_current_access(): void
    {
        Storage::fake('private'); Bus::fake(); [$u,$s,$c,$post]=$this->fixture();
        $this->actingAs($u)->post('/workspace/files',['context'=>$c,'kind'=>'notice','resource_id'=>$post,'file'=>UploadedFile::fake()->createWithContent('school.pdf',"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertRedirect();
        $file=DB::table('workspace_files')->first(); $this->assertNotNull($file); $this->assertSame('quarantined',$file->status);
        Storage::disk('private')->assertExists($file->path); Bus::assertDispatched(ScanWorkspaceFile::class);
        $url=URL::temporarySignedRoute('workspace.file-download',now()->addMinutes(5),['id'=>$file->id,'context'=>$c,'user'=>$u->id]);
        $this->get($url)->assertNotFound();
        DB::table('workspace_files')->where('id',$file->id)->update(['status'=>'clean','scanned_at'=>now()]);
        $this->get($url)->assertOk()->assertDownload('school.pdf')->assertHeader('X-Content-Type-Options','nosniff');
        DB::table('workspace_memberships')->where('user_id',$u->id)->update(['status'=>'revoked']);
        $this->get($url)->assertForbidden();
    }
    public function test_html_upload_is_rejected_and_file_links_are_signed(): void
    {
        Storage::fake('private'); Bus::fake(); [$u,$s,$c,$post]=$this->fixture();
        $this->actingAs($u)->postJson('/workspace/files',['context'=>$c,'kind'=>'notice','resource_id'=>$post,'file'=>UploadedFile::fake()->createWithContent('payload.pdf','<html><script>alert(1)</script></html>')])->assertUnprocessable();
        $this->assertDatabaseCount('workspace_files',0);
        $this->get('/workspace/files/1/download?context='.$c.'&user='.$u->id)->assertForbidden();
    }
    public function test_scanner_failure_never_marks_a_file_clean(): void
    {
        Storage::fake('private'); config(['filesystems.scanner_binary'=>'/definitely/missing/clamscan']);
        [$u,$s,$c,$post]=$this->fixture(); Storage::disk('private')->put('quarantine/file','payload');
        $id=DB::table('workspace_files')->insertGetId(['school_id'=>$s,'uploader_id'=>$u->id,'kind'=>'notice','resource_id'=>$post,'path'=>'quarantine/file','name'=>'file.pdf','mime'=>'application/pdf','size'=>7,'sha256'=>hash('sha256','payload'),'status'=>'quarantined']);
        try { (new ScanWorkspaceFile($id))->handle(); $this->fail('Missing scanner must fail closed.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('scan',$e->getMessage()); }
        $this->assertDatabaseHas('workspace_files',['id'=>$id,'status'=>'quarantined']);
    }
}
