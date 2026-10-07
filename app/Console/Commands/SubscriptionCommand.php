<?php

namespace App\Console\Commands;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class SubscriptionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew Subscription';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('user subscription validate process task started');
        $service = resolve('App\Jobs\SubscriptionQueue');
        $service->process();
        Log::info('user subscription validate process task ended');
    }
}
