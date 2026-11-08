@extends('workspace.layout')
@section('workspace-content')
@php
    $singular = ['parent' => 'parent or guardian', 'teacher' => 'teacher', 'staff' => 'staff member'][$role];
    $routeName = ['parent' => 'workspace.parents', 'teacher' => 'workspace.teachers', 'staff' => 'workspace.staff'][$role];
    $here = route($routeName);
    $statusOptions = ['active' => 'Active', 'revoked' => 'Access revoked', 'unverified' => 'Email not verified', 'disabled' => 'Account suspended'] + ($platform ? ['unconnected' => 'Not connected to a school'] : []);
    $scopeText = \App\Services\WorkspaceAccess::platformWide($context) ? 'across the platform' : 'in '.$context['organization'];
@endphp
<x-ap.head :eyebrow="$context['label'].' / People'" :title="$title" :sub="'Every '.$singular.' '.$scopeText.'. Search, filter and open a person to manage their access.'">
    @if($access->can($context, 'reports.export'))<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'people', 'role' => $role]) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>@endif
    <a class="ws-btn ap-btn-ghost" href="{{ route('workspace.imports', ['context' => $context['id']]) }}"><i class="fa fa-upload" aria-hidden="true"></i>Bulk import</a>
    @if($context['schools'])<button type="button" class="ws-btn" data-dialog-open="add-person"><i class="fa fa-plus" aria-hidden="true"></i>Add {{ $singular }}</button>@endif
</x-ap.head>

<x-ap.stats :stats="$stats" />

@if($role === 'parent' && $guardianRequests->count())
<section class="ap-panel">
    <header><div><h2>Guardian verification</h2><p>Check each request against school records before approving. Verified guardians see published student information.</p></div><x-ap.pill status="pending" :label="$guardianRequests->count().' waiting'" /></header>
    <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Parent / guardian</th><th>Student</th><th>School</th><th>Requested</th><th class="ap-actions ap-keep">Decision</th></tr></thead><tbody>
    @foreach($guardianRequests as $g)
        <tr><td><x-ap.person :first="$g->first_name" :last="$g->last_name" :email="$g->email" /></td><td>{{ $g->student_first }} {{ $g->student_last }}</td><td>{{ $g->school_name }}</td><td class="ap-muted">{{ $formatTime($g->created_at, 'j M Y') }}</td>
            <td class="ap-actions ap-keep"><form method="post" action="{{ route('workspace.guardian-status', $g->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn ws-btn-small" name="status" value="verified">Verify</button> <button class="ap-link-btn ap-link-btn--danger" name="status" value="rejected">Reject</button></form></td></tr>
    @endforeach
    </tbody></table></div>
</section>
@endif

<section class="ap-panel">
    <x-ap.toolbar :action="$here" :context="$context" placeholder="Search by name or email" :count="$people->total()">
        @if(count($context['schools']) > 1)<select name="school_id" aria-label="School"><option value="">All schools</option>@foreach($context['schools'] as $id => $name)<option value="{{ $id }}" @selected((string) request('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select>@endif
        <select name="status" aria-label="Status"><option value="">Any status</option>@foreach($statusOptions as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Name</th><th>School</th><th>Access</th>@if($role === 'parent')<th class="ap-num">Verified students</th>@elseif($role === 'teacher')<th class="ap-num">Class sections</th>@else<th>Delegated tools</th>@endif<th>Account</th><th>Joined</th><th class="ap-actions ap-keep"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @forelse($people as $person)
            @php $links = $memberships[$person->id] ?? collect(); $activeLink = $links->firstWhere('status', 'active'); @endphp
            <tr>
                <td><x-ap.person :first="$person->first_name" :last="$person->last_name" :email="$person->email" :href="route('workspace.member', ['id' => $person->id, 'context' => $context['id']])" /></td>
                <td><div class="ap-chips">@forelse($links as $link)<span class="ap-chip {{ $link->status !== 'active' ? 'ap-chip--muted' : '' }}">{{ $link->school_name }}</span>@empty<span class="ap-muted">Not connected</span>@endforelse</div></td>
                <td>@if($activeLink)<x-ap.pill status="active" />@elseif($links->count())<x-ap.pill status="revoked" label="Revoked" />@else<x-ap.pill status="unconnected" label="Awaiting school" />@endif</td>
                @if($role === 'staff')
                    <td><div class="ap-chips">@forelse(json_decode($activeLink->permissions ?? '[]', true) ?: [] as $permission)<span class="ap-chip">{{ \App\Services\WorkspaceAccess::PERMISSION_LABELS[$permission][0] ?? $permission }}</span>@empty<span class="ap-muted">Mobile app only</span>@endforelse</div></td>
                @else
                    <td class="ap-num">{{ number_format($extra[$person->id] ?? 0) }}</td>
                @endif
                <td>@if((int) $person->status !== 1)<x-ap.pill status="suspended" />@elseif(!$person->email_verified_at)<x-ap.pill status="unverified" label="Email unverified" />@else<x-ap.pill status="ok" label="Verified" />@endif</td>
                <td class="ap-muted">{{ $formatTime($person->created_at, 'j M Y') }}</td>
                <td class="ap-actions ap-keep"><a href="{{ route('workspace.member', ['id' => $person->id, 'context' => $context['id']]) }}">Manage →</a></td>
            </tr>
        @empty
            <x-ap.empty colspan="7" icon="users" :title="request()->except(['context','page']) ? 'No one matches these filters' : 'No '.strtolower($title).' yet'">
                <p>{{ request()->except(['context','page']) ? 'Try a different search or clear the filters.' : 'Add people one at a time, or upload a spreadsheet with Bulk import.' }}</p>
            </x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$people" />
</section>

@if($invitations->count())
<section class="ap-panel">
    <header><div><h2>Pending invitations</h2><p>Invitation links expire after {{ \App\Services\WorkspaceInvitations::LIFETIME_HOURS }} hours. Withdraw one to stop the link working.</p></div></header>
    <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Email</th><th>School</th><th>Sent</th><th>Expires</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
    @foreach($invitations as $invite)
        <tr><td>{{ $invite->email }}</td><td>{{ $invite->school_name }}</td><td class="ap-muted">{{ $formatTime($invite->created_at, 'j M Y') }}</td><td class="ap-muted">{{ $formatTime($invite->expires_at, 'j M · H:i') }}</td>
            <td class="ap-actions ap-keep"><form method="post" action="{{ route('workspace.invitation-revoke', $invite->id) }}" class="ap-inline" data-confirm="Withdraw the invitation for {{ $invite->email }}?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Withdraw</button></form></td></tr>
    @endforeach
    </tbody></table></div>
</section>
@endif

<x-ap.dialog id="add-person" :title="'Add a '.$singular" sub="Invite someone new by email, or connect a person who already has a PTM account.">
    <div data-tabs>
        <div class="ap-tabs" role="tablist"><button type="button" role="tab" data-tab="invite" aria-selected="{{ old('_mode', 'invite') === 'invite' ? 'true' : 'false' }}">Send an invitation</button><button type="button" role="tab" data-tab="connect" aria-selected="{{ old('_mode') === 'connect' ? 'true' : 'false' }}">Connect existing account</button></div>
        <form method="post" action="{{ route('workspace.invite') }}" class="ap-form" data-tab-panel="invite" @if(old('_mode') === 'connect') hidden @endif>
            @include('workspace.admin.school-field')
            <input type="hidden" name="role" value="{{ $role }}"><input type="hidden" name="_dialog" value="add-person"><input type="hidden" name="_mode" value="invite">
            <label class="ap-span">Email address<input type="email" name="email" required maxlength="191" placeholder="name@example.com" autocomplete="off" value="{{ old('_mode', 'invite') === 'invite' ? old('email') : '' }}"></label>
            @if($role === 'staff')<x-ap.perms :permissions="\App\Services\WorkspaceAccess::STAFF_PERMISSIONS" :checked="old('permissions', [])" legend="Delegated staff tools" />@endif
            <p class="ap-hint">They get a secure link to create their account or connect an existing one. Passwords are never emailed.</p>
            <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn" type="submit">Send invitation</button></div>
        </form>
        <form method="post" action="{{ route('workspace.memberships') }}" class="ap-form" data-tab-panel="connect" @if(old('_mode') !== 'connect') hidden @endif>
            @include('workspace.admin.school-field')
            <input type="hidden" name="role" value="{{ $role }}"><input type="hidden" name="_dialog" value="add-person"><input type="hidden" name="_mode" value="connect">
            <label class="ap-span">Account email<input type="email" name="email" required maxlength="191" placeholder="name@example.com" autocomplete="off" value="{{ old('_mode') === 'connect' ? old('email') : '' }}"></label>
            @if($role === 'staff')<x-ap.perms :permissions="\App\Services\WorkspaceAccess::STAFF_PERMISSIONS" :checked="old('permissions', [])" legend="Delegated staff tools" />@endif
            <p class="ap-hint">The account must be active with a verified email. Administrator roles are assigned from Administrators.</p>
            <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn" type="submit">Connect account</button></div>
        </form>
    </div>
</x-ap.dialog>
@endsection
