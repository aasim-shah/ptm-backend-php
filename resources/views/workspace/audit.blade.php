@extends('workspace.layout')
@section('workspace-content')
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Audit / Activity Log</p><h1>Audit / Activity Log</h1><p>Administrative actions recorded in {{ $context['organization'] }}.</p></div></header>
<section class="ws-card ws-filter-card">
    <form method="get" action="{{ route('workspace.audit') }}" class="ws-form ws-filter-form">
        <input type="hidden" name="context" value="{{ $context['id'] }}">
        <label>Action starts with<input name="action" value="{{ request('action') }}" placeholder="e.g. report, membership, delegation" maxlength="80"></label>
        <div class="ws-form-actions"><button class="ws-btn" type="submit">Filter</button></div>
    </form>
</section>
<section class="ws-card"><div class="ws-table-wrap"><table><thead><tr><th>When (UTC)</th><th>Action</th><th>Actor</th><th>School</th><th>Subject</th></tr></thead><tbody>
    @forelse($entries as $entry)<tr><td>{{ $entry->created_at }}</td><td><span class="ws-badge">{{ $entry->action }}</span></td><td>{{ trim($entry->first_name.' '.$entry->last_name) ?: 'Account #'.$entry->actor_id }}</td><td>{{ $entry->school_name ?? 'Platform' }}</td><td>{{ $entry->subject }}</td></tr>
    @empty<tr><td colspan="5" class="ws-empty-line">No recorded activity.</td></tr>@endforelse
</tbody></table></div>{{ $entries->links('pagination::simple-bootstrap-4') }}</section>
@endsection
