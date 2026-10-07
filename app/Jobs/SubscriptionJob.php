<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\UserSubscription;
use App\Paypal\PayPalAgreement;
use App\Paypal\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle()
    {
        Log::info("Running manage user subscription job");
        $userSubscriptions = UserSubscription::where('status', 'ACTIVE')->get();
        $subscriptionPlan = new SubscriptionPlan();
        foreach ($userSubscriptions as $userSubscription) {
            if (($userSubscription->type == Plan::TRAIL && Carbon::yesterday() > $userSubscription->expire_date) || $userSubscription->remaining_time == '0') {
                Log::info("Subscription period invalidating for user_id " . $userSubscription->user_id);
                $userSubscription->status = "INACTIVE";
                $userSubscription->expire_date = Carbon::now();
                $userSubscription->save();
                $userSubscription = $userSubscription->refresh();
                if($userSubscription->status == 'INACTIVE' && $userSubscription->subscription_id){
                    Log::info("inactivating paypal subscription for subscription_id ". $userSubscription->subscription_id);
                    $subscriptionPlan->cancelSubscription($userSubscription->subscription_id,true,[
                        "reason" => "Inactivate automatically due to 0 remaining time"
                    ]);
                }
            }
        }
    }
}