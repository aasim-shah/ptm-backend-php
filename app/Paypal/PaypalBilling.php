<?php

namespace App\Paypal;

use Illuminate\Support\Facades\Http;

/**
 * PayPal REST calls used by Subscriptions & Billing: subscription status, payment
 * history, cancellation and refunds. Every call throws on a PayPal error so the
 * caller can show the reason and nothing is recorded as done when it was not.
 */
class PaypalBilling
{
    private function base(): string
    {
        return config('environment.SANDBOX') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public function configured(): bool
    {
        $sandbox = (bool) config('environment.SANDBOX');
        return filled($sandbox ? config('environment.PAYPAL_SANDBOX_ID') : config('environment.PAYPAL_LIVE_ID'))
            && filled($sandbox ? config('environment.PAYPAL_SANDBOX_SECRET') : config('environment.PAYPAL_LIVE_SECRET'));
    }

    private function client()
    {
        abort_unless($this->configured(), 422, 'PayPal is not configured on this server.');
        $sandbox = (bool) config('environment.SANDBOX');
        $token = Http::asForm()->timeout(15)
            ->withBasicAuth($sandbox ? config('environment.PAYPAL_SANDBOX_ID') : config('environment.PAYPAL_LIVE_ID'),
                $sandbox ? config('environment.PAYPAL_SANDBOX_SECRET') : config('environment.PAYPAL_LIVE_SECRET'))
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json('access_token');
        return Http::withToken($token)->timeout(20)->acceptJson()->baseUrl($this->base());
    }

    /** @return array{status: ?string, next_billing_time: ?string, last_payment: ?array} */
    public function subscription(string $id): array
    {
        $data = $this->client()->get('/v1/billing/subscriptions/'.rawurlencode($id))->throw()->json();
        return ['status' => $data['status'] ?? null, 'next_billing_time' => $data['billing_info']['next_billing_time'] ?? null,
            'last_payment' => $data['billing_info']['last_payment']['amount'] ?? null];
    }

    /** Payments for a subscription over the last year, newest first. */
    public function transactions(string $id): array
    {
        $rows = $this->client()->get('/v1/billing/subscriptions/'.rawurlencode($id).'/transactions', [
            'start_time' => now()->subYear()->utc()->format('Y-m-d\TH:i:s\Z'), 'end_time' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ])->throw()->json('transactions') ?? [];
        return collect($rows)->map(fn ($t) => ['id' => $t['id'], 'status' => $t['status'] ?? '', 'time' => $t['time'] ?? null,
            'amount' => $t['amount_with_breakdown']['gross_amount']['value'] ?? null, 'currency' => $t['amount_with_breakdown']['gross_amount']['currency_code'] ?? null])
            ->sortByDesc('time')->values()->all();
    }

    public function cancel(string $id, string $reason): void
    {
        $this->client()->post('/v1/billing/subscriptions/'.rawurlencode($id).'/cancel', ['reason' => mb_substr($reason, 0, 127)])->throw();
    }

    /** Refunds one subscription payment, in full or for the given amount. Returns PayPal's refund id. */
    public function refund(string $transactionId, ?string $amount, ?string $currency): string
    {
        $body = $amount !== null ? ['amount' => ['total' => $amount, 'currency' => $currency]] : new \stdClass();
        $response = $this->client()->post('/v1/payments/sale/'.rawurlencode($transactionId).'/refund', $body)->throw();
        return (string) ($response->json('id') ?? '');
    }
}
