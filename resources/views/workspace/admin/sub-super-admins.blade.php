@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Sub Super Admins'" title="Sub Super Admins" sub="People you trust to run part of the platform. Each delegation covers one school or one district and only the actions you allow.">
    <button type="button" class="ws-btn" data-dialog-open="add-delegation"><i class="fa fa-plus" aria-hidden="true"></i>Add Sub Super Admin</button>
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.sub-super-admins')" :context="$context" placeholder="Search by name or email" :count="$delegations->total()">
        <select name="status" aria-label="Status"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="revoked" @selected(request('status') === 'revoked')>Revoked</option></select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Name</th><th>Scope</th><th>Allowed actions</th><th>Status</th><th class="ap-actions ap-keep"></th></tr></thead>
        <tbody>
        @forelse($delegations as $d)
            @php $perms = json_decode($d->permissions ?? '[]', true) ?: []; @endphp
            <tr>
                <td><x-ap.person :first="$d->first_name" :last="$d->last_name" :email="$d->email" :href="route('workspace.member', ['id' => $d->user_id, 'context' => $context['id']])" /></td>
                <td><span class="ap-muted">{{ $d->school_id ? 'School' : 'District' }}</span><div>{{ $d->school_name ?? $d->district_name }}</div></td>
                <td><div class="ap-chips">@forelse($perms as $p)<span class="ap-chip">{{ \App\Services\WorkspaceAccess::PERMISSION_LABELS[$p][0] ?? $p }}</span>@empty<span class="ap-muted">None</span>@endforelse</div></td>
                <td><x-ap.pill :status="$d->status" /></td>
                <td class="ap-actions ap-keep">@if($d->status === 'active')
                    <button type="button" class="ap-link-btn" data-dialog-open="edit-delegation-{{ $d->id }}">Edit</button> ·
                    <form method="post" action="{{ route('workspace.delegation-revoke', $d->id) }}" class="ap-inline" data-confirm="Revoke {{ $d->first_name }}'s Sub Super Admin access?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Revoke</button></form>
                @endif</td>
            </tr>
        @empty
            <x-ap.empty colspan="5" icon="sitemap" title="No Sub Super Admins yet"><p>Delegate a school or district to someone you trust, with only the actions they need.</p></x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$delegations" />
</section>

@foreach($delegations->where('status', 'active') as $d)
<x-ap.dialog :id="'edit-delegation-'.$d->id" :title="'Allowed actions for '.trim($d->first_name.' '.$d->last_name)" :sub="'Scope: '.($d->school_name ?? $d->district_name)">
    <form method="post" action="{{ route('workspace.delegation-update', $d->id) }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="edit-delegation-{{ $d->id }}">
        <x-ap.perms :permissions="\App\Services\WorkspaceAccess::DELEGATED_PERMISSIONS" :checked="json_decode($d->permissions ?? '[]', true) ?: []" />
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Save actions</button></div>
    </form>
</x-ap.dialog>
@endforeach

<x-ap.dialog id="add-delegation" title="Add a Sub Super Admin" sub="The person needs an active PTM account with a verified email. You can change or revoke this at any time.">
    @php $scope = old('scope', 'school'); @endphp
    <form method="post" action="{{ route('workspace.delegate') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="add-delegation">
        <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" placeholder="name@example.com" value="{{ old('email') }}"></label>
        <div class="ap-span"><p style="margin:0 0 8px;font-size:12px;font-weight:650;color:#415574">Scope</p>
            <div class="ap-segment" role="radiogroup" aria-label="Scope">
                <label><input type="radio" name="scope" value="school" @checked($scope === 'school') onchange="this.form.querySelector('[data-scope=school]').hidden=false;this.form.querySelector('[data-scope=district]').hidden=true"><i class="fa fa-university" aria-hidden="true"></i>One school</label>
                <label><input type="radio" name="scope" value="district" @checked($scope === 'district') onchange="this.form.querySelector('[data-scope=school]').hidden=true;this.form.querySelector('[data-scope=district]').hidden=false"><i class="fa fa-map-o" aria-hidden="true"></i>One district</label>
            </div>
        </div>
        <label class="ap-span" data-scope="school" @if($scope !== 'school') hidden @endif>School<select name="school_id"><option value="">Choose a school</option>@foreach($schools as $id => $name)<option value="{{ $id }}" @selected((string) old('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select></label>
        <label class="ap-span" data-scope="district" @if($scope !== 'district') hidden @endif>District<select name="district_id"><option value="">Choose a district</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected((string) old('district_id') === (string) $district->id)>{{ $district->name }}</option>@endforeach</select></label>
        <x-ap.perms :permissions="\App\Services\WorkspaceAccess::DELEGATED_PERMISSIONS" :checked="old('permissions', \App\Services\WorkspaceAccess::DEFAULT_DELEGATED_PERMISSIONS)" />
        <p class="ap-hint">Sub Super Admins can never create schools, manage billing, change platform settings or delegate access to others.</p>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Save delegation</button></div>
    </form>
</x-ap.dialog>
@endsection
