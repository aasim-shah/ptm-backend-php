@php
    $chart = $overview['chart'];
    $video = $overview['video'];
    $roleLabels = \App\Services\WorkspaceAccess::LABELS;
    // Chart titles follow the client's per-panel reference mockups.
    [$growthTitle, $activityTitle] = ['super_admin' => ['User growth', 'Platform activity'], 'sub_super_admin' => ['Organization growth', 'User activity'],
        'district_admin' => ['School enrollment', 'Engagement overview'], 'school_admin' => ['Student enrollment', 'Activity overview']][$context['role']];
    $completion = $overview['completion'];
    $breakdown = $overview['breakdown'];
    $completionRate = array_sum($completion) ? round($completion['published'] / array_sum($completion) * 100) : null;
@endphp
<section class="ws-card ws-video-center" aria-labelledby="video-center-title">
    <div class="ws-video-center__intro">
        <span class="ws-connection-icon" aria-hidden="true"><i class="fa fa-video-camera"></i></span>
        <div><h2 id="video-center-title">Video Communication Center</h2><p>Connect with parents, teachers, staff and schools.</p></div>
    </div>
    <dl class="ws-video-center__stats">
        <div><dt>Minutes this month</dt><dd>{{ number_format($video['video_minutes']) }}</dd></div>
        <div><dt>Calls this month</dt><dd>{{ number_format($video['video_calls']) }}</dd></div>
        <div><dt>Upcoming meetings</dt><dd>{{ number_format($overview['upcoming']) }}</dd></div>
    </dl>
    <div class="ws-video-center__actions">
        @if($access->contacts(auth()->user(), $context)->exists())<a class="ws-btn" href="{{ $url('schedule') }}"><i class="fa fa-calendar-plus-o" aria-hidden="true"></i>Schedule video call</a>@endif
        <a class="ws-btn ws-btn-secondary" href="{{ $url('video') }}#upcoming"><i class="fa fa-clock-o" aria-hidden="true"></i>Upcoming calls</a>
        <a class="ws-btn ws-btn-secondary" href="{{ $url('video') }}#history"><i class="fa fa-history" aria-hidden="true"></i>Recent calls</a>
    </div>
</section>

<div class="ws-chart-grid">
    <section class="ws-card ws-chart-card"><div class="ws-card-heading"><h2>{{ $growthTitle }} <small>last 6 months</small></h2>@if($access->can($context, 'analytics.view'))<a href="{{ $url('analytics') }}">Analytics →</a>@endif</div>
        <div class="ws-chart"><canvas id="chart-growth" role="img" aria-label="New registrations and active users per month"></canvas></div></section>
    <section class="ws-card ws-chart-card"><div class="ws-card-heading"><h2>{{ $activityTitle }} <small>last 6 months</small></h2>@if($access->can($context, 'analytics.view'))<a href="{{ $url('analytics') }}">Analytics →</a>@endif</div>
        <div class="ws-chart"><canvas id="chart-activity" role="img" aria-label="Messages, meetings, announcements, events and student updates per month"></canvas></div></section>
    <section class="ws-card ws-chart-card"><div class="ws-card-heading"><h2>Video call minutes <small>last 6 months</small></h2><a href="{{ $url('video') }}">Video →</a></div>
        <div class="ws-chart"><canvas id="chart-video" role="img" aria-label="Video call minutes per month"></canvas></div></section>
</div>
<div class="ws-chart-grid ws-chart-grid--two">
    <section class="ws-card ws-chart-card"><div class="ws-card-heading"><h2>{{ $breakdown['title'] }} <small>students{{ $breakdown['classes'] !== null ? ' and classes' : '' }}</small></h2>@if($access->can($context, 'reports.export'))<a href="{{ $url('reports') }}">Reports →</a>@endif</div>
        @if($breakdown['labels'])<div class="ws-chart"><canvas id="chart-breakdown" role="img" aria-label="{{ $breakdown['title'] }}"></canvas></div>@else<p class="ws-empty-line">No classes set up yet.</p>@endif</section>
    <section class="ws-card ws-chart-card"><div class="ws-card-heading"><h2>Report completion <small>student updates, last 6 months</small></h2>@if($completionRate !== null)<span class="ws-badge">{{ $completionRate }}% published</span>@endif</div>
        @if(array_sum($completion))<div class="ws-chart"><canvas id="chart-completion" role="img" aria-label="Published and draft student updates"></canvas></div>@else<p class="ws-empty-line">No student updates in the last 6 months.</p>@endif</section>
</div>

<div class="ws-grid ws-grid--even">
    <section class="ws-card" aria-labelledby="attention-title"><div class="ws-card-heading"><h2 id="attention-title"><i class="fa fa-exclamation-triangle ws-attention-icon" aria-hidden="true"></i> Attention needed</h2></div>
        @foreach($overview['attention'] as $item)
            <a class="ws-attention-row" href="{{ $url($item['page']) }}"><span class="ws-attention-row__icon" aria-hidden="true"><i class="fa fa-{{ $item['icon'] }}"></i></span><span class="ws-grow"><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span><span class="ws-count @if($item['count']) ws-count--alert @endif">{{ number_format($item['count']) }}</span><span aria-hidden="true">›</span></a>
        @endforeach
    </section>
    <section class="ws-card" aria-labelledby="signups-title"><div class="ws-card-heading"><h2 id="signups-title">Recent sign ups</h2>@if($access->can($context, 'memberships.manage'))<a href="{{ $url('people') }}">View all →</a>@endif</div>
        <div class="ws-table-wrap"><table><thead><tr><th>Name</th><th>User type</th><th>Organization</th><th>Joined</th><th>Status</th></tr></thead><tbody>
        @forelse($overview['signups'] as $signup)
            @php $status = (int) $signup->account_status !== 1 ? 'Disabled' : (!$signup->email_verified_at ? 'Unverified' : ucfirst($signup->status)); @endphp
            <tr><td>{{ $signup->first_name }} {{ $signup->last_name }}</td><td><span class="ws-badge">{{ $roleLabels[$signup->role] ?? $signup->role }}</span></td><td>{{ $signup->school_name }}</td><td>{{ $signup->created_at ? $formatTime($signup->created_at, 'j M Y') : '—' }}</td><td><span class="ws-status ws-status--{{ strtolower($status) }}">{{ $status }}</span></td></tr>
        @empty<tr><td colspan="5" class="ws-empty-line">No sign ups in this workspace yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>

<div class="ws-grid">
    <div>
        @include('workspace.roles.'.$context['role'])
        <section class="ws-card" id="upcoming"><div class="ws-card-heading"><h2>Upcoming meetings</h2><a href="{{ $url('schedule') }}">View schedule →</a></div>
            @forelse($appointments as $meeting)<article class="ws-row"><div><strong>{{ $meeting->title }}</strong><p>{{ $formatTime($meeting->starts_at, 'j M · H:i') }} {{ $timezone }}</p></div><span class="ws-badge">{{ ucfirst($meeting->status) }}</span></article>@empty<p class="ws-empty-line">No upcoming meetings.</p>@endforelse
        </section>
    </div>
    <div>
        <section class="ws-card"><div class="ws-card-heading"><h2>Announcements</h2><a href="{{ $url('notices') }}">All →</a></div>@forelse($notices as $notice)<article class="ws-notice"><span class="ws-meta">{{ $formatTime($notice->created_at, 'j M Y') }}</span><h3>{{ $notice->title }}</h3><p>{{ \Illuminate\Support\Str::limit($notice->body, 180) }}</p></article>@empty<p class="ws-empty-line">No announcements for this workspace yet.</p>@endforelse</section>
        <section class="ws-card"><div class="ws-card-heading"><h2>Coming up</h2><a href="{{ $url('events') }}">Events →</a></div>@forelse($events as $event)<article class="ws-row"><div><strong>{{ $event->title }}</strong><p>{{ $formatTime($event->starts_at, 'j M · H:i') }} {{ $timezone }}</p></div></article>@empty<p class="ws-empty-line">No upcoming school events.</p>@endforelse</section>
    </div>
</div>

@include('workspace.chart-script')
@push('workspace-scripts')
<script>
(function () {
    var c = @json($chart);
    ptmChart('chart-growth', 'line', c.labels, [{label: 'New registrations', data: c.registrations}, {label: 'Active users', data: c.active_users}]);
    ptmChart('chart-activity', 'bar', c.labels, [{label: 'Messages', data: c.messages}, {label: 'Meetings', data: c.meetings}, {label: 'Announcements', data: c.announcements}, {label: 'Events', data: c.events}, {label: 'Student updates', data: c.updates}], {stacked: true});
    ptmChart('chart-video', 'bar', c.labels, [{label: 'Minutes', data: c.video_minutes, colour: '#7b4fd6'}]);
    var b = @json($breakdown), done = @json($completion);
    var sets = [{label: 'Students', data: b.students}];
    if (b.classes) { sets.push({label: 'Classes', data: b.classes}); }
    ptmChart('chart-breakdown', 'bar', b.labels, sets);
    ptmChart('chart-completion', 'doughnut', ['Published', 'Draft'], [{data: [done.published, done.draft], colours: ['#16a36a', '#d9dee8']}]);
})();
</script>
@endpush
