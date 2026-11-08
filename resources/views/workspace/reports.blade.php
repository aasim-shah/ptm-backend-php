@extends('workspace.layout')
@section('workspace-content')
@php
    $query = array_filter(['context' => $context['id'], 'type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
        'school_id' => request('school_id'), 'role' => request('role')]);
    $dated = in_array($type, ['activity', 'video_usage', 'subscriptions', 'audit'], true);
@endphp
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Reports & Exports</p><h1>Reports & Exports</h1><p>Generate, filter and download reports for {{ $context['organization'] }}. Every export is recorded in the activity log.</p></div></header>

<div class="ws-report-types" role="list">
    @foreach($types as $key => $definition)
        <a role="listitem" class="ws-report-type" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => $key]) }}" @if($type === $key) aria-current="page" @endif>
            <strong>{{ $definition['title'] }}</strong><span>{{ $definition['description'] }}</span>
        </a>
    @endforeach
</div>

<section class="ws-card ws-filter-card no-print" aria-label="Report filters">
    <form method="get" action="{{ route('workspace.reports') }}" class="ws-form ws-filter-form">
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="type" value="{{ $type }}">
        @if($dated)
            <label>From<input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}"></label>
            <label>To<input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}"></label>
        @endif
        @if(count($context['schools']) > 1 && $type !== 'subscriptions')
            <label>School<select name="school_id"><option value="">All schools in scope</option>@foreach($context['schools'] as $id => $name)<option value="{{ $id }}" @selected((string) request('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select></label>
        @endif
        @if($type === 'people')
            <label>Role<select name="role"><option value="">All roles</option>@foreach(['parent', 'teacher', 'staff', 'school_admin', 'district_admin'] as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ \App\Services\WorkspaceAccess::LABELS[$role] }}</option>@endforeach</select></label>
        @endif
        <div class="ws-form-actions"><button class="ws-btn" type="submit">Update preview</button></div>
    </form>
</section>

<section class="ws-card ws-report-preview">
    <div class="ws-card-heading">
        <div><h2>{{ $report['title'] }}</h2><p class="ws-meta">{{ $context['organization'] }}@if($dated) · {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}@endif · {{ number_format(count($report['rows'])) }} rows</p></div>
        <form method="post" action="{{ route('workspace.report-export') }}" class="ws-export-actions no-print">@csrf
            @foreach($query as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <button class="ws-btn ws-btn-small" name="format" value="xlsx"><i class="fa fa-file-excel-o" aria-hidden="true"></i>Excel (XLSX)</button>
            <button class="ws-btn ws-btn-small ws-btn-secondary" name="format" value="csv"><i class="fa fa-file-text-o" aria-hidden="true"></i>CSV</button>
            <button class="ws-btn ws-btn-small ws-btn-secondary" name="format" value="pdf"><i class="fa fa-file-pdf-o" aria-hidden="true"></i>PDF</button>
            <button type="button" class="ws-btn ws-btn-small ws-btn-secondary" onclick="window.print()"><i class="fa fa-print" aria-hidden="true"></i>Print</button>
        </form>
    </div>
    @if($report['truncated'])<p class="ws-alert ws-error">The preview is limited to the first {{ number_format($report['limit']) }} rows. Excel and CSV exports include up to {{ number_format(\App\Services\WorkspaceReports::EXPORT_MAX_ROWS) }} rows.</p>@endif
    <div class="ws-table-wrap"><table><thead><tr>@foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
        @forelse(array_slice($report['rows'], 0, 100) as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
        @empty<tr><td colspan="{{ count($report['columns']) }}" class="ws-empty-line">No records match these filters.</td></tr>@endforelse
    </tbody></table></div>
    @if(count($report['rows']) > 100)<p class="ws-meta">Showing the first 100 rows. Download the report for all {{ number_format(count($report['rows'])) }} rows.</p>@endif
</section>
<section class="ws-card no-print" id="exports"><div class="ws-card-heading"><h2>Your exports</h2><span class="ws-meta">Files are private to you and deleted after {{ \App\Jobs\GenerateWorkspaceExport::LIFETIME_HOURS }} hours.</span></div>
    <div class="ws-table-wrap"><table><thead><tr><th>Report</th><th>Format</th><th>Requested (UTC)</th><th>Status</th><th>Download</th></tr></thead><tbody>
    @forelse($exports as $export)<tr><td>{{ $types[$export->type]['title'] ?? $export->type }}</td><td>{{ strtoupper($export->format) }}</td><td>{{ $export->created_at }}</td>
        <td><span class="ws-status ws-status--{{ $export->status === 'ready' ? 'active' : ($export->status === 'queued' ? 'pending' : 'failed') }}">{{ $export->status === 'ready' && $export->expires_at <= now()->toDateTimeString() ? 'Expired' : ucfirst($export->status) }}</span>@if($export->error)<small class="ws-meta"> {{ $export->error }}</small>@endif</td>
        <td>@if($export->status === 'ready' && $export->expires_at > now()->toDateTimeString())<a href="{{ route('workspace.report-file', ['id' => $export->id, 'context' => $context['id']]) }}">Download ({{ number_format($export->rows) }} rows)</a>@else — @endif</td></tr>
    @empty<tr><td colspan="5" class="ws-empty-line">No exports in the last 7 days.</td></tr>@endforelse
    </tbody></table></div>
    @if($exports->contains('status', 'queued'))<script>setTimeout(function () { if (!document.hidden) location.reload(); }, 10000);</script>@endif
</section>
@endsection
