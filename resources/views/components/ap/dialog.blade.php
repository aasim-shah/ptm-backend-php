@props(['id', 'title', 'sub' => null])
<dialog class="ap-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <header><div><h2 id="{{ $id }}-title">{{ $title }}</h2>@if($sub)<p>{{ $sub }}</p>@endif</div><button type="button" class="ap-close" data-dialog-close aria-label="Close">&times;</button></header>
    <div class="ap-dialog-body">{{ $slot }}</div>
</dialog>
