@extends('workspace.layout')
@section('workspace-content')
@php
    $actionLabels = ['connect' => ['Connect', 'active'], 'invite' => ['Invite', 'pending'], 'create' => ['Add', 'active'], 'skip' => ['Skip', 'none'], 'error' => ['Problem', 'revoked']];
@endphp
<x-ap.head :eyebrow="$context['label'].' / Bulk Imports'" title="Bulk imports" sub="Add many people or classes at once: download the template, fill it in, upload it, check the preview, then confirm. Nothing changes until you confirm.">
    @foreach($kinds as $key => [$label])<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.import-template', ['context' => $context['id'], 'kind' => $key]) }}"><i class="fa fa-download" aria-hidden="true"></i>{{ $label }} template</a>@endforeach
</x-ap.head>

@if($current)
<section class="ap-panel">
    <header>
        <div><h2>{{ $current->status === 'preview' ? 'Check before importing' : 'Import results' }} · {{ \App\Http\Controllers\Workspace\ImportsController::KINDS[$current->kind][0] }}</h2>
            <p>@if($current->status === 'preview'){{ count($current->rows) }} rows checked. Rows marked “Problem” will not be imported; fix them in the file and upload it again, or continue without them.@else Finished {{ $formatTime($current->updated_at) }}.@endif</p></div>
        @if($current->status === 'preview')
        <div class="ap-head-actions">
            <form method="post" action="{{ route('workspace.import-discard', $current->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn ap-btn-ghost">Discard</button></form>
            <form method="post" action="{{ route('workspace.import-confirm', $current->id) }}" class="ap-inline" data-confirm="Import {{ count(array_filter($current->rows, fn ($r) => in_array($r['action'], ['connect', 'invite', 'create'], true))) }} rows now?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn">Confirm import</button></form>
        </div>
        @endif
    </header>
    @if($current->status === 'preview')
    <div class="ap-toolbar">@foreach($current->summary as $action => $n)<span class="ap-chip">{{ $actionLabels[$action][0] ?? ucfirst($action) }}: {{ $n }}</span>@endforeach @if(($current->summary['invite'] ?? 0) && !config('delivery.email_enabled'))<span class="ap-pill ap-pill--warn">Email delivery is off, so invitations will not be sent</span>@endif</div>
    @endif
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>#</th>@if($current->kind === 'people')<th>Person</th><th>Role</th>@elseif($current->kind === 'students')<th>Student</th><th>Class / section</th>@else<th>Class / section</th><th>Teacher</th>@endif<th>School</th><th>{{ $current->status === 'preview' ? 'Will' : 'Result' }}</th><th>Details</th></tr></thead>
        <tbody>
        @foreach($current->rows as $i => $row)
            <tr><td class="ap-muted">{{ $i + 2 }}</td>
                @if($current->kind === 'people')<td><strong>{{ $row['name'] ?: '—' }}</strong><div class="ap-muted">{{ $row['email'] }}</div></td><td>{{ \App\Services\WorkspaceAccess::LABELS[$row['role']] ?? $row['role'] }}</td>
                @elseif($current->kind === 'students')<td><strong>{{ $row['name'] ?: '—' }}</strong>@if($row['admission_no'])<div class="ap-muted">{{ $row['admission_no'] }}</div>@endif</td><td>{{ $row['class'] }} <span class="ap-muted">/ {{ $row['section'] }}</span></td>
                @else<td><strong>{{ $row['class'] }}</strong> <span class="ap-muted">/ {{ $row['section'] }}</span></td><td>{{ $row['teacher_email'] }}</td>@endif
                <td>{{ $row['school'] ?: '—' }}</td>
                <td>@if($current->status === 'preview')<x-ap.pill :status="$actionLabels[$row['action']][1] ?? 'none'" :label="$actionLabels[$row['action']][0] ?? $row['action']" />@else{{ $row['result'] ?? '—' }}@endif</td>
                <td class="ap-muted">{{ $row['note'] }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif

<div class="ap-settings-grid">
    <section class="ap-panel">
        <header><div><h2>Upload a file</h2><p>CSV files up to 1 MB and {{ number_format(\App\Http\Controllers\Workspace\ImportsController::MAX_ROWS) }} rows. Save Excel sheets as “CSV UTF-8”.</p></div></header>
        <div class="ap-panel-body">
            <form method="post" action="{{ route('workspace.import-upload') }}" enctype="multipart/form-data" class="ap-form">@csrf
                <input type="hidden" name="context" value="{{ $context['id'] }}">
                <label class="ap-span">What are you importing?<select name="kind" required>@foreach($kinds as $key => [$label, $columns, $help])<option value="{{ $key }}" @selected(old('kind') === $key)>{{ $label }} — {{ $help }}</option>@endforeach</select></label>
                <label class="ap-span">CSV file<input type="file" name="file" accept=".csv,text/csv" required></label>
                <p class="ap-hint">Optional columns: school (when you manage one school), admission_no and guardian_email. Columns: @foreach($kinds as $key => [$label, $columns])<strong>{{ $label }}</strong>: {{ implode(', ', $columns) }}@if(!$loop->last) · @endif @endforeach. @if(count($context['schools']) === 1)The school column is optional.@else Use the exact school name or its ID.@endif</p>
                <div class="ap-form-actions"><button class="ws-btn">Upload and check</button></div>
            </form>
        </div>
    </section>
    <section class="ap-panel">
        <header><div><h2>Recent imports</h2><p>Your last 10 imports in this workspace.</p></div></header>
        <ul class="ap-list" style="padding:4px 22px">
        @forelse($recent as $item)
            @php $sum = json_decode($item->summary ?? '[]', true) ?: []; @endphp
            <li><div><a href="{{ route('workspace.imports', ['context' => $context['id'], 'import' => $item->id]) }}"><strong>{{ \App\Http\Controllers\Workspace\ImportsController::KINDS[$item->kind][0] ?? $item->kind }}</strong></a><small>{{ $formatTime($item->created_at) }} · @if($item->status === 'completed'){{ $sum['done'] ?? 0 }} done, {{ $sum['not_done'] ?? 0 }} not imported @else{{ collect($sum)->sum() }} rows @endif</small></div><x-ap.pill :status="$item->status === 'completed' ? 'active' : ($item->status === 'preview' ? 'pending' : 'none')" :label="['completed' => 'Completed', 'preview' => 'Waiting for confirmation', 'discarded' => 'Discarded'][$item->status] ?? $item->status" /></li>
        @empty
            <li><span class="ap-muted">No imports yet.</span></li>
        @endforelse
        </ul>
    </section>
</div>
@endsection
