@extends('workspace.layout')
@section('workspace-content')
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Subscriptions & Billing</p><h1>Subscriptions & Billing</h1><p>Individual plans and trials across the platform.</p></div></header>
<div class="ws-stats">@foreach($summary as $stat)
    <div class="ws-stat"><span class="ws-stat-icon" aria-hidden="true"><i class="fa fa-{{ $stat['icon'] }}"></i></span><div class="ws-stat-content"><p>{{ $stat['label'] }}</p><strong>{{ number_format($stat['value']) }}</strong><span>All markets</span></div></div>
@endforeach</div>
<section class="ws-card"><div class="ws-card-heading"><h2>Subscriptions</h2><a href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'subscriptions']) }}">Export report →</a></div>
    <div class="ws-table-wrap"><table><thead><tr><th>Account</th><th>Email</th><th>Plan</th><th>Type</th><th>Status</th><th>Activated</th><th>Expires</th></tr></thead><tbody>
        @forelse($subscriptions as $subscription)<tr><td>{{ trim($subscription->first_name.' '.$subscription->last_name) }}</td><td>{{ $subscription->email }}</td><td>{{ $subscription->plan_id }}</td><td>{{ $subscription->type }}</td><td><span class="ws-status ws-status--{{ strtolower($subscription->status) }}">{{ ucfirst(strtolower($subscription->status)) }}</span></td><td>{{ $subscription->activated_date }}</td><td>{{ $subscription->expire_date }}</td></tr>
        @empty<tr><td colspan="7" class="ws-empty-line">No subscriptions yet.</td></tr>@endforelse
    </tbody></table></div>{{ $subscriptions->links('pagination::simple-bootstrap-4') }}
</section>
@endsection
