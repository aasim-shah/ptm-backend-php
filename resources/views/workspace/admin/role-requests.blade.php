@extends('workspace.layout')
@section('workspace-content')
@php $actions = ['approve' => [['pending', 'rejected'], 'Approve', ''], 'reject' => [['pending'], 'Reject', 'ap-link-btn--danger'], 'suspend' => [['active'], 'Suspend', 'ap-link-btn--danger'], 'reactivate' => [['suspended'], 'Reactivate', '']]; @endphp
<x-ap.head :eyebrow="$context['label'].' / People'" title="Role requests" sub="People who asked in the mobile app to work as staff or an administrator at a school. Approve only after checking with the school.">
</x-ap.head>
@if($errors->has('message'))<div class="ws-alert ws-error" role="alert">{{ $errors->first('message') }}</div>@endif

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.role-requests')" :context="$context" :search="false" :count="$requests->total()">
        <select name="status" aria-label="Status">@foreach(['pending' => 'Waiting for a decision', 'active' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'ended' => 'Ended', 'all' => 'All requests'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach</select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Person</th><th>Requested role</th><th>Organization</th><th>Status</th><th>Requested</th><th class="ap-actions ap-keep">Decision</th></tr></thead>
        <tbody>
        @forelse($requests as $r)
            <tr>
                <td><x-ap.person :first="$r->user?->first_name" :last="$r->user?->last_name" :email="$r->user?->email" /></td>
                <td>{{ ucfirst(str_replace('_', ' ', $r->role)) }}</td>
                <td>{{ $r->organization_summary['name'] ?? '—' }}<div class="ap-muted">{{ ucfirst($r->organization_type) }}</div></td>
                <td><x-ap.pill :status="$r->status === 'ended' ? 'none' : $r->status" /></td>
                <td class="ap-muted">{{ optional($r->requested_at)->format('j M Y · H:i') }}</td>
                <td class="ap-actions ap-keep">
                    @foreach($actions as $action => [$from, $label, $class])
                        @if(in_array($r->status, $from, true))<form method="post" action="{{ route('role-requests.action', [$r->id, $action]) }}" class="ap-inline" @if($action !== 'approve') data-confirm="{{ $label }} this request?" @endif>@csrf<button class="{{ $action === 'approve' ? 'ws-btn ws-btn-small' : 'ap-link-btn '.$class }}">{{ $label }}</button></form> @endif
                    @endforeach
                    @if($r->status === 'active')<button type="button" class="ap-link-btn" data-dialog-open="perms-{{ $r->id }}">Permissions</button> · <form method="post" action="{{ route('role-requests.action', [$r->id, 'end']) }}" class="ap-inline" data-confirm="End this access?">@csrf<button class="ap-link-btn ap-link-btn--danger">End access</button></form>@endif
                </td>
            </tr>
        @empty
            <x-ap.empty colspan="6" icon="inbox" title="No requests here" />
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$requests" />
</section>

@foreach($requests->where('status', 'active') as $r)
<x-ap.dialog :id="'perms-'.$r->id" :title="'Permissions for '.trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? ''))" :sub="ucfirst(str_replace('_', ' ', $r->role)).' · '.($r->organization_summary['name'] ?? '')">
    <form method="post" action="{{ route('role-requests.permissions', $r->id) }}" class="ap-form">@csrf
        <div class="ap-perm-grid ap-span">
        @foreach($permissions as $permission)
            <label class="ap-perm"><input type="checkbox" name="permissions[{{ $permission->key }}]" value="1" @checked(in_array($permission->key, $r->effective_permissions, true))><span><strong>{{ ucfirst(str_replace(['.', '_'], ' ', $permission->key)) }}</strong>@if(!empty($permission->description))<span>{{ $permission->description }}</span>@endif</span></label>
        @endforeach
        </div>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn">Save permissions</button></div>
    </form>
</x-ap.dialog>
@endforeach
@endsection
