@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Districts'" title="Districts" :sub="$canCreate ? 'Group schools into districts or trusts, assign District Admins, and suspend or reactivate a district.' : 'Districts that include your schools.'">
    @if($access->can($context, 'reports.export'))<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'districts']) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>@endif
    @if($canCreate)<button type="button" class="ws-btn" data-dialog-open="add-district"><i class="fa fa-plus" aria-hidden="true"></i>Add district</button>@endif
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.districts')" :context="$context" placeholder="Search districts" :count="$districts->total()">
        <select name="market" aria-label="Market"><option value="">All markets</option><option value="US" @selected(request('market') === 'US')>United States</option><option value="UK" @selected(request('market') === 'UK')>United Kingdom</option></select>
        <select name="status" aria-label="Status"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>District</th><th>Market</th><th class="ap-num">Schools</th><th>District Admins</th><th>Status</th><th class="ap-actions ap-keep"></th></tr></thead>
        <tbody>
        @forelse($districts as $district)
            @php $href = route('workspace.district', ['id' => $district->id, 'context' => $context['id']]); @endphp
            <tr>
                <td><a href="{{ $href }}"><strong>{{ $district->name }}</strong></a>@if($district->timezone)<div class="ap-muted">{{ $district->timezone }}</div>@endif</td>
                <td><span class="ap-market ap-market--{{ $district->market }}">{{ $district->market }}</span></td>
                <td class="ap-num">{{ (int) ($schoolCounts[$district->id] ?? 0) }}</td>
                <td>@php $names = ($admins[$district->id] ?? collect())->map(fn ($a) => trim($a->first_name.' '.$a->last_name)); @endphp @if($names->count()){{ $names->implode(', ') }}@else<x-ap.pill status="warn" label="None assigned" />@endif</td>
                <td><x-ap.pill :status="$district->status ?? 'active'" /></td>
                <td class="ap-actions ap-keep"><a href="{{ $href }}">Open →</a></td>
            </tr>
        @empty
            <x-ap.empty colspan="6" icon="map-o" title="No districts yet"><p>{{ $canCreate ? 'Create a district to group schools and give a District Admin one view of them.' : 'None of your schools belong to a district.' }}</p></x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$districts" />
</section>

@if($canCreate)
<x-ap.dialog id="add-district" title="Add a district" sub="Use a district (US) or a trust or local authority (UK) to group schools.">
    <form method="post" action="{{ route('workspace.district-create') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="add-district">
        <label class="ap-span">Name<input name="name" required maxlength="191" value="{{ old('name') }}" placeholder="e.g. Springfield Public Schools"></label>
        <label>Market<select name="market" required>@foreach(\App\Http\Controllers\Workspace\AdministrationController::MARKETS as $code => $name)<option value="{{ $code }}" @selected(old('market') === $code)>{{ $name }}</option>@endforeach</select></label>
        <label>Time zone<select name="timezone"><option value="">Use the market default</option>@foreach(\DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::EUROPE) as $tz)<option value="{{ $tz }}" @selected(old('timezone') === $tz)>{{ $tz }}</option>@endforeach</select></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn" type="submit">Create district</button></div>
    </form>
</x-ap.dialog>
@endif
@endsection
