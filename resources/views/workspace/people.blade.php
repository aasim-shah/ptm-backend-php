<div class="ws-people-grid">
    <section class="ws-card">
        @include('workspace.form-heading', ['icon'=>'user-plus','tone'=>'purple','title'=>'Connect an existing account','description'=>'Give someone with a PTM account a place in your school.'])
        <form method="post" action="{{ route('workspace.memberships') }}" class="ws-form ws-form-grid">
            @include('workspace.fields')
            <label class="ws-full">Account email<input type="email" name="email" required value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email"></label>
            <label class="ws-full">School role<select name="role"><option value="parent">Parent</option><option value="teacher">Teacher</option><option value="staff">Staff Member</option></select></label>
            <details><summary><i class="fa fa-sliders" aria-hidden="true"></i>Delegated staff tools</summary><fieldset><legend>Available when the role is Staff Member</legend>
                @foreach(\App\Services\WorkspaceAccess::STAFF_PERMISSIONS as $permission)
                    <label class="ws-check"><input type="checkbox" name="permissions[]" value="{{ $permission }}">{{ ['web.access'=>'Authorize staff web access','students.view'=>'View school student roster','notices.publish'=>'Publish school announcements','events.manage'=>'Manage school events'][$permission] }}</label>
                @endforeach
            </fieldset></details>
            <p class="ws-field-hint">The account must be active and verified. Administrator roles are managed by the platform owner.</p>
            <div class="ws-form-actions"><button class="ws-btn" type="submit">Connect account <span aria-hidden="true">→</span></button></div>
        </form>
    </section>
    <section class="ws-card">
        @include('workspace.form-heading', ['icon'=>'envelope-o','tone'=>'pink','title'=>'Invite someone to your school','description'=>'A warm welcome starts with a secure invitation.'])
        <form method="post" action="{{ route('workspace.invite') }}" class="ws-form ws-form-grid">
            @include('workspace.fields')
            <label class="ws-full">Email address<input name="email" type="email" required maxlength="191" placeholder="name@example.com" autocomplete="email"></label>
            <label class="ws-full">School role<select name="role"><option value="parent">Parent</option><option value="teacher">Teacher</option><option value="staff">Staff Member</option></select></label>
            <p class="ws-form-note">They’ll receive a link to activate their own account or connect an existing one. Passwords are never emailed.</p>
            <div class="ws-form-actions"><button class="ws-btn" type="submit">Send invitation <span aria-hidden="true">→</span></button></div>
        </form>
    </section>
</div>
<section class="ws-card">
    @include('workspace.form-heading', ['icon'=>'users','tone'=>'blue','title'=>'School memberships','description'=>'Your school community and the access each person has.'])
    <div class="ws-table-wrap"><table><thead><tr><th>Person</th><th>School</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($members as $member)
        <tr><td>{{ $member->first_name }} {{ $member->last_name }}<small>{{ $member->email }}</small></td><td>{{ $context['schools'][$member->school_id] }}</td><td>{{ \App\Services\WorkspaceAccess::LABELS[$member->role] ?? $member->role }}</td><td><span class="ws-badge" data-state="{{ $member->status }}">{{ ucfirst($member->status) }}</span></td><td>
            @if($member->status === 'active' && in_array($member->role,['parent','teacher','staff']))
            <form method="post" action="{{ route('workspace.revoke',$member->id) }}">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}"><button class="ws-link ws-link-danger">Revoke access</button></form>
            @endif
        </td></tr>
    @empty<tr><td colspan="5">No school memberships.</td></tr>@endforelse
    </tbody></table></div>{{ $members->links('pagination::simple-bootstrap-4') }}
</section>
<section class="ws-card">
    @include('workspace.form-heading', ['icon'=>'shield','tone'=>'green','title'=>'Guardian relationship review','description'=>'Help the right people stay connected to each student.'])
    <p class="ws-field-hint">Check the guardian’s identity and authority against school records before approving. Verified guardians can see published student information.</p>
    @forelse($guardians as $guardian)
    <article class="ws-row"><div class="ws-grow"><strong>{{ $guardian->first_name }} {{ $guardian->last_name }}</strong><p>Student reference {{ $guardian->student_id }} · {{ $context['schools'][$guardian->school_id] }} · {{ $guardian->status }}</p></div>
        <form method="post" action="{{ route('workspace.guardian-status',$guardian->id) }}" class="ws-inline">@csrf<input type="hidden" name="context" value="{{ $context['id'] }}">
            @if($guardian->status === 'pending')<button class="ws-btn ws-btn-small" name="status" value="verified">Verify</button><button class="ws-link ws-link-danger" name="status" value="rejected">Reject</button>
            @elseif($guardian->status === 'verified')<button class="ws-link ws-link-danger" name="status" value="revoked">Revoke relationship</button>@endif
        </form>
    </article>
    @empty<p class="ws-empty-line">No guardian requests to review. New requests will appear here.</p>@endforelse
</section>
