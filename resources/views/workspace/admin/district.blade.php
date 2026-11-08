@extends('workspace.layout')
@section('workspace-content')
@php $status = $district->status ?? 'active'; @endphp
<x-ap.head :eyebrow="$context['label'].' / Districts'" :title="$district->name" :sub="\App\Http\Controllers\Workspace\AdministrationController::MARKETS[$district->market] ?? $district->market" :back="route('workspace.districts', ['context' => $context['id']])" back-label="All districts">
    <x-ap.pill :status="$status" />
    @if($platform)<form method="post" action="{{ route('workspace.select') }}" class="ap-inline">@csrf<input type="hidden" name="context" value="platform:d{{ $district->id }}"><button class="ws-btn ap-btn-ghost"><i class="fa fa-life-ring" aria-hidden="true"></i>Open support view</button></form>@endif
</x-ap.head>

<div class="ap-detail">
    <div>
        <section class="ap-panel">
            <header><div><h2>Schools in this district</h2><p>{{ $schools->count() }} {{ \Illuminate\Support\Str::plural('school', $schools->count()) }}</p></div>@if($platform && $available->count())<button type="button" class="ws-btn ws-btn-small" data-dialog-open="add-school-to-district"><i class="fa fa-plus" aria-hidden="true"></i>Add school</button>@endif</header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>School</th><th>Postcode / ZIP</th><th>Status</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @forelse($schools as $school)
                <tr><td><a href="{{ route('workspace.school', ['id' => $school->id, 'context' => $context['id']]) }}"><strong>{{ $school->school_name }}</strong></a></td><td class="ap-muted">{{ $school->post_code }}</td><td><x-ap.pill :status="$school->status ?? 'active'" /></td>
                    <td class="ap-actions ap-keep">@if($platform)<form method="post" action="{{ route('workspace.district-school', $district->id) }}" class="ap-inline" data-confirm="Remove {{ $school->school_name }} from {{ $district->name }}?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="school_id" value="{{ $school->id }}"><button class="ap-link-btn ap-link-btn--danger" name="action" value="remove">Remove</button></form>@endif</td></tr>
            @empty
                <x-ap.empty colspan="4" icon="university" title="No schools in this district yet" />
            @endforelse
            </tbody></table></div>
        </section>

        <section class="ap-panel">
            <header><div><h2>District Admins</h2><p>District Admins see every school in this district.</p></div>@if($platform)<button type="button" class="ws-btn ws-btn-small" data-dialog-open="assign-district-admin"><i class="fa fa-plus" aria-hidden="true"></i>Assign District Admin</button>@endif</header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Name</th><th>Status</th><th>Since</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @forelse($admins as $admin)
                <tr><td><x-ap.person :first="$admin->first_name" :last="$admin->last_name" :email="$admin->email" /></td><td><x-ap.pill :status="$admin->status" /></td><td class="ap-muted">{{ $formatTime($admin->created_at, 'j M Y') }}</td>
                    <td class="ap-actions ap-keep">@if($platform && $admin->status === 'active')<form method="post" action="{{ route('workspace.admin-revoke', $admin->id) }}" class="ap-inline" data-confirm="Remove {{ $admin->first_name }} as District Admin?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Remove</button></form>@endif</td></tr>
            @empty
                <x-ap.empty colspan="4" icon="user-secret" title="No District Admin yet" />
            @endforelse
            </tbody></table></div>
        </section>

        <section class="ap-panel">
            <header><h2>Recent activity</h2></header>
            <div class="ap-panel-body">@include('workspace.admin.activity', ['entries' => $activity])</div>
        </section>
    </div>
    <div>
        @if($platform)
        <section class="ap-panel">
            <header><h2>District details</h2></header>
            <div class="ap-panel-body">
                <form method="post" action="{{ route('workspace.district-update', $district->id) }}" class="ap-form">@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}">
                    <label class="ap-span">Name<input name="name" required maxlength="191" value="{{ old('name', $district->name) }}"></label>
                    <label class="ap-span">Market<select name="market" required>@foreach(\App\Http\Controllers\Workspace\AdministrationController::MARKETS as $code => $name)<option value="{{ $code }}" @selected(old('market', $district->market) === $code)>{{ $name }}</option>@endforeach</select></label>
                    <label class="ap-span">Time zone<select name="timezone"><option value="">Use the market default</option>@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $district->timezone) === $tz)>{{ $tz }}</option>@endforeach</select></label>
                    <div class="ap-form-actions"><button class="ws-btn">Save</button></div>
                </form>
            </div>
        </section>
        <section class="ap-panel ap-danger-zone">
            <header><div><h2>{{ $status === 'suspended' ? 'Reactivate district' : 'Suspend district' }}</h2><p>{{ $status === 'suspended' ? 'District Admins regain access.' : 'District Admins lose access. Each school keeps its own status and School Admins keep working.' }}</p></div></header>
            <div class="ap-panel-body">
                <form method="post" action="{{ route('workspace.district-status', $district->id) }}" class="ap-form" @if($status !== 'suspended') data-confirm="Suspend {{ $district->name }}?" @endif>@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}">
                    @if($status === 'suspended')<input type="hidden" name="status" value="active"><div class="ap-form-actions"><button class="ws-btn">Reactivate district</button></div>
                    @else<input type="hidden" name="status" value="suspended"><label class="ap-span">Reason (kept in the activity log)<input name="reason" required maxlength="300"></label><div class="ap-form-actions"><button class="ws-btn ap-btn-danger">Suspend district</button></div>@endif
                </form>
            </div>
        </section>
        @else
        <section class="ap-panel"><header><h2>Details</h2></header><div class="ap-panel-body"><dl class="ap-facts"><dt>Market</dt><dd>{{ $district->market }}</dd><dt>Time zone</dt><dd>{{ $district->timezone ?: 'Market default' }}</dd></dl></div></section>
        @endif
    </div>
</div>

@if($platform)
<x-ap.dialog id="add-school-to-district" :title="'Add a school to '.$district->name" sub="Only schools that are not in another district are listed.">
    <form method="post" action="{{ route('workspace.district-school', $district->id) }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="action" value="add"><input type="hidden" name="_dialog" value="add-school-to-district">
        <label class="ap-span">School<select name="school_id" required><option value="">Choose a school</option>@foreach($available as $s)<option value="{{ $s->id }}">{{ $s->school_name }}</option>@endforeach</select></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Add school</button></div>
    </form>
</x-ap.dialog>
<x-ap.dialog id="assign-district-admin" title="Assign a District Admin" :sub="'For '.$district->name.'. The person needs an active PTM account with a verified email.'">
    <form method="post" action="{{ route('workspace.district-admin') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="district_id" value="{{ $district->id }}"><input type="hidden" name="_dialog" value="assign-district-admin">
        <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" value="{{ old('email') }}" placeholder="name@example.com"></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Assign District Admin</button></div>
    </form>
</x-ap.dialog>
@endif
@endsection
