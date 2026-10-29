<?php
namespace App\Console\Commands;
use App\Jobs\DeliverWorkspaceNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class DeliverWorkspaceNotifications extends Command
{
    protected $signature = 'workspace:deliver {--limit=100}';
    protected $description = 'Dispatch due outbox deliveries; delivery jobs reauthorize their recipients';
    public function handle(): int
    {
        DB::table('workspace_deliveries')->whereIn('status',['pending','sending'])->where('attempts','>=',5)->where(function ($q) { $q->where('status','pending')->orWhere('locked_at','<',now()->subMinutes(5)); })->update(['status'=>'failed','updated_at'=>now()]);
        DB::table('workspace_deliveries')->where('status','sending')->where('locked_at','<',now()->subMinutes(5))
            ->update(['status'=>'pending','available_at'=>now()]);
        $ids = DB::table('workspace_deliveries')->where('status','pending')->where('available_at','<=',now())->orderBy('id')->limit(min(1000,max(1,(int)$this->option('limit'))))->pluck('id');
        foreach ($ids as $id) { DeliverWorkspaceNotification::dispatch($id); }
        $this->info('Dispatched '.$ids->count().' delivery jobs.'); return 0;
    }
}
