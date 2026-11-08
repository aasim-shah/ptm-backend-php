@extends('workspace.layout')
@section('workspace-content')
@php
    $url = fn($target, $query = []) => route('workspace.page', ['page' => $target, 'context' => $context['id']] + $query);
    $range = fn($days, $grain) => $url('analytics', ['from' => now()->subDays($days - 1)->toDateString(), 'to' => now()->toDateString(), 'grain' => $grain]);
    $percent = fn($value) => $value === null ? '—' : $value.'%';
@endphp
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Analytics & Insights</p><h1>Analytics & Insights</h1><p>{{ $context['organization'] }} · {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}</p></div></header>

<section class="ws-card ws-filter-card" aria-label="Date range">
    <form method="get" action="{{ route('workspace.analytics') }}" class="ws-form ws-filter-form">
        <input type="hidden" name="context" value="{{ $context['id'] }}">
        <label>From<input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}"></label>
        <label>To<input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}"></label>
        <label>Group by<select name="grain">@foreach(\App\Services\WorkspaceAnalytics::GRAINS as $key => $label)<option value="{{ $key }}" @selected($grain === $key)>{{ $label }}</option>@endforeach</select></label>
        <div class="ws-form-actions"><button class="ws-btn" type="submit">Apply</button></div>
    </form>
    <p class="ws-quick-ranges">Quick ranges:
        <a href="{{ $range(30, 'day') }}">Last 30 days</a>
        <a href="{{ $range(91, 'week') }}">Last quarter</a>
        <a href="{{ $range(183, 'month') }}">Last 6 months</a>
        <a href="{{ $range(365, 'month') }}">Last 12 months</a>
        <a href="{{ $range(730, 'quarter') }}">Last 2 years</a>
    </p>
</section>

<div class="ws-stats">@foreach($kpis as $stat)
    <div class="ws-stat"><span class="ws-stat-icon" aria-hidden="true"><i class="fa fa-{{ $stat['icon'] }}"></i></span><div class="ws-stat-content"><p>{{ $stat['label'] }}</p><strong data-kpi="{{ $stat['key'] }}">{{ number_format($stat['value']) }}</strong><span>Current total</span></div></div>
@endforeach</div>

<dl class="ws-metric-row">
    <div><dt>Video minutes</dt><dd data-total="video_minutes">{{ number_format($totals['video_minutes']) }}</dd></div>
    <div><dt>Video calls</dt><dd data-total="video_calls">{{ number_format($totals['video_calls']) }}</dd></div>
    <div><dt>Announcement read rate</dt><dd>{{ $percent($totals['announcement_read_rate']) }}</dd></div>
    <div><dt>Event read rate</dt><dd>{{ $percent($totals['event_read_rate']) }}</dd></div>
    <div><dt>Student updates published</dt><dd>{{ $percent($totals['update_completion']) }}</dd></div>
    <div><dt>Classes</dt><dd>{{ number_format($totals['classes']) }}</dd></div>
</dl>

<div class="ws-chart-grid ws-chart-grid--two">
    <section class="ws-card ws-chart-card"><h2>User growth</h2><p class="ws-meta">New {{ $context['role'] === 'super_admin' ? 'platform accounts' : 'school memberships' }} and new members by role</p><div class="ws-chart ws-chart--tall"><canvas id="a-growth" role="img" aria-label="User growth chart"></canvas></div></section>
    <section class="ws-card ws-chart-card"><h2>Engagement & activity trends</h2><p class="ws-meta">Distinct active users and everyday activity</p><div class="ws-chart ws-chart--tall"><canvas id="a-active" role="img" aria-label="Active users and activity chart"></canvas></div></section>
    <section class="ws-card ws-chart-card"><h2>Video call usage & minutes</h2><p class="ws-meta">Minutes charted to the call ledger and number of calls</p><div class="ws-chart ws-chart--tall"><canvas id="a-video" role="img" aria-label="Video usage chart"></canvas></div></section>
    <section class="ws-card ws-chart-card"><h2>Events & announcements</h2><p class="ws-meta">Published announcements, events and student updates</p><div class="ws-chart ws-chart--tall"><canvas id="a-posts" role="img" aria-label="Events and announcements chart"></canvas></div></section>
    <section class="ws-card ws-chart-card"><h2>Subscriptions</h2><p class="ws-meta">New subscriptions and trials for connected accounts</p><div class="ws-chart ws-chart--tall"><canvas id="a-subscriptions" role="img" aria-label="Subscriptions chart"></canvas></div></section>
    <section class="ws-card ws-chart-card"><h2>People by role</h2><p class="ws-meta">Active members today</p><div class="ws-chart ws-chart--tall"><canvas id="a-roles" role="img" aria-label="People by role chart"></canvas></div></section>
</div>

<section class="ws-card"><details><summary>View the data behind these charts</summary>
    <div class="ws-table-wrap"><table><thead><tr><th>Period</th><th>Registrations</th><th>Active users</th><th>Messages</th><th>Meetings</th><th>Announcements</th><th>Events</th><th>Updates</th><th>Video minutes</th><th>Subscriptions</th></tr></thead><tbody>
    @foreach($series['labels'] as $i => $label)<tr><td>{{ $label }}</td><td>{{ $series['registrations'][$i] }}</td><td>{{ $series['active_users'][$i] }}</td><td>{{ $series['messages'][$i] }}</td><td>{{ $series['meetings'][$i] }}</td><td>{{ $series['announcements'][$i] }}</td><td>{{ $series['events'][$i] }}</td><td>{{ $series['updates'][$i] }}</td><td>{{ $series['video_minutes'][$i] }}</td><td>{{ $series['subscriptions'][$i] }}</td></tr>@endforeach
    </tbody></table></div></details>
    <p class="ws-meta" id="analytics-freshness">Updated {{ now()->toIso8601String() }} · refreshes every 60 seconds</p>
</section>

@include('workspace.chart-script')
@push('workspace-scripts')
<script>
(function () {
    var charts = {};
    function draw(s, kpis) {
        Object.keys(charts).forEach(function (key) { charts[key].destroy(); });
        var roles = {}; kpis.forEach(function (k) { roles[k.key] = k.value; });
        charts.growth = ptmChart('a-growth', 'line', s.labels, [{label: 'All registrations', data: s.registrations}, {label: 'Parents', data: s.parents}, {label: 'Teachers', data: s.teachers}, {label: 'Staff', data: s.staff}]);
        charts.active = ptmChart('a-active', 'line', s.labels, [{label: 'Active users', data: s.active_users}, {label: 'Messages', data: s.messages}, {label: 'Meetings', data: s.meetings}]);
        charts.video = ptmChart('a-video', 'bar', s.labels, [{label: 'Minutes', data: s.video_minutes, colour: '#7b4fd6'}, {label: 'Calls', data: s.video_calls, colour: '#f08c1a'}]);
        charts.posts = ptmChart('a-posts', 'bar', s.labels, [{label: 'Announcements', data: s.announcements, colour: '#d63a83'}, {label: 'Events', data: s.events, colour: '#f08c1a'}, {label: 'Student updates', data: s.updates, colour: '#16a36a'}], {stacked: true});
        charts.subscriptions = ptmChart('a-subscriptions', 'line', s.labels, [{label: 'New subscriptions', data: s.subscriptions, colour: '#0e9aa7'}]);
        charts.roles = ptmChart('a-roles', 'doughnut', ['Parents', 'Teachers', 'Staff', 'Students'], [{data: [roles.parents, roles.teachers, roles.staff, roles.students], colour: '#176ce0'}]);
        if (charts.roles) { charts.roles.data.datasets[0].backgroundColor = ['#176ce0', '#16a36a', '#7b4fd6', '#f08c1a']; charts.roles.update(); }
    }
    draw(@json($series), @json($kpis));
    setInterval(async function () {
        if (document.hidden) return;
        try {
            var response = await fetch(location.href, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) { if ([401, 403].includes(response.status)) location.reload(); return; }
            var data = await response.json();
            draw(data.series, data.kpis);
            data.kpis.forEach(function (k) { var node = document.querySelector('[data-kpi="' + k.key + '"]'); if (node) node.textContent = new Intl.NumberFormat().format(k.value); });
            ['video_minutes', 'video_calls'].forEach(function (key) { var node = document.querySelector('[data-total="' + key + '"]'); if (node) node.textContent = new Intl.NumberFormat().format(data.totals[key]); });
            document.getElementById('analytics-freshness').textContent = 'Updated ' + data.as_of + ' · refreshes every 60 seconds';
        } catch (_) {}
    }, 60000);
})();
</script>
@endpush
@endsection
