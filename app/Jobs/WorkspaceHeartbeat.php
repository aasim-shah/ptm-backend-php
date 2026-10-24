<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
class WorkspaceHeartbeat implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public function __construct() { $this->onConnection('workspace'); $this->onQueue('workspace'); }
    public function handle(): void { DB::table('workspace_runtime')->updateOrInsert(['component'=>'worker'],['seen_at'=>now()]); }
}
