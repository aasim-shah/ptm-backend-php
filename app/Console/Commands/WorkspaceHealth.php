<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class WorkspaceHealth extends Command
{
    protected $signature='workspace:health {--component=all} {--max-age=180}';
    protected $description='Check database and worker/scheduler heartbeat freshness';
    public function handle(): int
    {
        try {
            DB::select('SELECT 1'); $component=$this->option('component');
            if (!in_array($component,['all','worker','scheduler','database'])) { $this->error('Unsupported component.'); return 1; }
            foreach ($component==='all'?['worker','scheduler']:($component==='database'?[]:[$component]) as $name) {
                if (!DB::table('workspace_runtime')->where('component',$name)->where('seen_at','>',now()->subSeconds(max(30,(int)$this->option('max-age'))))->exists()) {
                    $this->error($name.' heartbeat is missing or stale.'); return 1;
                }
            }
            $this->info('Healthy.'); return 0;
        } catch (\Throwable $e) { $this->error('Health check failed. Check private application logs.'); return 1; }
    }
}
