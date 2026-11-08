@extends('workspace.layout')
@section('workspace-content')
@php
    $url = fn($target, $query = []) => route('workspace.page', ['page' => $target, 'context' => $context['id']] + $query);
    $rounding = app(\App\Services\PlatformSettings::class)->get('video.rounding');
    $hasContacts = $access->contacts(auth()->user(), $context)->exists();
@endphp
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Video Calls</p><h1>Video Calls</h1><p>{{ $context['organization'] }} · {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}. Calls are never recorded.</p></div></header>

<section class="ws-card ws-video-center">
    <div class="ws-video-center__intro"><span class="ws-connection-icon" aria-hidden="true"><i class="fa fa-video-camera"></i></span><div><h2>Video Communication Center</h2><p>Calls take place in the Parent Teacher Mobile app. Minutes are charged to the call ledger: {{ $rounding === 'second' ? 'every second counts, with no rounding' : 'each call is rounded up to the next whole minute' }}.@if(\App\Services\WorkspaceAccess::platformWide($context)) <a href="{{ route('workspace.settings', ['context' => $context['id']]) }}#video">Change policy</a>@endif</p></div></div>
    <dl class="ws-video-center__stats">
        <div><dt>Minutes</dt><dd>{{ number_format($totals['video_minutes']) }}</dd></div>
        <div><dt>Calls</dt><dd>{{ number_format($totals['video_calls']) }}</dd></div>
        <div><dt>Average call</dt><dd>{{ $totals['video_calls'] ? round($totals['video_minutes'] / $totals['video_calls'], 1) : 0 }} min</dd></div>
    </dl>
    <div class="ws-video-center__actions">
        @if($hasContacts)<a class="ws-btn" href="{{ $url('schedule') }}"><i class="fa fa-calendar-plus-o" aria-hidden="true"></i>Schedule video call</a>@endif
        @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $days => $label)
            <a class="ws-btn ws-btn-secondary" href="{{ $url('video', ['from' => now()->subDays($days - 1)->toDateString(), 'to' => now()->toDateString()]) }}">{{ $label }}</a>
        @endforeach
    </div>
</section>

<div class="ws-grid">
    <section class="ws-card" id="upcoming"><div class="ws-card-heading"><h2>Upcoming calls</h2>@if($hasContacts)<a href="{{ $url('schedule') }}">Schedule →</a>@endif</div>
        @forelse($meetings as $meeting)<article class="ws-row"><div><strong>{{ $meeting->title }}</strong><p>{{ \Carbon\Carbon::parse($meeting->starts_at, 'UTC')->format('j M · H:i') }} UTC · {{ $context['schools'][$meeting->school_id] ?? '' }}</p></div><span class="ws-badge">{{ ucfirst($meeting->status) }}</span></article>@empty<p class="ws-empty-line">No upcoming calls.</p>@endforelse
    </section>
    <section class="ws-card" id="history"><h2>Recent calls</h2>
        <div class="ws-table-wrap"><table><thead><tr><th>Participant</th><th>Minutes{{ $rounding === 'second' ? '' : ' (rounded up)' }}</th><th>Source</th><th>When (UTC)</th></tr></thead><tbody>
            @forelse($calls as $call)<tr><td>{{ $call->first_name }} {{ $call->last_name }}</td><td>{{ $rounding === 'second' ? round($call->seconds / 60, 1) : (int) ceil($call->seconds / 60) }}</td><td>{{ ucfirst(str_replace('_', ' ', $call->source)) }}</td><td>{{ \Carbon\Carbon::parse($call->created_at)->format('j M Y · H:i') }}</td></tr>
            @empty<tr><td colspan="4" class="ws-empty-line">No calls in this period.</td></tr>@endforelse
        </tbody></table></div>{{ $calls->links('pagination::simple-bootstrap-4') }}
    </section>
</div>
@endsection
