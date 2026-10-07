<?php

namespace App\Jobs;

class SubscriptionQueue
{
    public function process(){
        SubscriptionJob::dispatch();
    }
}