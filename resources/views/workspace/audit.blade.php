@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Audit / Activity Log'" title="Audit / Activity Log" :sub="'Who did what, and when, in '.$context['organization'].'. Includes sign-ins, failed sign-in attempts and every administrative change.'">
    @if($access->can($context, 'reports.export'))<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', array_filter(['context' => $context['id'], 'type' => 'audit', 'from' => request('from'), 'to' => request('to'), 'school_id' => request('school_id')])) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>@endif
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.audit')" :context="$context" placeholder="Search by person" :count="$entries->total()">
        <select name="category" aria-label="Type of activity"><option value="">All activity</option>@foreach($categories as $key => [$label]) <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach</select>
        @if(count($context['schools']) > 1)<select name="school_id" aria-label="School"><option value="">All schools</option>@foreach($context['schools'] as $id => $name)<option value="{{ $id }}" @selected((string) request('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select>@endif
        <input type="date" name="from" value="{{ request('from') }}" aria-label="From date" max="{{ now()->toDateString() }}">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="To date" max="{{ now()->toDateString() }}">
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>When ({{ $timezone }})</th><th>Activity</th><th>About</th><th>Done by</th><th>School</th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            <tr>
                <td class="ap-muted" style="white-space:nowrap">{{ $formatTime($entry->created_at, 'j M Y · H:i') }}</td>
                <td><strong>{{ $entry->action_label }}</strong><div class="ap-muted"><code>{{ $entry->action }}</code></div></td>
                <td>{{ $entry->subject_label ?: '—' }}</td>
                <td>@if($entry->first_name || $entry->email)<x-ap.person :first="$entry->first_name" :last="$entry->last_name" :email="$entry->email" />@else{{ $entry->actor_name }}@endif</td>
                <td>{{ $entry->school_name ?? 'Platform' }}</td>
            </tr>
        @empty
            <x-ap.empty colspan="5" icon="history" title="No activity matches these filters" />
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$entries" />
</section>
@endsection
