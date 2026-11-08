<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class PruneWorkspaceExports extends Command
{
    protected $signature='workspace:prune-exports';
    protected $description='Delete report export files after they expire';
    public function handle(): int
    {
        $count=0;
        foreach (DB::table('workspace_exports')->where('status','ready')->where('expires_at','<=',now())->limit(500)->get() as $export) {
            if ($export->path) { Storage::disk('private')->delete($export->path); }
            DB::table('workspace_exports')->where('id',$export->id)->update(['status'=>'expired','path'=>null,'updated_at'=>now()]); $count++;
        }
        $this->info($count.' expired export(s) removed.'); return 0;
    }
}
