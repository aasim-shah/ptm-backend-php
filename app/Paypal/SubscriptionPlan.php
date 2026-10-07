<?php

namespace App\Paypal;

use App\Helpers\Utility;
use App\Models\UserSubscription;
use App\PayPalSDK\PayPal\Api\ChargeModel;
use App\PayPalSDK\PayPal\Api\Currency;
use App\PayPalSDK\PayPal\Api\MerchantPreferences;
use App\PayPalSDK\PayPal\Api\Patch;
use App\PayPalSDK\PayPal\Api\PatchRequest;
use App\PayPalSDK\PayPal\Api\PaymentDefinition;
use App\PayPalSDK\PayPal\Api\Plan;
use App\PayPalSDK\PayPal\Api\SubscriptionList;
use App\PayPalSDK\PayPal\Common\PayPalModel;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

function getBaseUrl()
{
    return null;
}

class SubscriptionPlan extends Paypal
{

    public function getFrequency()
    {
        return config('environment.SANDBOX') ? 'Day' : 'Year';
    }

    public function getAmount()
    {
        return config('environment.SANDBOX') ? 10 : config('environment.PREMIUM_PRICE');
    }

    public function create()
    {
        Log::info("The new plan is creating.");
        $plan = $this->plan();
        $paymentDefinition = $this->paymentDefinition();
        $paymentDefinitionForTrial = $this->paymentDefinitionForTrial();
        $baseUrl = getBaseUrl();
        $merchantPreferences = $this->merchantPreferences($baseUrl);
        $plan->setPaymentDefinitions(array($paymentDefinition, $paymentDefinitionForTrial));
        $plan->setMerchantPreferences($merchantPreferences);

        try {
            $output = $plan->create($this->apiContext);
        } catch (Exception $ex) {
            Log::error($ex);
            return null;
        }

        return $output;
    }

    public function plan()
    {
        $plan = new Plan();
        $plan->setName('5 Star plan')
            ->setDescription(config('environment.PREMIUM') . ' Video conferencing mobile minutes for up to ' . config('environment.PREMIUM_CALL_COUNT') . ' participants')
            ->setType('INFINITE');
        return $plan;
    }

    public function paymentDefinition()
    {
        $paymentDefinition = new PaymentDefinition();
        $paymentDefinition->setName('Regular Payments')
            ->setType('REGULAR')
            ->setFrequency($this->getFrequency())
            ->setFrequencyInterval("1")
            ->setAmount(new Currency(array('value' => $this->getAmount(), 'currency' => 'USD')));
        return $paymentDefinition;
    }

    public function paymentDefinitionForTrial()
    {
        $paymentDefinition = new PaymentDefinition();
        $paymentDefinition->setName('Trial Payments')
            ->setType('TRIAL')
            ->setFrequency($this->getFrequency())
            ->setFrequencyInterval("1")
            ->setCycles("1")
            ->setAmount(new Currency(array('value' => 0, 'currency' => 'USD')));
        return $paymentDefinition;
    }

    private function merchantPreferences($baseUrl)
    {
        $merchantPreferences = new MerchantPreferences();
        $merchantPreferences->setReturnUrl(route('execute-agreement', ['true']))
            ->setCancelUrl(route('execute-agreement', ['false']))
            ->setNotifyUrl(route('execute-agreement', ['notify']))
            ->setAutoBillAmount("yes")
            ->setSetupFee(new Currency(array('value' => $this->getAmount(), 'currency' => 'USD')))
            ->setInitialFailAmountAction("CONTINUE")
            ->setMaxFailAttempts("0");
        return $merchantPreferences;
    }

    public function planDetails($id)
    {
        return Plan::get($id, $this->apiContext);
    }

    public function activatePlan($id)
    {

        $createdPlan = $this->planDetails($id);
        $patch = new Patch();
        $value = new PayPalModel('{
            "state":"ACTIVE"
        }');

        $patch->setOp('replace')
            ->setPath('/')
            ->setValue($value);

        $patchRequest = new PatchRequest();
        $patchRequest->addPatch($patch);
        $createdPlan->update($patchRequest, $this->apiContext);
        return Plan::get($createdPlan->getId(), $this->apiContext);
    }

    public function subscriptionList($subscriptionId, $startDate, $lastDate)
    {
        $subscriptionList = new SubscriptionList();
        $url = "/v1/billing/subscriptions/$subscriptionId/transactions?start_time=$startDate&end_time=$lastDate";
        return $subscriptionList->subscriptionList($url, $this->apiContext);
    }

    public function cancelSubscription($subscriptionId, $paypalOnly,$body)
    {
        $subscriptionList = new SubscriptionList();
        $url = "/v1/billing/subscriptions/$subscriptionId/cancel";
        $canceled = $subscriptionList->cancelSubscription($url, $this->apiContext, $body);
        if ($canceled && !$paypalOnly) {
            $userId = Auth::user()->id;
            $userSubscription = UserSubscription::where('subscription_id', $subscriptionId)->where('user_id', $userId)->first();
            $userSubscription->status = 'INACTIVE';
            $userSubscription->save();
        }
        return $canceled;
    }

    private function chargeModel()
    {
        $chargeModel = new ChargeModel();
        $chargeModel->setType('SHIPPING')
            ->setAmount(new Currency(array('value' => 10, 'currency' => 'USD')));
        return $chargeModel;
    }
}