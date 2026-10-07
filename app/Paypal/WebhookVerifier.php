<?php

namespace App\Paypal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies PayPal webhook signatures with PayPal's verify-webhook-signature API.
 * Requires PAYPAL_WEBHOOK_ID (from the PayPal developer dashboard).
 */
class WebhookVerifier
{
    public function verify(Request $request): bool
    {
        $webhookId = config('environment.PAYPAL_WEBHOOK_ID');
        if (!$webhookId) {
            Log::critical('PayPal webhook rejected: PAYPAL_WEBHOOK_ID is not configured.');
            return false;
        }

        $sandbox = (bool) config('environment.SANDBOX');
        $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $clientId = $sandbox ? config('environment.PAYPAL_SANDBOX_ID') : config('environment.PAYPAL_LIVE_ID');
        $secret = $sandbox ? config('environment.PAYPAL_SANDBOX_SECRET') : config('environment.PAYPAL_LIVE_SECRET');

        try {
            $token = Http::asForm()->withBasicAuth($clientId, $secret)
                ->post("$base/v1/oauth2/token", ['grant_type' => 'client_credentials'])
                ->throw()->json('access_token');

            $status = Http::withToken($token)->post("$base/v1/notifications/verify-webhook-signature", [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($request->getContent(), true),
            ])->throw()->json('verification_status');
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification failed: ' . $e->getMessage());
            return false;
        }

        return $status === 'SUCCESS';
    }
}
