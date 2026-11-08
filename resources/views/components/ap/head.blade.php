@props(['eyebrow' => null, 'title', 'sub' => null, 'back' => null, 'backLabel' => 'Back'])
<header class="ap-head">
    <div>
        @if($back)<a class="ap-back" href="{{ $back }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ $backLabel }}</a>@endif
        @if($eyebrow)<p class="ws-eyebrow">{{ $eyebrow }}</p>@endif
        <h1>{{ $title }}</h1>
        @if($sub)<p class="ap-sub">{{ $sub }}</p>@endif
    </div>
    @if(trim($slot))<div class="ap-head-actions">{{ $slot }}</div>@endif
</header>
