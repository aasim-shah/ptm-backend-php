@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Subscriptions & Billing'" title="Subscriptions & Billing" sub="Plans, trials and video minutes across the platform. Payments are taken by PayPal. Open a subscription to check it against PayPal, refund a payment or cancel it.">
    <a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'subscriptions']) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>
    <button type="button" class="ws-btn" data-dialog-open="adjust-minutes"><i class="fa fa-clock-o" aria-hidden="true"></i>Adjust minutes</button>
</x-ap.head>

<x-ap.stats :stats="$summary" />

<div class="ap-detail">
    <div>
        <section class="ap-panel">
            <x-ap.toolbar :action="route('workspace.billing')" :context="$context" placeholder="Search by name, email or PayPal subscription ID" :count="$subscriptions->total()">
                <select name="status" aria-label="Status"><option value="">All subscriptions</option>@foreach(['active' => 'Active', 'trial' => 'Active trials', 'expiring' => 'Expiring in 30 days', 'ended' => 'Expired or cancelled'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            </x-ap.toolbar>
            <div class="ap-table-wrap"><table class="ap-table">
                <thead><tr><th>Account</th><th>Plan</th><th>Status</th><th class="ap-num">Minutes left</th><th>Expires</th><th>PayPal</th><th class="ap-actions ap-keep"></th></tr></thead>
                <tbody>
                @forelse($subscriptions as $s)
                    @php $expired = $s->expire_date && $s->expire_date < now()->toDateString(); $state = $s->status === 'ACTIVE' && !$expired ? ($s->type === \App\Models\Plan::TRAIL ? 'pending' : 'active') : 'revoked'; @endphp
                    <tr><td><x-ap.person :first="$s->first_name" :last="$s->last_name" :email="$s->email" /></td>
                        <td>{{ $s->type === \App\Models\Plan::TRAIL ? 'Free trial' : ($s->plan_name ?? 'Plan') }}<div class="ap-muted">{{ $s->plan_id }}</div></td>
                        <td><x-ap.pill :status="$state" :label="$s->status === 'ACTIVE' && !$expired ? ($s->type === \App\Models\Plan::TRAIL ? 'Trial' : 'Active') : ($expired ? 'Expired' : ucfirst(strtolower($s->status)))" /></td>
                        <td class="ap-num">{{ $s->remaining_time !== null ? number_format(floor($s->remaining_time / 60)) : '—' }}</td>
                        <td class="ap-muted">{{ $s->expire_date ? \Carbon\Carbon::parse($s->expire_date)->format('j M Y') : '—' }}</td>
                        <td class="ap-muted">{{ $s->subscription_id ?: '—' }}</td>
                        <td class="ap-actions ap-keep"><a href="{{ route('workspace.subscription', ['id' => $s->id, 'context' => $context['id']]) }}">Manage →</a></td></tr>
                @empty
                    <x-ap.empty colspan="7" icon="credit-card" title="No subscriptions match"><p>Trials start when someone signs up in the app. Paid plans appear after PayPal confirms payment.</p></x-ap.empty>
                @endforelse
                </tbody>
            </table></div>
            <x-ap.pager :items="$subscriptions" />
        </section>
    </div>
    <div>
        <section class="ap-panel">
            <header><div><h2>Plans</h2><p>Plans offered in the app. Prices are set in PayPal.</p></div></header>
            <ul class="ap-list" style="padding:4px 22px">
            @forelse($plans as $plan)
                <li><div><strong>{{ $plan->name }}</strong><small>{{ $plan->currency }} {{ $plan->amount }} · {{ $plan->type }} · {{ (int) ($subscribers[$plan->plan_id] ?? 0) }} active</small></div>
                    <form method="post" action="{{ route('workspace.plan-status', $plan->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="status" value="{{ $plan->status ? 0 : 1 }}"><button class="ap-link-btn {{ $plan->status ? 'ap-link-btn--danger' : '' }}">{{ $plan->status ? 'Hide' : 'Offer' }}</button></form></li>
            @empty
                <li><span class="ap-muted">No plans yet. Create them in PayPal and sync with the plan tools.</span></li>
            @endforelse
            </ul>
        </section>
        <section class="ap-panel">
            <header><div><h2>Free trial</h2><p>{{ $trial['minutes'] }} minutes · {{ $trial['days'] }} days · {{ $trial['calls'] }} calls. Minutes {{ $trial['rounding'] === 'second' ? 'count every second' : 'round up per call' }}.</p></div><a href="{{ route('workspace.settings', ['context' => $context['id']]) }}#trial">Change →</a></header>
        </section>
        <section class="ap-panel">
            <header><h2>Recent minute adjustments</h2></header>
            <div class="ap-panel-body">@include('workspace.admin.activity', ['entries' => $adjustments])</div>
        </section>
    </div>
</div>

<x-ap.dialog id="adjust-minutes" title="Adjust video minutes" sub="Adds minutes to, or removes them from, the person's active subscription or trial. The reason is kept in the activity log.">
    <form method="post" action="{{ route('workspace.minutes-adjust') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="adjust-minutes">
        <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" value="{{ old('email') }}"></label>
        <label>Minutes <small>(use a minus sign to remove)</small><input type="number" name="minutes" required min="-6000" max="6000" value="{{ old('minutes') }}"></label>
        <label>Reason<input name="reason" required maxlength="200" value="{{ old('reason') }}" placeholder="e.g. Call dropped, goodwill credit"></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Save adjustment</button></div>
    </form>
</x-ap.dialog>
@endsection
