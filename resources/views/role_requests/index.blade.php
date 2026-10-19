@extends('layouts.master')

@section('title')
    Role Requests
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Role Requests</h3>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        @foreach (['pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                            <a href="{{ route('role-requests.index', ['status' => $key]) }}"
                               class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                        @endforeach
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Organization</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($contexts as $context)
                                <tr>
                                    <td>
                                        {{ optional($context->user)->first_name }} {{ optional($context->user)->last_name }}<br>
                                        <small>{{ optional($context->user)->email }}</small>
                                        @if (optional($context->user)->title)
                                            <br><small class="text-muted">{{ $context->user->title }}</small>
                                        @endif
                                    </td>
                                    <td>{{ str_replace('_', ' ', ucfirst($context->role)) }}</td>
                                    <td>
                                        {{ $context->organization_summary['name'] ?? '—' }}<br>
                                        <small class="text-muted">{{ $context->organization_type }}</small>
                                    </td>
                                    <td>{{ ucfirst($context->status) }}</td>
                                    <td>{{ optional($context->requested_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if ($context->status === 'active')
                                            <form method="POST" action="{{ route('role-requests.permissions', $context->id) }}">
                                                @csrf
                                                @foreach ($permissions as $permission)
                                                    <div class="form-check">
                                                        <label class="form-check-label" title="{{ $permission->description }}">
                                                            <input type="checkbox" class="form-check-input"
                                                                   name="permissions[{{ $permission->key }}]" value="1"
                                                                   {{ in_array($permission->key, $context->effective_permissions, true) ? 'checked' : '' }}>
                                                            {{ $permission->key }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                                <button type="submit" class="btn btn-sm btn-outline-secondary mt-1">Save permissions</button>
                                            </form>
                                        @else
                                            <small class="text-muted">None until active</small>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach ([
                                            'approve' => ['pending', 'rejected'],
                                            'reject' => ['pending'],
                                            'suspend' => ['active'],
                                            'reactivate' => ['suspended'],
                                            'end' => ['pending', 'active', 'suspended', 'rejected'],
                                        ] as $action => $from)
                                            @if (in_array($context->status, $from, true))
                                                <form method="POST" class="d-inline"
                                                      action="{{ route('role-requests.action', [$context->id, $action]) }}"
                                                      @if (in_array($action, ['suspend', 'end'], true)) onsubmit="return confirm('{{ ucfirst($action) }} this access?')" @endif>
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-sm mb-1 {{ in_array($action, ['approve', 'reactivate'], true) ? 'btn-success' : 'btn-outline-danger' }}">
                                                        {{ ucfirst($action) }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center">No requests.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $contexts->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
