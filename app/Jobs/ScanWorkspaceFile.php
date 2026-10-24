<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
class ScanWorkspaceFile implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public $tries=3; public $backoff=[60,300]; public $timeout=120;
    public function __construct(public int $fileId) { $this->afterCommit(); $this->onConnection('workspace'); $this->onQueue('workspace'); }
    public function handle(): void
    {
        $file=DB::table('workspace_files')->find($this->fileId);
        if (!$file || $file->status!=='quarantined') { return; }
        $path=Storage::disk('private')->path($file->path);
        if (!is_file($path) || !hash_equals($file->sha256,hash_file('sha256',$path))) {
            DB::table('workspace_files')->where('id',$file->id)->update(['status'=>'rejected','scanned_at'=>now(),'updated_at'=>now()]); return;
        }
        $scan=new Process([config('filesystems.scanner_binary','clamscan'),'--no-summary','--',$path]); $scan->setTimeout(90);
        try { $scan->run(); } catch (\Throwable $e) { throw new \RuntimeException('Antimalware scanner unavailable. File remains quarantined.'); }
        if (!in_array($scan->getExitCode(),[0,1],true)) { throw new \RuntimeException('Antimalware scan failed. File remains quarantined.'); }
        DB::table('workspace_files')->where('id',$file->id)->where('status','quarantined')->update(['status'=>$scan->getExitCode()===0?'clean':'rejected','scanned_at'=>now(),'updated_at'=>now()]);
    }
}
