<?php
namespace App\Paypal;

use App\PayPalSDK\PayPal\Api\Details;
use App\PayPalSDK\PayPal\Auth\OAuthTokenCredential;
use App\PayPalSDK\PayPal\Rest\ApiContext;
use Illuminate\Support\Facades\Log;

class Paypal
{

    protected ApiContext $apiContext;

    public function __construct()
    {
        if(config('environment.SANDBOX')){
            Log::info("paypal mode is SANDBOX");
            $this->apiContext = new ApiContext(
                new OAuthTokenCredential(
                    config('environment.PAYPAL_SANDBOX_ID'),
                    config('environment.PAYPAL_SANDBOX_SECRET')
                )
            );
        }else{
            Log::warning("paypal mode is LIVE");
            $this->apiContext = new ApiContext(
                new OAuthTokenCredential(
                    config('environment.PAYPAL_LIVE_ID'),
                    config('environment.PAYPAL_LIVE_SECRET')
                )
            );
        }

    }

    protected function details(): Details
    {
        $details = new Details();
        $details->setShipping(1.2);
        $details->setTax(1.3);
        $details->setSubtotal(17.50);
        return $details;
    }

}