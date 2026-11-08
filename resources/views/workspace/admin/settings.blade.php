@extends('workspace.layout')
@section('workspace-content')
@php
    $field = fn ($key) => str_replace('.', '__', $key);
    $sections = [
        'trial' => ['Free trial', 'What a new account receives before choosing a plan. Used by the mobile app sign-up.', 'gift'],
        'video' => ['Video minutes', 'How call time is counted against a subscription or trial.', 'video-camera'],
        'policy' => ['Terms, privacy & consent', 'Raise a version to ask everyone to accept the new document the next time they open the app.', 'file-text-o'],
        'retention' => ['Data retention', 'How long records are kept before they are removed automatically.', 'archive'],
    ];
@endphp
<x-ap.head :eyebrow="$context['label'].' / Settings'" title="Platform settings" sub="USA and UK market configuration and platform-wide policies. Changes apply immediately and are recorded in the activity log. Your own name, time zone and password are under My profile.">
    <a class="ws-btn ap-btn-ghost" href="{{ route('workspace.page', ['page' => 'profile', 'context' => $context['id']]) }}"><i class="fa fa-user-circle" aria-hidden="true"></i>My profile</a>
    <a class="ws-btn ap-btn-ghost" href="{{ route('workspace.integrations', ['context' => $context['id']]) }}"><i class="fa fa-plug" aria-hidden="true"></i>Integrations</a>
</x-ap.head>

<h2 style="font-size:16px;margin:0 0 12px">Markets</h2>
<div class="ap-settings-grid" id="markets">
@foreach($markets as $market)
    <section class="ap-panel">
        <header><div><h2><span class="ap-market ap-market--{{ $market->code }}">{{ $market->code }}</span> {{ $market->name }}</h2><p>{{ number_format($counts[$market->code] ?? 0) }} schools use this market.</p></div><x-ap.pill :status="$market->enabled ? 'active' : 'suspended'" :label="$market->enabled ? 'Open for sign-ups' : 'Closed'" /></header>
        <div class="ap-panel-body">
            <form method="post" action="{{ route('workspace.market-save', $market->code) }}" class="ap-form">@csrf
                <input type="hidden" name="context" value="{{ $context['id'] }}">
                <label class="ap-toggle"><span><strong>Open for new schools and sign-ups</strong><br><small>Closing a market keeps existing schools working.</small></span><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($market->enabled)></label>
                <label>Currency code<input name="currency" value="{{ $market->currency }}" maxlength="3" required></label>
                <label>Currency symbol<input name="currency_symbol" value="{{ $market->currency_symbol }}" maxlength="4" required></label>
                <label>Language & number format<select name="locale"><option value="en_US" @selected($market->locale === 'en_US')>English (US)</option><option value="en_GB" @selected($market->locale === 'en_GB')>English (UK)</option></select></label>
                <label>Date format<select name="date_format">@foreach(['m/d/Y' => 'MM/DD/YYYY', 'd/m/Y' => 'DD/MM/YYYY', 'Y-m-d' => 'YYYY-MM-DD'] as $v => $l)<option value="{{ $v }}" @selected($market->date_format === $v)>{{ $l }}</option>@endforeach</select></label>
                <label>Default time zone<select name="timezone">@foreach(\DateTimeZone::listIdentifiers($market->code === 'US' ? \DateTimeZone::AMERICA : \DateTimeZone::EUROPE) as $tz)<option value="{{ $tz }}" @selected($market->timezone === $tz)>{{ $tz }}</option>@endforeach</select></label>
                <label>Phone prefix<input name="phone_prefix" value="{{ $market->phone_prefix }}" required maxlength="6"></label>
                <label>Postal code label<input name="postal_label" value="{{ $market->postal_label }}" required maxlength="32"></label>
                <label>Region label<input name="region_label" value="{{ $market->region_label }}" required maxlength="32"></label>
                <p class="ap-hint">Terminology shown to schools in this market.</p>
                @foreach(['grade' => 'Academic level', 'school_admin' => 'School leader', 'district' => 'Group of schools', 'guardian' => 'Parent', 'term' => 'School term'] as $key => $label)
                    <label>{{ $label }}<input name="terminology[{{ $key }}]" value="{{ $market->terminology[$key] ?? '' }}" required maxlength="40"></label>
                @endforeach
                <label>Minimum user age<input type="number" name="minimum_age" value="{{ $market->minimum_age }}" min="13" max="18" required></label>
                <label>Support email<input type="email" name="support_email" value="{{ $market->support_email }}" maxlength="191"></label>
                <label class="ap-span">Privacy policy URL<input type="url" name="privacy_url" value="{{ $market->privacy_url }}" maxlength="255" placeholder="https://"></label>
                <label class="ap-span">Terms of service URL<input type="url" name="terms_url" value="{{ $market->terms_url }}" maxlength="255" placeholder="https://"></label>
                <div class="ap-form-actions"><button class="ws-btn">Save {{ $market->name }}</button></div>
            </form>
        </div>
    </section>
@endforeach
</div>

<h2 style="font-size:16px;margin:8px 0 12px">Platform policies</h2>
<div class="ap-settings-grid">
@foreach($sections as $section => [$title, $about, $icon])
    <section class="ap-panel" id="{{ $section }}">
        <header><div><h2><i class="fa fa-{{ $icon }}" aria-hidden="true"></i> {{ $title }}</h2><p>{{ $about }}</p></div></header>
        <div class="ap-panel-body">
            <form method="post" action="{{ route('workspace.settings-save') }}" class="ap-form">@csrf
                <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="section" value="{{ $section }}">
                @foreach($definitions as $key => [$default, $rule, $label, $help])
                    @continue(!str_starts_with($key, $section.'.'))
                    @if(is_bool($default))
                        <label class="ap-toggle"><span><strong>{{ $label }}</strong><br><small>{{ $help }}</small></span><input type="hidden" name="{{ $field($key) }}" value="0"><input type="checkbox" name="{{ $field($key) }}" value="1" @checked($values[$key])></label>
                    @elseif($key === 'video.rounding')
                        <label class="ap-span">{{ $label }}<select name="{{ $field($key) }}"><option value="minute_up" @selected($values[$key] === 'minute_up')>Round each call up to the next whole minute</option><option value="second" @selected($values[$key] === 'second')>Count every second, no rounding</option></select><small>{{ $help }}</small></label>
                    @else
                        <label>{{ $label }}<input name="{{ $field($key) }}" value="{{ $values[$key] }}" @if(is_int($default)) type="number" @endif required><small>{{ $help }}</small></label>
                    @endif
                @endforeach
                <div class="ap-form-actions"><button class="ws-btn">Save</button></div>
            </form>
        </div>
    </section>
@endforeach
</div>
@endsection
