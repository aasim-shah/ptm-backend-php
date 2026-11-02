@extends('workspace.layout')
@section('workspace-content')
@php $support = \App\Services\WorkspaceSupport::class; $own = (int) $ticket->requester_id === (int) auth()->id(); @endphp
<header class="ws-page-heading"><div><p class="ws-eyebrow">{{ $context['label'] }} / Support / #{{ $ticket->id }}</p><h1>{{ $ticket->subject }}</h1>
    <p>{{ $support::CATEGORIES[$ticket->category] ?? $ticket->category }} · {{ $support::PRIORITIES[$ticket->priority] ?? $ticket->priority }} priority · {{ $ticket->school_name ?? 'Platform' }} · Raised by {{ $ticket->first_name }} {{ $ticket->last_name }}</p></div></header>

<div class="ws-grid">
    <section class="ws-card"><div class="ws-card-heading"><h2>Conversation</h2><span class="ws-status ws-status--{{ in_array($ticket->status, ['open', 'in_progress']) ? 'pending' : 'inactive' }}">{{ $support::STATUSES[$ticket->status] }}</span></div>
        @foreach($replies as $reply)
            <article class="ws-notice @if($reply->internal) ws-internal-note @endif"><span class="ws-meta">{{ $reply->first_name }} {{ $reply->last_name }} · {{ $reply->created_at }} UTC @if($reply->internal)· Internal note (not visible to the requester)@endif</span><p>{!! nl2br(e($reply->body)) !!}</p></article>
        @endforeach
        @if($ticket->status !== 'closed')
        <form method="post" action="{{ route('workspace.support-reply', $ticket->id) }}" class="ws-form ws-form--stack">@csrf
            <input type="hidden" name="context" value="{{ $context['id'] }}">
            <label>Reply<textarea name="body" maxlength="5000" rows="4" required></textarea></label>
            @if($handler)<label class="ws-check"><input type="checkbox" name="internal" value="1">Internal note (only support staff can see it)</label>@endif
            <div class="ws-form-actions"><button class="ws-btn" type="submit">Send</button></div>
        </form>
        @else<p class="ws-empty-line">This ticket is closed. Open a new ticket if you still need help.</p>@endif
    </section>
    <section class="ws-card"><h2>Ticket</h2>
        <dl class="ws-ticket-facts"><dt>Status</dt><dd>{{ $support::STATUSES[$ticket->status] }}</dd><dt>Assigned to</dt><dd>{{ $assignee ?? 'Not assigned' }}</dd><dt>Opened</dt><dd>{{ $ticket->created_at }} UTC</dd>@if($ticket->resolved_at)<dt>Resolved</dt><dd>{{ $ticket->resolved_at }} UTC</dd>@endif</dl>
        @if($handler)
            <form method="post" action="{{ route('workspace.support-status', $ticket->id) }}" class="ws-form ws-form--stack">@csrf
                <input type="hidden" name="context" value="{{ $context['id'] }}">
                <label>Change status<select name="status">@foreach($support::STATUSES as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select></label>
                <div class="ws-form-actions"><button class="ws-btn ws-btn-secondary" name="assign" value="me">Assign to me</button><button class="ws-btn" type="submit">Update</button></div>
            </form>
        @elseif($own && $ticket->status !== 'closed')
            <form method="post" action="{{ route('workspace.support-status', $ticket->id) }}">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn ws-btn-secondary" name="status" value="closed">Close this ticket</button></form>
        @endif
        <p><a href="{{ route('workspace.support', ['context' => $context['id']]) }}">← All tickets</a></p>
    </section>
</div>
@endsection
