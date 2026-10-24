<?php
namespace App\Http\Controllers\Workspace;
use App\Http\Controllers\Controller;
use App\Jobs\ScanWorkspaceFile;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
class FilesController extends Controller
{
    public function __construct(private WorkspaceAccess $access,private WorkspaceFiles $files) {}
    private function context(Request $r): array { return $this->access->resolve($r->user(),$r->input('context')); }
    public function index(Request $request)
    {
        $context=$this->context($request); $items=[];
        foreach (DB::table('workspace_files')->whereIn('school_id',array_keys($context['schools']))->orderByDesc('id')->limit(100)->get() as $file) {
            if (!$this->files->resource($request->user(),$context,$file->kind,$file->resource_id)) { continue; }
            if ($request->filled('resource_id') && ((int)$request->resource_id!==(int)$file->resource_id || $request->kind!==$file->kind)) { continue; }
            $items[]=['id'=>$file->id,'name'=>$file->name,'size'=>$file->size,'status'=>$file->status,'download'=>$file->status==='clean' ? URL::temporarySignedRoute('workspace.file-download',now()->addMinutes(5),['id'=>$file->id,'context'=>$context['id'],'user'=>$request->user()->id]) : null];
        }
        $resource=$this->files->resource($request->user(),$context,(string)$request->input('kind',''),(int)$request->input('resource_id',0));
        $canUpload=$this->files->mayUpload($request->user(),$context,(string)$request->input('kind',''),$resource);
        if ($request->expectsJson()) { return response()->json(['files'=>$items]); }
        return view('workspace.files',['files'=>$items,'context'=>$context,'contexts'=>$this->access->webContexts($request->user()),'access'=>$this->access,'page'=>'files','canUpload'=>$canUpload,'resource'=>$resource]);
    }
    public function upload(Request $request)
    {
        $context=$this->context($request);
        $request->validate(['kind'=>'required|in:message,update,notice,event','resource_id'=>'required|integer','file'=>'required|file|max:10240']);
        $resource=$this->files->resource($request->user(),$context,$request->kind,(int)$request->resource_id);
        abort_unless($this->files->mayUpload($request->user(),$context,$request->kind,$resource),403);
        $upload=$request->file('file'); $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($upload->getRealPath());
        abort_unless(in_array($mime,['application/pdf','image/jpeg','image/png'],true),422,'Only PDF, JPEG and PNG documents are accepted.');
        $path=$upload->store('quarantine','private');
        try {
            $id=DB::transaction(function () use ($request,$upload,$mime,$path,$resource) {
                $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u','_',basename($upload->getClientOriginalName())),0,180);
                $id=DB::table('workspace_files')->insertGetId(['school_id'=>$resource->school_id,'uploader_id'=>$request->user()->id,'kind'=>$request->kind,
                    'resource_id'=>$request->resource_id,'path'=>$path,'name'=>$name?:'attachment','mime'=>$mime,'size'=>$upload->getSize(),'sha256'=>hash_file('sha256',Storage::disk('private')->path($path)),
                    'status'=>'quarantined','created_at'=>now(),'updated_at'=>now()]);
                $this->access->audit($request->user(),$resource->school_id,'file.quarantined',$id); return $id;
            });
        } catch (\Throwable $e) { Storage::disk('private')->delete($path); throw $e; }
        ScanWorkspaceFile::dispatch($id);
        return $request->expectsJson() ? response()->json(['message'=>'File quarantined for scanning.','id'=>$id],202) : back()->with('workspace_success','File uploaded. Downloads stay locked until scanning succeeds.');
    }
    public function download(Request $request,int $id)
    {
        abort_unless((int)$request->query('user')===(int)$request->user()->id,403);
        $context=$this->context($request); $file=DB::table('workspace_files')->whereIn('school_id',array_keys($context['schools']))->find($id);
        abort_unless($file && $file->status==='clean' && $this->files->resource($request->user(),$context,$file->kind,$file->resource_id),404);
        $path=Storage::disk('private')->path($file->path);
        abort_unless(is_file($path) && hash_equals($file->sha256,hash_file('sha256',$path)),404);
        $this->access->audit($request->user(),$file->school_id,'file.downloaded',$id);
        return Storage::disk('private')->download($file->path,$file->name,['Content-Type'=>$file->mime,'X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>'sandbox','Cache-Control'=>'no-store, private']);
    }
}
