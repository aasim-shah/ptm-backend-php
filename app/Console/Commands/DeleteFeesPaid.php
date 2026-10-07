<?php

namespace App\Console\Commands;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class DeleteFeesPaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-fees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Fees Data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::table('fees_choiceables')->delete();
        DB::table('payment_transactions')->delete();
        DB::table('fees_paids')->delete();
        Log::info('data-delete');
        return 0;
    }
}
