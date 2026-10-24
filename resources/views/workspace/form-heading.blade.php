<div class="ws-section-heading">
    <span class="ws-section-icon" data-tone="{{ $tone ?? 'blue' }}" aria-hidden="true"><i class="fa fa-{{ $icon ?? 'pencil-square-o' }}"></i></span>
    <div><h2>{{ $title }}</h2>@if(!empty($description))<p>{{ $description }}</p>@endif</div>
</div>
