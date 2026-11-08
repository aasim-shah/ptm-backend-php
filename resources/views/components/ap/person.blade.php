@props(['first' => '', 'last' => '', 'email' => null, 'href' => null])
@php $name = trim($first.' '.$last) ?: ($email ?? 'Unnamed account'); $initials = mb_strtoupper(mb_substr($first ?: $name, 0, 1).mb_substr($last, 0, 1)); @endphp
<div class="ap-person"><span class="ap-avatar" aria-hidden="true">{{ $initials }}</span><div>@if($href)<a href="{{ $href }}"><strong>{{ $name }}</strong></a>@else<strong>{{ $name }}</strong>@endif @if($email)<small>{{ $email }}</small>@endif</div></div>
