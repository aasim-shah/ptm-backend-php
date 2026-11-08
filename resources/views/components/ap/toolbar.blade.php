@props(['action', 'context', 'placeholder' => 'Search', 'search' => true, 'count' => null])
<form method="get" action="{{ $action }}" class="ap-toolbar" role="search">
    <input type="hidden" name="context" value="{{ $context['id'] }}">
    @if($search)<div class="ap-search"><i class="fa fa-search" aria-hidden="true"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" maxlength="120"></div>@endif
    {{ $slot }}
    <button class="ws-btn ws-btn-small" type="submit">Apply</button>
    @if(collect(request()->except(['context', 'page']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())<a class="ap-reset" href="{{ $action.'?context='.urlencode($context['id']) }}">Clear filters</a>@endif
    @if($count !== null)<span class="ap-result-count">{{ number_format($count) }} {{ \Illuminate\Support\Str::plural('result', $count) }}</span>@endif
</form>
