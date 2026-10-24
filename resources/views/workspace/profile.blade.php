<section class="ws-profile-banner" aria-label="Your account">
    <div class="ws-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->first_name,0,1).mb_substr(auth()->user()->last_name,0,1)) }}</div>
    <div><h2>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2><p>{{ auth()->user()->email }}</p></div>
    <span class="ws-role-pill"><i class="fa fa-check-circle" aria-hidden="true"></i>{{ $context['label'] }}</span>
</section>
<div class="ws-profile-grid">
    <section class="ws-card">
        @include('workspace.form-heading', ['icon'=>'user-o','tone'=>'purple','title'=>'Personal information','description'=>'Keep your details up to date for your school community.'])
        <form method="post" action="{{ route('workspace.profile') }}" class="ws-form ws-form-grid">
            @csrf<input type="hidden" name="context" value="{{ $context['id'] }}">
            <label>First name<input name="first_name" value="{{ old('first_name',auth()->user()->first_name) }}" required maxlength="100" autocomplete="given-name" @if($errors->has('first_name')) aria-invalid="true" @endif></label>
            <label>Last name<input name="last_name" value="{{ old('last_name',auth()->user()->last_name) }}" required maxlength="100" autocomplete="family-name" @if($errors->has('last_name')) aria-invalid="true" @endif></label>
            <label>Timezone<select name="timezone" aria-describedby="profile-timezone-help">@foreach(\DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected($zone === old('timezone',$timezone))>{{ str_replace('_',' ',$zone) }}</option>@endforeach</select><span class="ws-field-hint" id="profile-timezone-help">Your meetings and events are displayed in this timezone.</span></label>
            <details><summary><i class="fa fa-lock" aria-hidden="true"></i>Change password</summary>
                <div class="ws-password-fields">
                    <label>Current password<input type="password" name="current_password" autocomplete="current-password"></label>
                    <div class="ws-field-row">
                        <label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" placeholder="At least 12 characters"></label>
                        <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" placeholder="Enter your new password again"></label>
                    </div>
                    <p class="ws-field-hint">Leave these fields empty to keep your current password.</p>
                </div>
            </details>
            <div class="ws-form-actions"><a href="{{ $url('home') }}">Back to dashboard</a><button class="ws-btn" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save changes</button></div>
        </form>
    </section>
    <section class="ws-card">
        @include('workspace.form-heading', ['icon'=>'building-o','tone'=>'green','title'=>'Your school connections','description'=>'The places you belong.'])
        @foreach($contexts as $option)
        <article class="ws-membership-card"><span class="ws-role-pill">{{ $option['label'] }}</span><h3>{{ $option['organization'] }}</h3><p>{{ $option['id'] === $context['id'] ? 'Your current workspace' : 'Available in your workspace switcher' }}</p></article>
        @endforeach
        <p class="ws-field-hint">Your school administrator can help with changes to your roles or guardian relationships.</p>
        <div class="ws-security-callout"><h3>A little extra peace of mind.</h3><p>Manage your authenticator, recovery codes and signed-in devices.</p><a href="{{ route('security.index') }}">Account security <span aria-hidden="true">→</span></a></div>
    </section>
</div>
