<?php

namespace App\Paypal;

use App\Helpers\Utility;
use App\Models\User;
use App\Models\UserEcToken;
use App\Models\UserSubscription;
use App\PayPalSDK\PayPal\Api\Agreement;
use App\PayPalSDK\PayPal\Api\Payer;
use App\PayPalSDK\PayPal\Api\PayerInfo;
use App\PayPalSDK\PayPal\Api\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayPalAgreement extends Paypal
{

    public function create($id)
    {
        $agreement = new Agreement();
        try {
            DB::beginTransaction();
            $agreement->setName('Base Agreement')
                ->setDescription('Basic Agreement')
                ->setStartDate(Carbon::now()->addHour()->toDateTimeLocalString() . "Z");

            $plan = new Plan();
            $plan->setId($id);
            $agreement->setPlan($plan);

            $payer = new Payer();
            $payer->setPaymentMethod('paypal');
            $agreement->setPayer($payer);
            $agreement = $agreement->create($this->apiContext);

            if ($agreement) {
                $user = $this->findUser(null, Auth::user()->id);
//                $this->removeNoSubscriptionRecord($user->id);
//                $subscription = $this->createNewUserSubscription($user->email, $user->id, null, null, $id, 0);
            }
            $approvalUrl = $agreement->getApprovalLink();
            $parts = parse_url($approvalUrl);
            parse_str($parts['query'], $query);
            $this->setUserEcToken(Auth::user()->id,$query['token']);

            DB::commit();
            return $agreement;
        }catch (\Exception $e){
            DB::rollBack();
            Log::error($e);
            return response()->json(
                [
                    'error' => true,
                    'status' => 'failed',
                ],400
            );
        }
    }

    public function executeAgreement($token, $status = true)
    {
        Log::info("Payment agreement is executing. Status = ".$status);
        $agreement = new Agreement();
        try {
            DB::beginTransaction();
            $executed = $agreement->execute($token, $this->apiContext);
            Log::info($executed);
            if ($executed && $executed->state == 'Active' && $status) {
                $payer = $executed->payer->payer_info;
                $plan = $executed->plan->payment_definitions;
                $userDetails = $this->findUserByEcToken($token);
                $this->removeTrailIfExists($userDetails->user_id);
                $this->removeNoSubscriptionRecord($userDetails->user_id);
                $subscription = $this->createNewUserSubscription($payer->email,$userDetails->user_id,$executed->id,'ACTIVE',$plan,null);


                DB::commit();
                return $subscription;
            }else{
                return null;
            }


        }catch (\Exception $e){
            DB::rollBack();
            Log::error($e);
            return response()->json(
                [
                    'error' => true,
                    'status' => 'failed',
                ],400
            );
        }


        if (!$status) {
            if ($executed->payer->status == 'unverified') {
                // TODO::
            }
        }
    }

    public function createNewUserSubscription($email, $user_id, $subscription_id,$status, $plan = null, $plan_id = null)
    {
        Log::info("New subscription is creating for user_id " . $user_id);
        $subscription = new UserSubscription();
        $subscription->id = Utility::getUUID();
        $subscription->email = $email;
        $subscription->user_id = $user_id;
        $subscription->status = $status;
        if($plan_id){
            $subscription->plan_id = $plan_id;
        }
        $subscription->activated_date = Carbon::now()->toDateString();
        $subscription->expire_date = Carbon::now()->addYear()->toDateString();
        $subscription->subscription_id = $subscription_id;
        if ($plan) {
            $subscription->type = ($plan[0]->type == 'TRIAL' ? 'REGULAR': $plan[0]->type) . '-' . $plan[0]->frequency;
            $subscription->remaining_time = config('environment.PREMIUM')*60;
            $subscription->call_count = config('environment.PREMIUM_CALL_COUNT');
        }
        $subscription->save();
        return $subscription->refresh();
    }

    private function findUser($email = null, $id = null)
    {
        if ($email) {
            return User::where('email', $email)->where('status', 1)->first();
        } elseif ($id) {
            return User::where('id', $id)->where('status', 1)->first();
        }
    }

    private function removeNoSubscriptionRecord($user_id)
    {
        return UserSubscription::where('user_id', $user_id)
            ->where('type', '!=',\App\Models\Plan::TRAIL)->delete();
    }

    public function removeTrailIfExists($user_id)
    {
        Log::info("Trail period invalidating for user_id " . $user_id);
        $isTrail = UserSubscription::where('user_id', $user_id)
            ->where('type', \App\Models\Plan::TRAIL)->first();
        if($isTrail){
            $isTrail->status = 'INACTIVE';
            $isTrail->save();
        }
    }

    public function activateActiveSubscription($subscriptionId){
        $previousSubscription = UserSubscription::where('subscription_id',$subscriptionId)->first();
       if($previousSubscription){
           Log::info("activating subscription for user_id " . $previousSubscription->user_id);
           $previousSubscription->status = 'ACTIVE';
           $previousSubscription->remaining_time = config('environment.PREMIUM')*60;
           $previousSubscription->call_count = config('environment.PREMIUM_CALL_COUNT');
           $previousSubscription->expire_date = Carbon::now()->addYear()->toDateString();
           $previousSubscription->save();
           return $previousSubscription;
       }
    }
    public function removePreviousSubscription($subscriptionId,$userId){
        $previousSubscription = UserSubscription::where('subscription_id','!=',$subscriptionId)
            ->where('user_id',$userId)
            ->where('status','ACTIVE')
            ->where('type','!=','TRAIL')
            ->delete();
    }
    public function inactivateActiveSubscriptionBySubscriptionId($subscriptionId){
       $previousSubscription = UserSubscription::where('subscription_id',$subscriptionId)->where('status','ACTIVE')->first();
       if($previousSubscription){
           Log::info("Inactivating previous subscription for user_id " . $previousSubscription->user_id);
           $previousSubscription->status = 'INACTIVE';
           $previousSubscription->remaining_time = 0;
           $previousSubscription->call_count = 0;
           $previousSubscription->save();
           return $previousSubscription;
       }
    }

    private function setUserEcToken($user_id,$token){
        $uect = new UserEcToken();
        $uect->id = Utility::getUUID();
        $uect->user_id = $user_id;
        $uect->ec_token = $token;
        $uect->save();
    }
    private function findUserByEcToken($token){
        return UserEcToken::where('ec_token',$token)->first();
    }

}