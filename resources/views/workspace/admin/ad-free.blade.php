@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Monetization'" title="Ad-free requests" :sub="$canReview ? 'Schools and districts can ask for the app to show no ads to their community. Approve or reject each request.' : 'Ask the platform to turn off ads for your school community.'">
    @if($canRequest)<button type="button" class="ws-btn" data-dialog-open="request-ad-free"><i class="fa fa-plus" aria-hidden="true"></i>New request</button>@endif
    @if($canReview)<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.integrations', ['context' => $context['id']]) }}#ads"><i class="fa fa-sliders" aria-hidden="true"></i>Ad settings</a>@endif
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.ad-free-requests')" :context="$context" :search="false" :count="$requests->total()">
        <select name="status" aria-label="Status"><option value="">Any status</option>@foreach(['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Organization</th><th>Reason</th><th>Requested by</th><th>Date</th><th>Status</th>@if($canReview)<th class="ap-actions ap-keep"></th>@endif</tr></thead>
        <tbody>
        @forelse($requests as $r)
            <tr><td><strong>{{ $r->school_name ?? $r->district_name }}</strong><div class="ap-muted">{{ $r->school_id ? 'School' : 'Whole district' }}</div></td>
                <td style="max-width:360px">{{ \Illuminate\Support\Str::limit($r->reason, 220) }}</td>
                <td>{{ trim($r->first_name.' '.$r->last_name) }}</td><td class="ap-muted">{{ $formatTime($r->created_at, 'j M Y') }}</td>
                <td><x-ap.pill :status="$r->status" /></td>
                @if($canReview)<td class="ap-actions ap-keep">@if($r->status === 'pending')<form method="post" action="{{ route('workspace.ad-free-review', $r->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn ws-btn-small" name="status" value="approved">Approve</button> <button class="ap-link-btn ap-link-btn--danger" name="status" value="rejected">Reject</button></form>@endif</td>@endif</tr>
        @empty
            <x-ap.empty :colspan="$canReview ? 6 : 5" icon="ban" title="No ad-free requests" />
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$requests" />
</section>

@if($canRequest)
<x-ap.dialog id="request-ad-free" title="Request ad-free status" sub="The Super Admin reviews every request.">
    <form method="post" action="{{ route('workspace.ad-free') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="request-ad-free">
        <label class="ap-span">Organization<select name="school_id">@if($context['role'] === 'district_admin')<option value="">Entire district</option>@endif @foreach($schools as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label class="ap-span">Reason<textarea name="reason" required maxlength="2000" placeholder="e.g. Our district has an institutional licence">{{ old('reason') }}</textarea></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Submit request</button></div>
    </form>
</x-ap.dialog>
@endif
@endsection
