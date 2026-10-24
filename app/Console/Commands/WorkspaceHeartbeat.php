<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class WorkspaceHeartbeat extends Command
{
    protected $signature='workspace:heartbeat';
    protected $description='Record scheduler health and enqueue a worker heartbeat';
    public function handle(): int
    {
        DB::table('workspace_runtime')->updateOrInsert(['component'=>'scheduler'],['seen_at'=>now()]);
        \App\Jobs\WorkspaceHeartbeat::dispatch(); return 0;
    }
}
