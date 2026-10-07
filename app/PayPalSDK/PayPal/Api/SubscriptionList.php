<?php

namespace App\PayPalSDK\PayPal\Api;

use App\PayPalSDK\PayPal\Common\PayPalResourceModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionList extends PayPalResourceModel
{

    private $client;

    public function subscriptionList($url,$apiContext){
        $this->client = new \GuzzleHttp\Client();
        $response = $this->getResponseFromUrl($url,$apiContext,'GET');
        Log::info($response);
        return json_decode($response, true);
    }
    public function cancelSubscription($url,$apiContext,$body){
        $this->client = new \GuzzleHttp\Client();
        $response = $this->getResponseFromUrl($url,$apiContext,'POST',$body);
        Log::info($response);
        return json_decode($response, true);
    }

    public function getResponseFromUrl($url,$apiContext,$method,$body = null)
    {
        Log::info("Get response from url = " . $url);
        if ($body) {
            $body = json_encode($body, true);
        }
        try {
            return self::executeCall(
                $url,
                $method,
                $body,
                null,
                $apiContext,
                ''
            );
        } catch (\Exception $exception) {
            Log::error($exception);
            return response()->json([
                'error' => true,
                'status' => 'failed',
                'data' => null
            ],400);
        }
    }
}