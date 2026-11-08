@props(['stats'])
<div {{ $attributes->merge(['class' => 'ap-stats'.(count($stats) === 5 ? ' ap-stats--5' : '')]) }}>
    @foreach($stats as $stat)
        <div class="ap-stat"><i class="fa fa-{{ $stat['icon'] }}" aria-hidden="true"></i><div><span>{{ $stat['label'] }}</span><strong>{{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</strong></div></div>
    @endforeach
</div>
