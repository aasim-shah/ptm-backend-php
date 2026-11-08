@extends('workspace.layout')
@section('workspace-content')
@php
    $name = trim($subscription->first_name.' '.$subscription->last_name) ?: $subscription->email;
    $trial = $subscription->type === \App\Models\Plan::TRAIL;
    $active = $subscription->status === 'ACTIVE' && (!$subscription->expire_date || $subscription->expire_date >= now()->toDateString());
@endphp
<x-ap.head :eyebrow="$context['label'].' / Subscriptions & Billing'" :title="$name" :sub="($trial ? 'Free trial' : ($subscription->plan_name ?? $subscription->plan_id)).' · '.$subscription->email" :back="route('workspace.billing', ['context' => $context['id']])" back-label="All subscriptions">
    <x-ap.pill :status="$active ? 'active' : 'revoked'" :label="$active ? ($trial ? 'Trial' : 'Active') : 'Ended'" />
</x-ap.head>

@if($mismatch)<div class="ws-alert ws-error" role="alert"><strong>The app and PayPal disagree.</strong> The app says {{ strtolower($subscription->status) }}, PayPal says {{ strtolower($remote['status']) }}. Cancel it here if it should end, or check the PayPal dashboard.</div>@endif
@if($paypalError)<div class="ws-alert ap-hint--warn" role="status">{{ $paypalError }}</div>@endif

<div class="ap-detail">
    <div>
        <section class="ap-panel">
            <header><div><h2>Payments</h2><p>Live from PayPal for the last 12 months.</p></div></header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Date</th><th>Payment ID</th><th class="ap-num">Amount</th><th>Status</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @forelse($transactions as $t)
                <tr><td class="ap-muted">{{ $t['time'] ? $formatTime($t['time'], 'j M Y · H:i') : '—' }}</td><td><code>{{ $t['id'] }}</code></td><td class="ap-num">{{ $t['currency'] }} {{ $t['amount'] }}</td>
                    <td><x-ap.pill :status="strtoupper($t['status']) === 'COMPLETED' ? 'active' : (str_contains(strtoupper($t['status']), 'REFUND') ? 'none' : 'pending')" :label="ucfirst(strtolower(str_replace('_', ' ', $t['status'])))" /></td>
                    <td class="ap-actions ap-keep">@if(strtoupper($t['status']) === 'COMPLETED')<button type="button" class="ap-link-btn ap-link-btn--danger" data-dialog-open="refund-{{ $loop->index }}">Refund</button>@endif</td></tr>
            @empty
                <x-ap.empty colspan="5" icon="credit-card" :title="$subscription->subscription_id ? 'No payments found' : 'No PayPal payments'"><p>{{ $subscription->subscription_id ? 'PayPal reports no payments in the last 12 months.' : 'Trials and app-granted plans have no PayPal payments.' }}</p></x-ap.empty>
            @endforelse
            </tbody></table></div>
        </section>
        <section class="ap-panel">
            <header><h2>Billing history in the app</h2></header>
            <div class="ap-panel-body">@include('workspace.admin.activity', ['entries' => $history])</div>
        </section>
    </div>
    <div>
        <section class="ap-panel">
            <header><h2>Details</h2></header>
            <div class="ap-panel-body"><dl class="ap-facts">
                <dt>Plan</dt><dd>{{ $trial ? 'Free trial' : ($subscription->plan_name ?? $subscription->plan_id) }}</dd>
                <dt>App status</dt><dd>{{ ucfirst(strtolower($subscription->status)) }}</dd>
                <dt>PayPal status</dt><dd>{{ $remote ? ucfirst(strtolower($remote['status'])) : ($subscription->subscription_id ? 'Unavailable' : 'Not a PayPal subscription') }}</dd>
                <dt>Minutes left</dt><dd>{{ $subscription->remaining_time !== null ? number_format(floor($subscription->remaining_time / 60)) : '—' }}</dd>
                <dt>Calls left</dt><dd>{{ $subscription->call_count ?? '—' }}</dd>
                <dt>Started</dt><dd>{{ $subscription->activated_date ?: '—' }}</dd>
                <dt>Expires</dt><dd>{{ $subscription->expire_date ?: '—' }}</dd>
                @if($remote && $remote['next_billing_time'])<dt>Next payment</dt><dd>{{ $formatTime($remote['next_billing_time'], 'j M Y') }}</dd>@endif
                <dt>PayPal ID</dt><dd>{{ $subscription->subscription_id ?: '—' }}</dd>
            </dl></div>
        </section>
        @if($subscription->status === 'ACTIVE')
        <section class="ap-panel ap-danger-zone">
            <header><div><h2>{{ $trial ? 'End trial' : 'Cancel subscription' }}</h2><p>{{ $subscription->subscription_id ? 'Stops future PayPal payments straight away. Past payments are not refunded automatically.' : 'Ends access to the plan in the app.' }}</p></div></header>
            <div class="ap-panel-body">
                <form method="post" action="{{ route('workspace.subscription-cancel', $subscription->id) }}" class="ap-form" data-confirm="{{ $trial ? 'End this trial?' : 'Cancel this subscription in PayPal?' }}">@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}">
                    <label class="ap-span">Reason<input name="reason" required maxlength="127" placeholder="e.g. Requested by the customer"></label>
                    <div class="ap-form-actions"><button class="ws-btn ap-btn-danger">{{ $trial ? 'End trial' : 'Cancel subscription' }}</button></div>
                </form>
            </div>
        </section>
        @endif
    </div>
</div>

@foreach($transactions as $t)
@if(strtoupper($t['status']) === 'COMPLETED')
<x-ap.dialog :id="'refund-'.$loop->index" title="Refund a payment" :sub="'Payment '.$t['id'].' · '.$t['currency'].' '.$t['amount'].'. Money goes back through PayPal and cannot be undone here.'">
    <form method="post" action="{{ route('workspace.subscription-refund', $subscription->id) }}" class="ap-form" data-confirm="Send this refund through PayPal now?">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="transaction_id" value="{{ $t['id'] }}"><input type="hidden" name="currency" value="{{ $t['currency'] }}">
        <label>Amount ({{ $t['currency'] }}) <small>leave empty for a full refund</small><input type="number" name="amount" step="0.01" min="0.01" max="{{ $t['amount'] }}" placeholder="{{ $t['amount'] }}"></label>
        <label>Reason<input name="reason" required maxlength="200"></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn ap-btn-danger">Send refund</button></div>
    </form>
</x-ap.dialog>
@endif
@endforeach
@endsection
