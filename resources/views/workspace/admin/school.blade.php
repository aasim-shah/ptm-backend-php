@extends('workspace.layout')
@section('workspace-content')
@php $status = $school->status ?? 'active'; $schoolAdminView = $context['role'] === 'school_admin' && count($context['schools']) === 1; @endphp
<x-ap.head :eyebrow="$context['label'].' / Schools'" :title="$school->school_name" :sub="collect([$school->address, $school->post_town, $school->state, $school->post_code])->filter()->implode(', ')"
    :back="$schoolAdminView ? null : route('workspace.schools', ['context' => $context['id']])" back-label="All schools">
    <x-ap.pill :status="$status" />
    @if($school->market)<span class="ap-market ap-market--{{ $school->market }}">{{ $school->market }}</span>@endif
    @if($platform)
        <form method="post" action="{{ route('workspace.select') }}" class="ap-inline">@csrf<input type="hidden" name="context" value="platform:s{{ $school->id }}"><button class="ws-btn ap-btn-ghost"><i class="fa fa-life-ring" aria-hidden="true"></i>Open support view</button></form>
    @endif
</x-ap.head>

<x-ap.stats :stats="$stats" />

<div class="ap-detail">
    <div>
        <section class="ap-panel" id="profile">
            <header><div><h2>School profile</h2><p>Shown to parents and staff in the app.</p></div></header>
            <div class="ap-panel-body">
            @if($canEdit)
                <form method="post" action="{{ route('workspace.school-profile') }}" class="ap-form">@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="school_id" value="{{ $school->id }}">
                    <label class="ap-span">School name<input name="school_name" value="{{ old('school_name', $school->school_name) }}" required maxlength="191"></label>
                    @if($platform)
                    <label>Market<select name="market" required>@foreach(\App\Http\Controllers\Workspace\AdministrationController::MARKETS as $code => $name)<option value="{{ $code }}" @selected(old('market', $school->market) === $code)>{{ $name }}</option>@endforeach</select></label>
                    <label>District<select name="district_id"><option value="">No district</option>@foreach($districts as $d)<option value="{{ $d->id }}" @selected((string) old('district_id', $school->district_id) === (string) $d->id)>{{ $d->name }} · {{ $d->market }}</option>@endforeach</select></label>
                    @endif
                    <label class="ap-span">Street address<input name="address" value="{{ old('address', $school->address) }}" required maxlength="191"></label>
                    <label>Town or city<input name="post_town" value="{{ old('post_town', $school->post_town) }}" maxlength="191"></label>
                    <label>State / county<input name="state" value="{{ old('state', $school->state) }}" maxlength="191"></label>
                    <label>ZIP code / postcode<input name="post_code" value="{{ old('post_code', $school->post_code) }}" required maxlength="32"></label>
                    <label>Time zone<select name="timezone"><option value="">Use the market default</option>@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $school->timezone) === $tz)>{{ $tz }}</option>@endforeach</select></label>
                    <label>School email<input type="email" name="email" value="{{ old('email', $school->email) }}" maxlength="191"></label>
                    <label>Phone<input name="phone" value="{{ old('phone', $school->phone) }}" maxlength="40"></label>
                    <label class="ap-span">Website<input type="url" name="website" value="{{ old('website', $school->website) }}" maxlength="191" placeholder="https://"></label>
                    <div class="ap-form-actions"><button class="ws-btn" type="submit">Save profile</button></div>
                </form>
            @else
                <dl class="ap-facts"><dt>Email</dt><dd>{{ $school->email ?: '—' }}</dd><dt>Phone</dt><dd>{{ $school->phone ?: '—' }}</dd><dt>Website</dt><dd>{{ $school->website ?: '—' }}</dd><dt>Time zone</dt><dd>{{ $school->timezone ?: 'Market default' }}</dd></dl>
            @endif
            </div>
        </section>

        <section class="ap-panel">
            <header><div><h2>School Admins</h2><p>School Admins manage people, classes and communications for this school.</p></div>@if($canAssign)<button type="button" class="ws-btn ws-btn-small" data-dialog-open="assign-admin"><i class="fa fa-plus" aria-hidden="true"></i>Assign School Admin</button>@endif</header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Name</th><th>Status</th><th>Since</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @forelse($admins as $admin)
                <tr><td><x-ap.person :first="$admin->first_name" :last="$admin->last_name" :email="$admin->email" :href="$access->can($context, 'memberships.manage') ? route('workspace.member', ['id' => $admin->user_id, 'context' => $context['id']]) : null" /></td>
                    <td><x-ap.pill :status="$admin->status" /></td><td class="ap-muted">{{ $formatTime($admin->created_at, 'j M Y') }}</td>
                    <td class="ap-actions ap-keep">@if($canAssign && $admin->status === 'active' && (int) $admin->user_id !== (int) auth()->id())<form method="post" action="{{ route('workspace.admin-revoke', $admin->id) }}" class="ap-inline" data-confirm="Remove {{ $admin->first_name }} as School Admin?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Remove</button></form>@endif</td></tr>
            @empty
                <x-ap.empty colspan="4" icon="user-secret" title="No School Admin yet"><p>Assign one so the school can manage its own people and classes.</p></x-ap.empty>
            @endforelse
            </tbody></table></div>
        </section>

        <section class="ap-panel">
            <header><div><h2>Recent activity</h2></div>@if($access->can($context, 'audit.view'))<a href="{{ route('workspace.audit', ['context' => $context['id'], 'school_id' => $school->id]) }}">Full log →</a>@endif</header>
            <div class="ap-panel-body">@include('workspace.admin.activity', ['entries' => $activity])</div>
        </section>
    </div>
    <div>
        <section class="ap-panel">
            <header><h2>Details</h2></header>
            <div class="ap-panel-body"><dl class="ap-facts">
                <dt>School ID</dt><dd>#{{ $school->id }}</dd>
                <dt>District</dt><dd>@if($school->district_id && in_array($context['role'], ['super_admin', 'sub_super_admin', 'district_admin'], true))<a href="{{ route('workspace.district', ['id' => $school->district_id, 'context' => $context['id']]) }}">{{ $school->district_name }}</a>@else{{ $school->district_name ?? '—' }}@endif</dd>
                <dt>Market</dt><dd>{{ \App\Http\Controllers\Workspace\AdministrationController::MARKETS[$school->market] ?? 'Not set' }}</dd>
                <dt>Created</dt><dd>{{ $formatTime($school->created_at, 'j M Y') }}</dd>
            </dl></div>
        </section>
        <section class="ap-panel">
            <header><h2>Quick links</h2></header>
            <div class="ap-panel-body"><ul class="ap-list">
                @if($access->can($context, 'memberships.manage'))
                <li><a href="{{ route('workspace.parents', ['context' => $context['id'], 'school_id' => $school->id]) }}">Parents / Guardians</a><i class="fa fa-angle-right" aria-hidden="true"></i></li>
                <li><a href="{{ route('workspace.teachers', ['context' => $context['id'], 'school_id' => $school->id]) }}">Teachers</a><i class="fa fa-angle-right" aria-hidden="true"></i></li>
                <li><a href="{{ route('workspace.staff', ['context' => $context['id'], 'school_id' => $school->id]) }}">Staff</a><i class="fa fa-angle-right" aria-hidden="true"></i></li>
                @endif
                @if($access->can($context, 'students.view'))<li><a href="{{ route('workspace.students', ['context' => $context['id'], 'school_id' => $school->id]) }}">Students</a><i class="fa fa-angle-right" aria-hidden="true"></i></li>@endif
            </ul></div>
        </section>
        @if($platform)
        <section class="ap-panel ap-danger-zone">
            <header><div><h2>{{ $status === 'suspended' ? 'Reactivate school' : 'Suspend school' }}</h2><p>{{ $status === 'suspended' ? 'Members regain access to this school straight away.' : 'Everyone connected only through this school loses access until it is reactivated. Data is kept.' }}</p></div></header>
            <div class="ap-panel-body">
                <form method="post" action="{{ route('workspace.school-status', $school->id) }}" class="ap-form" @if($status !== 'suspended') data-confirm="Suspend {{ $school->school_name }}?" @endif>@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}">
                    @if($status === 'suspended')
                        <input type="hidden" name="status" value="active"><div class="ap-form-actions"><button class="ws-btn">Reactivate school</button></div>
                    @else
                        <input type="hidden" name="status" value="suspended">
                        <label class="ap-span">Reason (kept in the activity log)<input name="reason" required maxlength="300" placeholder="e.g. Contract ended"></label>
                        <div class="ap-form-actions"><button class="ws-btn ap-btn-danger">Suspend school</button></div>
                    @endif
                </form>
            </div>
        </section>
        @endif
    </div>
</div>

@if($canAssign)
<x-ap.dialog id="assign-admin" title="Assign a School Admin" :sub="'For '.$school->school_name.'. The person needs an active PTM account with a verified email.'">
    <form method="post" action="{{ route('workspace.school-admin') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="school_id" value="{{ $school->id }}"><input type="hidden" name="_dialog" value="assign-admin">
        <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" value="{{ old('email') }}" placeholder="name@example.com"></label>
        <p class="ap-hint">No account yet? Ask them to install the app and sign up with this email, then assign them here.</p>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn" type="submit">Assign School Admin</button></div>
    </form>
</x-ap.dialog>
@endif
@endsection
