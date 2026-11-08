@extends('workspace.layout')
@section('workspace-content')
@php
    $name = trim($person->first_name.' '.$person->last_name) ?: $person->email;
    $back = ['parents' => 'workspace.parents', 'teachers' => 'workspace.teachers', 'staff' => 'workspace.staff'][$page] ?? 'workspace.administrators';
    $platform = \App\Services\WorkspaceAccess::platformWide($context);
@endphp
<x-ap.head :eyebrow="$context['label'].' / People'" :title="$name" :sub="$person->email" :back="route($back, ['context' => $context['id']])" back-label="Back to list">
    @if((int) $person->status === 1 && $person->email)
    <form method="post" action="{{ route('workspace.member-reset', $person->id) }}" class="ap-inline" data-confirm="Email a password reset link to {{ $person->email }}?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-btn ap-btn-ghost"><i class="fa fa-key" aria-hidden="true"></i>Send password reset</button></form>
    @endif
</x-ap.head>

<div class="ap-detail">
    <div>
        <section class="ap-panel">
            <header><div><h2>School access</h2><p>Each row is one role in one school. Revoking access keeps the history.</p></div></header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Role</th><th>Organization</th><th>Status</th><th>Since</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @forelse($memberships as $m)
                <tr><td><strong>{{ \App\Services\WorkspaceAccess::LABELS[$m->role] ?? $m->role }}</strong>
                        @if($m->role === 'staff' || $m->role === 'sub_super_admin')<div class="ap-chips" style="margin-top:5px">@foreach(json_decode($m->permissions ?? '[]', true) ?: [] as $permission)<span class="ap-chip">{{ \App\Services\WorkspaceAccess::PERMISSION_LABELS[$permission][0] ?? $permission }}</span>@endforeach</div>@endif</td>
                    <td>{{ $m->school_name ?? $m->district_name ?? '—' }}</td>
                    <td><x-ap.pill :status="$m->status" /></td>
                    <td class="ap-muted">{{ $formatTime($m->starts_at ?? $m->created_at, 'j M Y') }}</td>
                    <td class="ap-actions ap-keep">
                        @if(in_array($m->role, ['parent', 'teacher', 'staff'], true))
                            @if($m->status === 'active')
                                <form method="post" action="{{ route('workspace.revoke', $m->id) }}" class="ap-inline" data-confirm="Revoke {{ $name }}'s {{ strtolower(\App\Services\WorkspaceAccess::LABELS[$m->role]) }} access to {{ $m->school_name }}?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Revoke</button></form>
                            @else
                                <form method="post" action="{{ route('workspace.restore', $m->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn">Restore</button></form>
                            @endif
                        @elseif(in_array($m->role, ['school_admin', 'district_admin'], true) && $m->status === 'active' && ($platform || $m->role === 'school_admin'))
                            <form method="post" action="{{ route('workspace.admin-revoke', $m->id) }}" class="ap-inline" data-confirm="Remove this administrator access?">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ap-link-btn ap-link-btn--danger">Remove</button></form>
                        @endif
                    </td></tr>
            @empty
                <x-ap.empty colspan="5" icon="link" title="Not connected to a school yet">
                    <p>This account registered in the app but no school has approved it. Connect it from Parents, Teachers or Staff with “Add”.</p>
                </x-ap.empty>
            @endforelse
            </tbody></table></div>
        </section>

        @if($children->count())
        <section class="ap-panel">
            <header><div><h2>Students</h2><p>Guardian relationships for this person.</p></div></header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Student</th><th>School</th><th>Relationship</th><th class="ap-actions ap-keep"></th></tr></thead><tbody>
            @foreach($children as $child)
                <tr><td>{{ $child->first_name }} {{ $child->last_name }}</td><td>{{ $child->school_name }}</td><td><x-ap.pill :status="$child->status" /></td>
                    <td class="ap-actions ap-keep"><form method="post" action="{{ route('workspace.guardian-status', $child->id) }}" class="ap-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}">
                        @if($child->status === 'pending')<button class="ws-btn ws-btn-small" name="status" value="verified">Verify</button> <button class="ap-link-btn ap-link-btn--danger" name="status" value="rejected">Reject</button>
                        @elseif($child->status === 'verified')<button class="ap-link-btn ap-link-btn--danger" name="status" value="revoked">Revoke</button>@endif
                    </form></td></tr>
            @endforeach
            </tbody></table></div>
        </section>
        @endif

        @if($classes->count())
        <section class="ap-panel">
            <header><div><h2>Classes taught</h2><p>Class sections where this person is the class teacher.</p></div></header>
            <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Class</th><th>Section</th><th>School</th><th class="ap-num">Students</th></tr></thead><tbody>
            @foreach($classes as $class)<tr><td>{{ $class->class_name }}</td><td>{{ $class->section_name }}</td><td>{{ $class->school_name }}</td><td class="ap-num">{{ $class->students }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
        @endif

        <section class="ap-panel">
            <header><div><h2>Recent activity</h2><p>Actions by or about this person.</p></div></header>
            <div class="ap-panel-body">
            @include('workspace.admin.activity', ['entries' => $activity])
            </div>
        </section>
    </div>

    <div>
        <section class="ap-panel">
            <header><h2>Account</h2>@if((int) $person->status !== 1)<x-ap.pill status="suspended" />@else<x-ap.pill status="active" />@endif</header>
            <div class="ap-panel-body">
                <dl class="ap-facts">
                    <dt>Email</dt><dd>{{ $person->email ?? '—' }}</dd>
                    <dt>Email status</dt><dd>{{ $person->email_verified_at ? 'Verified '.$formatTime($person->email_verified_at, 'j M Y') : 'Not verified' }}</dd>
                    <dt>Mobile</dt><dd>{{ $person->mobile ?: '—' }}</dd>
                    <dt>Account roles</dt><dd>{{ $roles->implode(', ') ?: '—' }}</dd>
                    <dt>Joined</dt><dd>{{ $formatTime($person->created_at, 'j M Y') }}</dd>
                    <dt>Account ID</dt><dd>#{{ $person->id }}</dd>
                </dl>
            </div>
        </section>
        @if($platform && !$isSuperAdmin && (int) $person->id !== (int) auth()->id())
        <section class="ap-panel ap-danger-zone">
            <header><div><h2>{{ (int) $person->status === 1 ? 'Suspend account' : 'Reactivate account' }}</h2><p>{{ (int) $person->status === 1 ? 'Signs the person out on every device and blocks the app and web panel until reactivated.' : 'The person can sign in again. School access returns as it was.' }}</p></div></header>
            <div class="ap-panel-body">
                <form method="post" action="{{ route('workspace.member-account', $person->id) }}" class="ap-form" @if((int) $person->status === 1) data-confirm="Suspend {{ $name }}? They will be signed out everywhere." @endif>@csrf
                    <input type="hidden" name="context" value="{{ $context['id'] }}">
                    @if((int) $person->status === 1)
                        <input type="hidden" name="status" value="suspend">
                        <label class="ap-span">Reason (kept in the activity log)<input name="reason" required maxlength="300" placeholder="e.g. Safeguarding review requested by the school"></label>
                        <div class="ap-form-actions"><button class="ws-btn ap-btn-danger">Suspend account</button></div>
                    @else
                        <input type="hidden" name="status" value="reactivate">
                        <div class="ap-form-actions"><button class="ws-btn">Reactivate account</button></div>
                    @endif
                </form>
            </div>
        </section>
        @endif
    </div>
</div>
@endsection
