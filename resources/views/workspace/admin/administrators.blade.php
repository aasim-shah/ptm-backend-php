@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Administrators'" title="Administrators" sub="School Admins and District Admins, the schools or districts they manage, and their status.">
    <button type="button" class="ws-btn" data-dialog-open="assign-admin"><i class="fa fa-plus" aria-hidden="true"></i>Assign administrator</button>
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.administrators')" :context="$context" placeholder="Search by name, email, school or district" :count="$admins->total()">
        <select name="role" aria-label="Role"><option value="">All administrator roles</option><option value="school_admin" @selected(request('role') === 'school_admin')>School Admins</option><option value="district_admin" @selected(request('role') === 'district_admin')>District Admins</option></select>
        <select name="status" aria-label="Status"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="revoked" @selected(request('status') === 'revoked')>Removed</option></select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Name</th><th>Role</th><th>Manages</th><th>Status</th><th>Since</th><th class="ap-actions ap-keep"></th></tr></thead>
        <tbody>
        @forelse($admins as $admin)
            <tr>
                <td><x-ap.person :first="$admin->first_name" :last="$admin->last_name" :email="$admin->email" :href="route('workspace.member', ['id' => $admin->user_id, 'context' => $context['id']])" /></td>
                <td><span class="ap-chip">{{ \App\Services\WorkspaceAccess::LABELS[$admin->role] }}</span></td>
                <td>{{ $admin->school_name ?? $admin->district_name ?? '—' }}</td>
                <td><x-ap.pill :status="$admin->status" :label="$admin->status === 'revoked' ? 'Removed' : null" /></td>
                <td class="ap-muted">{{ $formatTime($admin->created_at, 'j M Y') }}</td>
                <td class="ap-actions ap-keep">@if($admin->status === 'active' && ($platform || $admin->role === 'school_admin') && (int) $admin->user_id !== (int) auth()->id())<form method="post" action="{{ route('workspace.admin-revoke', $admin->id) }}" class="ap-inline" data-confirm="Remove {{ $admin->first_name }} {{ $admin->last_name }} as {{ \App\Services\WorkspaceAccess::LABELS[$admin->role] }}?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Remove</button></form>@endif</td>
            </tr>
        @empty
            <x-ap.empty colspan="6" icon="user-secret" title="No administrators match"><p>Assign a School Admin or District Admin to get started.</p></x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$admins" />
</section>

<x-ap.dialog id="assign-admin" title="Assign an administrator" sub="The person needs an active PTM account with a verified email.">
    <div data-tabs>
        @if($platform)<div class="ap-tabs" role="tablist"><button type="button" role="tab" data-tab="school" aria-selected="{{ old('_mode', 'school') === 'school' ? 'true' : 'false' }}">School Admin</button><button type="button" role="tab" data-tab="district" aria-selected="{{ old('_mode') === 'district' ? 'true' : 'false' }}">District Admin</button></div>@endif
        <form method="post" action="{{ route('workspace.school-admin') }}" class="ap-form" data-tab-panel="school" @if(old('_mode') === 'district') hidden @endif>@csrf
            <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="assign-admin"><input type="hidden" name="_mode" value="school">
            <label class="ap-span">School<select name="school_id" required><option value="">Choose a school</option>@foreach($schools as $id => $name)<option value="{{ $id }}" @selected((string) old('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select></label>
            <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" placeholder="name@example.com" value="{{ old('_mode', 'school') === 'school' ? old('email') : '' }}"></label>
            <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Assign School Admin</button></div>
        </form>
        @if($platform)
        <form method="post" action="{{ route('workspace.district-admin') }}" class="ap-form" data-tab-panel="district" @if(old('_mode') !== 'district') hidden @endif>@csrf
            <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="assign-admin"><input type="hidden" name="_mode" value="district">
            <label class="ap-span">District<select name="district_id" required><option value="">Choose a district</option>@foreach($districts as $d)<option value="{{ $d->id }}" @selected((string) old('district_id') === (string) $d->id)>{{ $d->name }}</option>@endforeach</select></label>
            <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" placeholder="name@example.com" value="{{ old('_mode') === 'district' ? old('email') : '' }}"></label>
            <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Assign District Admin</button></div>
        </form>
        @endif
    </div>
</x-ap.dialog>
@endsection
