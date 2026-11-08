@props(['permissions', 'checked' => [], 'name' => 'permissions[]', 'legend' => 'Allowed actions'])
<fieldset class="ap-perms">
    <legend>{{ $legend }}</legend>
    <div class="ap-perm-tools"><button type="button" class="ap-link-btn" onclick="this.closest('fieldset').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true)">Select all</button><button type="button" class="ap-link-btn" onclick="this.closest('fieldset').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">Clear</button></div>
    <div class="ap-perm-grid">
        @foreach($permissions as $permission)
            @php [$label, $help] = \App\Services\WorkspaceAccess::PERMISSION_LABELS[$permission] ?? [$permission, '']; @endphp
            <label class="ap-perm"><input type="checkbox" name="{{ $name }}" value="{{ $permission }}" @checked(in_array($permission, $checked, true))><span><strong>{{ $label }}</strong><span>{{ $help }}</span></span></label>
        @endforeach
    </div>
</fieldset>
