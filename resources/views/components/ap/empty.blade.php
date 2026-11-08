@props(['icon' => 'inbox', 'title', 'colspan' => null])
@if($colspan)<tr><td colspan="{{ $colspan }}">@endif
<div class="ap-empty"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i><strong>{{ $title }}</strong>{{ $slot }}</div>
@if($colspan)</td></tr>@endif
