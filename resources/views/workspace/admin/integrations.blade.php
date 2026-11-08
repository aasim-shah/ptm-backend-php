@extends('workspace.layout')
@section('workspace-content')
<x-ap.head :eyebrow="$context['label'].' / Integrations'" title="Integrations & monetization" sub="Health of each outside service the platform depends on. Keys and secrets stay on the server; this page only shows whether each one is set.">
    <a class="ws-btn ap-btn-ghost" href="{{ route('workspace.integrations', ['context' => $context['id']]) }}"><i class="fa fa-refresh" aria-hidden="true"></i>Check again</a>
</x-ap.head>

<div class="ap-health" style="margin-bottom:24px">
@foreach($providers as $p)
    <article>
        <h3><span><i class="fa fa-{{ $p['icon'] }}" aria-hidden="true"></i> {{ $p['name'] }}</span><x-ap.pill :status="$p['ok'] ? 'ok' : 'missing'" :label="$p['ok'] ? 'Working' : 'Needs setup'" /></h3>
        <p>{{ $p['about'] }}</p>
        <ul>@foreach($p['facts'] as $label => $ok)<li><span>{{ $label }}</span><span style="color:{{ $ok ? '#1c7956' : '#b42343' }}"><i class="fa fa-{{ $ok ? 'check' : 'times' }}" aria-hidden="true"></i> {{ $ok ? 'Set' : 'Missing' }}</span></li>@endforeach</ul>
        @if($p['note'])<p style="margin:10px 0 0">{{ $p['note'] }}</p>@endif
    </article>
@endforeach
</div>

<section class="ap-panel" id="ads">
    <header><div><h2><i class="fa fa-bullhorn" aria-hidden="true"></i> AdMob ads in the mobile app</h2><p>The app reads these switches from <code>/api/auth/settings</code> (<code>ads_enabled</code>). Approved ad-free schools never see ads. {{ $ads['approved'] }} approved, {{ $ads['pending'] }} waiting for review.</p></div><a href="{{ route('workspace.ad-free-requests', ['context' => $context['id']]) }}">Ad-free requests →</a></header>
    <div class="ap-panel-body">
        <form method="post" action="{{ route('workspace.ads-save') }}" class="ap-form" data-confirm="Save ad settings? Turning ads off affects every app user.">@csrf
            <input type="hidden" name="context" value="{{ $context['id'] }}">
            <label class="ap-toggle"><span><strong>Show ads (kill switch)</strong><br><small>Off hides every ad on every device straight away.</small></span><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($ads['enabled'])></label>
            <label class="ap-toggle"><span><strong>Ads in the United States</strong></span><input type="hidden" name="markets[US]" value="0"><input type="checkbox" name="markets[US]" value="1" @checked($ads['markets']['US'] ?? true)></label>
            <label class="ap-toggle"><span><strong>Ads in the United Kingdom</strong></span><input type="hidden" name="markets[UK]" value="0"><input type="checkbox" name="markets[UK]" value="1" @checked($ads['markets']['UK'] ?? true)></label>
            <div class="ap-form-actions"><button class="ws-btn">Save ad settings</button></div>
        </form>
    </div>
</section>
@endsection
