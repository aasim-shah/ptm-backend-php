@extends('workspace.layout')
@section('workspace-content')
@php $support = \App\Services\WorkspaceSupport::class; @endphp
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Support</p><h1>Support</h1><p>{{ $handler ? 'Tickets raised in your scope. Reply, add internal notes and resolve.' : 'Ask the Parent Teacher Mobile team for help and follow every reply here.' }}</p></div></header>

<div class="ws-report-types" role="list">
    <a role="listitem" class="ws-report-type" href="{{ route('workspace.support', ['context' => $context['id']]) }}" @if(!request('status')) aria-current="page" @endif><strong>All tickets</strong><span>{{ number_format($counts->sum()) }} in total</span></a>
    @foreach($support::STATUSES as $key => $label)
        <a role="listitem" class="ws-report-type" href="{{ route('workspace.support', ['context' => $context['id'], 'status' => $key]) }}" @if(request('status') === $key) aria-current="page" @endif><strong>{{ $label }}</strong><span>{{ number_format($counts[$key] ?? 0) }} tickets</span></a>
    @endforeach
</div>

<div class="ws-grid">
    <section class="ws-card"><h2>Tickets</h2>
        <div class="ws-table-wrap"><table><thead><tr><th>#</th><th>Subject</th><th>Requester</th><th>School</th><th>Priority</th><th>Status</th><th>Updated (UTC)</th></tr></thead><tbody>
        @forelse($tickets as $ticket)<tr>
            <td>{{ $ticket->id }}</td>
            <td><a href="{{ route('workspace.support-ticket', ['id' => $ticket->id, 'context' => $context['id']]) }}">{{ $ticket->subject }}</a><br><small class="ws-meta">{{ $support::CATEGORIES[$ticket->category] ?? $ticket->category }}</small></td>
            <td>{{ $ticket->first_name }} {{ $ticket->last_name }}</td><td>{{ $ticket->school_name ?? 'Platform' }}</td>
            <td><span class="ws-status ws-status--{{ $ticket->priority === 'normal' ? 'active' : 'pending' }}">{{ $support::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</span></td>
            <td><span class="ws-status ws-status--{{ in_array($ticket->status, ['open', 'in_progress']) ? 'pending' : 'inactive' }}">{{ $support::STATUSES[$ticket->status] ?? $ticket->status }}</span></td>
            <td>{{ $ticket->updated_at }}</td></tr>
        @empty<tr><td colspan="7" class="ws-empty-line">No tickets here.</td></tr>@endforelse
        </tbody></table></div>{{ $tickets->links('pagination::simple-bootstrap-4') }}
    </section>
    <section class="ws-card">@include('workspace.form-heading', ['icon' => 'life-ring', 'tone' => 'blue', 'title' => 'Open a support ticket', 'description' => 'Tell us what happened. Never include passwords.'])
        <form method="post" action="{{ route('workspace.support-store') }}" class="ws-form ws-form--stack">@csrf
            <input type="hidden" name="context" value="{{ $context['id'] }}">
            @if(count($context['schools']) > 1 && $context['role'] !== 'super_admin')<label>School<select name="school_id" required>@foreach($context['schools'] as $id => $name)<option value="{{ $id }}" @selected((string) old('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select></label>@endif
            <label>Subject<input name="subject" maxlength="160" required value="{{ old('subject') }}"></label>
            <label>Category<select name="category" required>@foreach($support::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>Priority<select name="priority">@foreach($support::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>Details<textarea name="body" maxlength="5000" rows="5" required>{{ old('body') }}</textarea></label>
            <div class="ws-form-actions"><button class="ws-btn" type="submit">Submit ticket</button></div>
        </form>
    </section>
</div>
@endsection
