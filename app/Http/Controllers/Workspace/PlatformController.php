<?php

namespace App\Http\Controllers\Workspace;

use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Super Admin platform pages: platform settings, USA/UK market configuration,
 * integrations health with the AdMob kill switch, and billing controls.
 * Secrets are never shown; health checks only report whether a value is set.
 */
class PlatformController extends AdminPage
{
    private function platform(Request $request): array
    {
        $context = $this->context($request, ['super_admin']);
        $this->platformOnly($context);
        return $context;
    }

    public function settings(Request $request, PlatformSettings $settings)
    {
        $context = $this->platform($request);
        $markets = DB::table('workspace_markets')->orderBy('code')->get()->map(function ($m) { $m->terminology = json_decode($m->terminology, true) ?: []; return $m; });
        $counts = DB::table('schools')->selectRaw('market, COUNT(*) as total')->groupBy('market')->pluck('total', 'market');
        return $this->render($request, $context, 'settings', 'workspace.admin.settings', ['values' => $settings->all(), 'definitions' => PlatformSettings::DEFINITIONS,
            'markets' => $markets, 'counts' => $counts]);
    }

    public function saveSettings(Request $request, PlatformSettings $settings)
    {
        $context = $this->platform($request);
        $section = $request->validate(['section' => 'required|in:trial,video,policy,retention'])['section'];
        $keys = array_values(array_filter(array_keys(PlatformSettings::DEFINITIONS), fn ($k) => str_starts_with($k, $section.'.')));
        $rules = [];
        foreach ($keys as $key) { $rules[str_replace('.', '__', $key)] = PlatformSettings::DEFINITIONS[$key][1]; }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $settings, $keys, $data) {
            foreach ($keys as $key) {
                $value = $data[str_replace('.', '__', $key)];
                $default = PlatformSettings::DEFINITIONS[$key][0];
                $settings->set($key, is_bool($default) ? (bool) $value : (is_int($default) ? (int) $value : $value), $request->user()->id);
            }
            $this->access->audit($request->user(), null, 'settings.updated', implode(',', $keys));
        });
        return $this->done($request, 'Settings saved.');
    }

    public function saveMarket(Request $request, string $code)
    {
        $context = $this->platform($request);
        $data = $request->validate(['enabled' => 'required|boolean', 'currency' => 'required|string|size:3', 'currency_symbol' => 'required|string|max:4',
            'locale' => 'required|in:en_US,en_GB', 'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())], 'date_format' => 'required|in:m/d/Y,d/m/Y,Y-m-d',
            'phone_prefix' => ['required', 'regex:/^\+\d{1,4}$/'], 'postal_label' => 'required|string|max:32', 'region_label' => 'required|string|max:32',
            'terminology' => 'required|array', 'terminology.grade' => 'required|string|max:40', 'terminology.school_admin' => 'required|string|max:40',
            'terminology.district' => 'required|string|max:40', 'terminology.guardian' => 'required|string|max:40', 'terminology.term' => 'required|string|max:40',
            'privacy_url' => 'nullable|url|max:255', 'terms_url' => 'nullable|url|max:255', 'support_email' => 'nullable|email|max:191', 'minimum_age' => 'required|integer|min:13|max:18']);
        abort_unless(DB::table('workspace_markets')->where('code', $code)->exists(), 404);
        $data['currency'] = strtoupper($data['currency']);
        $data['terminology'] = json_encode(collect($data['terminology'])->only(['grade', 'school_admin', 'district', 'guardian', 'term'])->all());
        DB::table('workspace_markets')->where('code', $code)->update($data + ['updated_by' => $request->user()->id, 'updated_at' => now()]);
        $this->access->audit($request->user(), null, 'market.updated', $code);
        return $this->done($request, (\App\Http\Controllers\Workspace\AdministrationController::MARKETS[$code] ?? $code).' market saved.');
    }

    public function integrations(Request $request, PlatformSettings $settings)
    {
        $context = $this->platform($request);
        $set = fn ($v) => filled($v) && !str_starts_with((string) $v, 'Your ');
        $deliveries = fn ($channel) => DB::table('workspace_deliveries')->where('channel', $channel)->where('created_at', '>', now()->subDay())
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $heartbeat = fn ($c) => DB::table('workspace_runtime')->where('component', $c)->value('seen_at');
        $storageOk = false;
        try { $probe = 'health/'.bin2hex(random_bytes(6)).'.txt'; Storage::disk('private')->put($probe, 'ok'); $storageOk = Storage::disk('private')->get($probe) === 'ok'; Storage::disk('private')->delete($probe); } catch (\Throwable $e) { $storageOk = false; }
        $paypalSandbox = (bool) config('environment.SANDBOX');
        $email = $deliveries('email'); $push = $deliveries('push');
        $providers = [
            ['name' => 'Agora video', 'icon' => 'video-camera', 'about' => 'Video calls in the mobile app.', 'ok' => $set(config('environment.AGORA_APP_ID')) && $set(config('environment.AGORA_APP_CERTIFICATE')),
                'facts' => ['App ID' => $set(config('environment.AGORA_APP_ID')), 'App certificate' => $set(config('environment.AGORA_APP_CERTIFICATE')), 'Webhook secret' => $set(config('environment.AGORA_WEBHOOK_SECRET'))],
                'note' => 'Last call charged: '.(($last = DB::table('call_usage_ledger')->max('created_at')) ? \Carbon\Carbon::parse($last)->diffForHumans() : 'never')],
            ['name' => 'PayPal subscriptions', 'icon' => 'paypal', 'about' => 'Plans and subscription billing ('.($paypalSandbox ? 'sandbox' : 'live').' mode).',
                'ok' => $paypalSandbox ? $set(config('environment.PAYPAL_SANDBOX_ID')) && $set(config('environment.PAYPAL_SANDBOX_SECRET')) : $set(config('environment.PAYPAL_LIVE_ID')) && $set(config('environment.PAYPAL_LIVE_SECRET')),
                'facts' => ['Client ID' => $set($paypalSandbox ? config('environment.PAYPAL_SANDBOX_ID') : config('environment.PAYPAL_LIVE_ID')), 'Client secret' => $set($paypalSandbox ? config('environment.PAYPAL_SANDBOX_SECRET') : config('environment.PAYPAL_LIVE_SECRET')), 'Webhook ID' => $set(config('environment.PAYPAL_WEBHOOK_ID'))],
                'note' => $paypalSandbox ? 'Sandbox mode: no real payments are taken.' : 'Live mode.'],
            ['name' => 'Stripe', 'icon' => 'cc-stripe', 'about' => 'Card payments.', 'ok' => $set(config('environment.STRIPE_SECRET_KEY')),
                'facts' => ['Secret key' => $set(config('environment.STRIPE_SECRET_KEY')), 'Webhook secret' => $set(config('environment.STRIPE_WEBHOOK_SECRET'))], 'note' => null],
            ['name' => 'Email', 'icon' => 'envelope', 'about' => 'Invitations, password resets and notifications.', 'ok' => (bool) config('delivery.email_enabled') && $set(config('mail.from.address')),
                'facts' => ['Sending enabled' => (bool) config('delivery.email_enabled'), 'Mail server' => $set(config('mail.mailers.smtp.host')) || config('mail.default') !== 'smtp', 'From address' => $set(config('mail.from.address'))],
                'note' => 'Last 24 hours: '.(int) ($email['sent'] ?? 0).' sent, '.(int) ($email['failed'] ?? 0).' failed, '.(int) ($email['pending'] ?? 0).' waiting.'],
            ['name' => 'Push notifications', 'icon' => 'bell', 'about' => 'Firebase Cloud Messaging to the mobile app.', 'ok' => (bool) config('delivery.push_enabled') && $set(config('services.firebase.project_id')),
                'facts' => ['Sending enabled' => (bool) config('delivery.push_enabled'), 'Firebase project' => $set(config('services.firebase.project_id')), 'Service account file' => is_file((string) config('services.firebase.key_file'))],
                'note' => 'Last 24 hours: '.(int) ($push['sent'] ?? 0).' sent, '.(int) ($push['failed'] ?? 0).' failed.'],
            ['name' => 'File storage', 'icon' => 'hdd-o', 'about' => 'Private attachments and exports.', 'ok' => $storageOk,
                'facts' => ['Write and read test' => $storageOk], 'note' => null],
            ['name' => 'Background jobs', 'icon' => 'cogs', 'about' => 'Scheduler and queue worker.', 'ok' => ($s = $heartbeat('scheduler')) && $s > now()->subMinutes(5)->toDateTimeString() && ($w = $heartbeat('worker')) && $w > now()->subMinutes(5)->toDateTimeString(),
                'facts' => ['Scheduler running' => ($s = $heartbeat('scheduler')) && $s > now()->subMinutes(5)->toDateTimeString(), 'Queue worker running' => ($w = $heartbeat('worker')) && $w > now()->subMinutes(5)->toDateTimeString()],
                'note' => 'Failed jobs: '.DB::table('failed_jobs')->count().'.'],
        ];
        return $this->render($request, $context, 'integrations', 'workspace.admin.integrations', ['providers' => $providers, 'ads' => [
            'enabled' => (bool) $settings->get('ads.enabled'), 'markets' => (array) $settings->get('ads.markets'),
            'approved' => DB::table('workspace_ad_free_requests')->where('status', 'approved')->count(), 'pending' => DB::table('workspace_ad_free_requests')->where('status', 'pending')->count()]]);
    }

    public function saveAds(Request $request, PlatformSettings $settings)
    {
        $context = $this->platform($request);
        $data = $request->validate(['enabled' => 'required|boolean', 'markets' => 'array', 'markets.US' => 'required|boolean', 'markets.UK' => 'required|boolean']);
        DB::transaction(function () use ($request, $settings, $data) {
            $settings->set('ads.enabled', (bool) $data['enabled'], $request->user()->id);
            $settings->set('ads.markets', ['US' => (bool) $data['markets']['US'], 'UK' => (bool) $data['markets']['UK']], $request->user()->id);
            $this->access->audit($request->user(), null, 'ads.updated', ($data['enabled'] ? 'on' : 'off').':US='.(int) $data['markets']['US'].':UK='.(int) $data['markets']['UK']);
        });
        return $this->done($request, $data['enabled'] ? 'Ad settings saved.' : 'Ads are switched off for everyone.');
    }

    public function planStatus(Request $request, string $id)
    {
        $context = $this->platform($request);
        $data = $request->validate(['status' => 'required|boolean']);
        abort_unless(DB::table('plan')->where('id', $id)->exists(), 404);
        DB::table('plan')->where('id', $id)->update(['status' => (int) $data['status'], 'updated_at' => now()]);
        $this->access->audit($request->user(), null, 'plan.updated', $id.':'.($data['status'] ? 'on' : 'off'));
        return $this->done($request, $data['status'] ? 'Plan is now offered in the app.' : 'Plan hidden from new subscribers. Existing subscribers keep it.');
    }

    /** One subscription with its live PayPal state, payments, refunds and cancellation. */
    public function subscription(Request $request, string $id, \App\Paypal\PaypalBilling $paypal)
    {
        $context = $this->platform($request);
        $subscription = DB::table('user_subscription as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->leftJoin('plan as p', 'p.plan_id', '=', 's.plan_id')
            ->where('s.id', $id)->select('s.*', 'u.first_name', 'u.last_name', 'p.name as plan_name')->first();
        abort_unless($subscription, 404);
        $remote = null; $transactions = []; $paypalError = null;
        if ($subscription->subscription_id) {
            try { $remote = $paypal->subscription($subscription->subscription_id); $transactions = $paypal->transactions($subscription->subscription_id); }
            catch (\Throwable $e) { $paypalError = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException ? $e->getMessage() : 'PayPal could not be reached. Try again shortly.'; report($e); }
        }
        // Reconciliation: the app and PayPal must agree whether the subscription is active.
        $mismatch = $remote && (($remote['status'] === 'ACTIVE') !== ($subscription->status === 'ACTIVE'));
        $history = DB::table('workspace_audit as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->where(fn ($q) => $q->where('a.subject', 'like', $subscription->user_id.':%')->orWhere('a.subject', 'like', $id.'%'))
            ->whereIn('a.action', ['minutes.adjusted', 'subscription.cancelled', 'payment.refunded'])->select('a.*', 'u.first_name', 'u.last_name')->orderByDesc('a.id')->limit(20)->get();
        return $this->render($request, $context, 'billing', 'workspace.admin.subscription', compact('subscription', 'remote', 'transactions', 'paypalError', 'mismatch', 'history'));
    }

    public function cancelSubscription(Request $request, string $id, \App\Paypal\PaypalBilling $paypal)
    {
        $context = $this->platform($request);
        $data = $request->validate(['reason' => 'required|string|max:127']);
        $subscription = DB::table('user_subscription')->find($id);
        abort_unless($subscription, 404);
        abort_unless($subscription->status === 'ACTIVE', 422, 'This subscription is not active.');
        if ($subscription->subscription_id) {
            try { $paypal->cancel($subscription->subscription_id, $data['reason']); }
            catch (\Illuminate\Http\Client\RequestException $e) { abort(422, 'PayPal refused the cancellation: '.($e->response->json('message') ?? 'unknown error').'.'); }
            catch (\Illuminate\Http\Client\ConnectionException $e) { abort(422, 'PayPal could not be reached. Nothing was cancelled.'); }
        }
        DB::table('user_subscription')->where('id', $id)->update(['status' => 'INACTIVE', 'updated_at' => now()]);
        $this->access->audit($request->user(), null, 'subscription.cancelled', $id.' · '.$data['reason']);
        return $this->done($request, $subscription->subscription_id ? 'Subscription cancelled in PayPal and in the app. No further payments will be taken.' : 'Trial ended.');
    }

    public function refund(Request $request, string $id, \App\Paypal\PaypalBilling $paypal)
    {
        $context = $this->platform($request);
        $data = $request->validate(['transaction_id' => 'required|string|max:64|regex:/^[A-Za-z0-9-]+$/', 'amount' => 'nullable|numeric|min:0.01|max:100000',
            'currency' => 'required|string|size:3', 'reason' => 'required|string|max:200']);
        $subscription = DB::table('user_subscription')->find($id);
        abort_unless($subscription && $subscription->subscription_id, 404);
        // Only refund a payment that belongs to this subscription.
        try { $payments = $paypal->transactions($subscription->subscription_id); }
        catch (\Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException $e) { abort(422, 'PayPal could not be reached. Nothing was refunded.'); }
        $payment = collect($payments)->firstWhere('id', $data['transaction_id']);
        abort_unless($payment, 422, 'That payment does not belong to this subscription.');
        abort_unless(strtoupper($payment['status']) === 'COMPLETED', 422, 'Only completed payments can be refunded. This one is '.strtolower($payment['status']).'.');
        $data['amount'] = $data['amount'] ?? null;
        abort_if($data['amount'] !== null && (float) $data['amount'] > (float) $payment['amount'], 422, 'The refund cannot be more than the payment.');
        $amount = $data['amount'] !== null ? number_format((float) $data['amount'], 2, '.', '') : null;
        try { $refundId = $paypal->refund($data['transaction_id'], $amount, strtoupper($data['currency'])); }
        catch (\Illuminate\Http\Client\RequestException $e) { abort(422, 'PayPal refused the refund: '.($e->response->json('message') ?? 'unknown error').'.'); }
        catch (\Illuminate\Http\Client\ConnectionException $e) { abort(422, 'PayPal could not be reached. Check PayPal before trying again, in case the refund went through.'); }
        $this->access->audit($request->user(), null, 'payment.refunded', $id.' · '.$data['transaction_id'].' · '.($amount ?? 'full').' '.strtoupper($data['currency']).' · '.$data['reason'].' · '.$refundId);
        return $this->done($request, 'Refund sent through PayPal'.($refundId ? ' (reference '.$refundId.')' : '').'.');
    }

    /** Adds or removes video minutes on a person's active subscription, with a reason in the activity log. */
    public function adjustMinutes(Request $request)
    {
        $context = $this->platform($request);
        $data = $request->validate(['email' => 'required|email', 'minutes' => 'required|integer|between:-6000,6000|not_in:0', 'reason' => 'required|string|max:200']);
        $user = \App\Models\User::where('email', $data['email'])->whereNull('deleted_at')->first();
        abort_unless($user, 422, 'No account uses this email.');
        DB::transaction(function () use ($request, $data, $user) {
            $subscription = DB::table('user_subscription')->where('user_id', $user->id)->where('status', 'ACTIVE')->orderByDesc('created_at')->lockForUpdate()->first();
            abort_unless($subscription, 422, 'This account has no active subscription or trial to adjust.');
            $remaining = max(0, (float) $subscription->remaining_time + $data['minutes'] * 60);
            DB::table('user_subscription')->where('id', $subscription->id)->update(['remaining_time' => $remaining, 'updated_at' => now()]);
            $this->access->audit($request->user(), null, 'minutes.adjusted', $user->id.':'.($data['minutes'] > 0 ? '+' : '').$data['minutes'].' min · '.$data['reason']);
        });
        return $this->done($request, ($data['minutes'] > 0 ? 'Added ' : 'Removed ').abs($data['minutes']).' minutes for '.$user->email.'.');
    }
}
