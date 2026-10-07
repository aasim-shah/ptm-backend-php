<?php

namespace App\Http\Controllers\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\UserSubscription;
use App\Paypal\PayPalAgreement;
use App\Paypal\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function createPlan()
    {
        $plan = new SubscriptionPlan();
        $planCreated = $plan->create();
        if ($planCreated) {
            $plan = new Plan();
            $plan->id = Utility::getUUID();
            $plan->plan_id = $planCreated->id;
            $plan->name = $planCreated->name;
            $plan->description = $planCreated->description;
            $plan->amount = $planCreated->payment_definitions[0]->amount->value;
            $plan->currency = $planCreated->payment_definitions[0]->amount->currency;
            $plan->type = $planCreated->payment_definitions[0]->type . "-" . $planCreated->payment_definitions[0]->frequency;
            $plan->save();
        }
        return response()->json(['data' => $planCreated ? json_decode($planCreated->toJSON(), true) : null]);
    }

    public function listPlan()
    {
        $plans = Plan::where('status', 1)->orderBy('created_at', 'DESC')->get();
        return response()->json(
            [
                'data' => $plans
            ]
        );
    }
    public function activeSubscription()
    {
        $isActive = UserSubscription::where('user_id',Auth::user()->id)
            ->where('status','ACTIVE')->where('remaining_time','!=',0)->first();
        $isTrial = UserSubscription::where('type',Plan::TRAIL)
            ->where('status','INACTIVE')
            ->where('user_id',Auth::user()->id)->first();

        return response()->json(
            [
                'data' => $isActive,
                'previous_trial' => $isTrial
            ]
        );
    }

    public function planDetails($id)
    {
        $plan = new SubscriptionPlan();
        $details = $plan->planDetails($id);
        return response()->json(['data' => $details ? json_decode($details->toJSON(), true) : null]);
    }

    public function activatePlan($id)
    {
        $plan = new SubscriptionPlan();
        $planActivated = $plan->activatePlan($id);
        if ($planActivated) {
            $plan = Plan::where('plan_id', $id)->first();
            $plan->status = 1;
            $plan->save();
        }
        return response()->json(['data' => (bool) $planActivated]);
    }

    public function createAgreement($id)
    {
        $plan = new PayPalAgreement();
        $planCreated = $plan->create($id);
        if(!$planCreated){
            return response()->json([
                'error' => true,
                'status' => 'failed',
                'code' => '400',
                'message' => 'Subscription creation',
                'data' => $planCreated
            ],400);
        }
        return response()->json([
            'error' => false,
            'status' => 'success',
            'code' => '200',
            'message' => 'Subscription creation',
            'data' => json_decode($planCreated,true)
        ]);
    }


    public function subscriptionList($subscription_id)
    {
        $plan = new SubscriptionPlan();
        $startDate = Carbon::now()->subYear()->format('Y-m-d\TH:i:s.v\Z');
        $lastDate = Carbon::now()->format('Y-m-d\TH:i:s.v\Z');
        return response()->json(['data' => $plan->subscriptionList($subscription_id, $startDate, $lastDate)]);
    }

    public function cancelSubscription($subscription_id)
    {
        $owns = UserSubscription::where('user_id', Auth::id())
            ->where('subscription_id', $subscription_id)
            ->exists();
        if (!$owns) {
            return response()->json([
                'error' => true,
                'status' => 'failed',
                'code' => '403',
                'message' => trans('no_permission_message'),
            ], 403);
        }

        $plan = new SubscriptionPlan();
        $body = [
            "reason" => "User cancellation"
        ];
        $canceled = $plan->cancelSubscription($subscription_id,false,$body);
        return response()->json([
            'error' => false,
            'status' => 'success',
            'code' => '200',
            'message' => 'Subscription cancellation',
            'data' => $canceled
        ]);
    }

    public function executeAgreement($status)
    {
        Log::info(request());
        $agreement = new PayPalAgreement();
        if ($status == 'true') {
            $subscription = $agreement->executeAgreement(request('token'));
            if($subscription){
                return redirect()->route('payment-success');
            }
            return redirect()->route('payment-failed');
        } elseif ($status == 'false') {
            $agreement->executeAgreement(request('token'), false);
            return redirect()->route('payment-failed');
        } else {
            Log::info(" ** Notifying agreement ** ");
            Log::info(request());
            return null;
        }
    }

    public function activateTrail(){
        $user = Auth::user();
        $hasSubscription = UserSubscription::where('user_id',$user->id)
            ->where('status','ACTIVE')
            ->get();
        if(sizeof($hasSubscription)>0){
            return response()->json([
                'error' => true,
                'status' => 'failed',
                'code' => '102',
                'message' => 'You have already activated a plan',
                'data' => $hasSubscription
            ],400);
        }

        $hadTrail =  UserSubscription::where('user_id',$user->id)
            ->where('type',Plan::TRAIL)->first();
        if($hadTrail){
            return response()->json([
                'error' => true,
                'status' => 'failed',
                'code' => '103',
                'message' => 'You have activated a previous trail',
                'data' => $hadTrail
            ],400);
        }

        $subscription = new UserSubscription();
        $subscription->id = Utility::getUUID();
        $subscription->user_id = $user->id;
        $subscription->email = $user->email;
        $subscription->type = Plan::TRAIL;
        $subscription->remaining_time = config('environment.TRAIL')*60;
        $subscription->call_count = config('environment.TRAIL_CALL_COUNT');
        $subscription->activated_date = Carbon::now()->toDateString();
        $subscription->expire_date = Carbon::now()->addDays((config('environment.TRAIL_DAYS') ?? 5))->toDateString();
        $subscription->status = 'ACTIVE';
        $subscription->save();

        return response()->json([
            'error' => false,
            'status' => 'success',
            'code' => '200',
            'message' => 'Trail package activated',
            'data' => $subscription->refresh()
        ]);

    }

    public function paymentFailed(){
        return view('payment-failed');
    }

    public function paymentSuccess(){
        return view('payment-success');
    }

}