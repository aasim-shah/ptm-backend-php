@php $presented = app(\App\Services\AuditPresenter::class)->present(collect($entries)); @endphp
@if($presented->count())
<ul class="ap-list ap-timeline">
    @foreach($presented as $entry)
        <li><div><strong>{{ $entry->action_label }}</strong>@if($entry->subject_label) <span class="ap-muted">· {{ $entry->subject_label }}</span>@endif<small>by {{ $entry->actor_name }}</small></div><time datetime="{{ $entry->created_at }}">{{ $formatTime($entry->created_at, 'j M · H:i') }}</time></li>
    @endforeach
</ul>
@else
<p class="ap-muted" style="margin:0">No recorded activity yet.</p>
@endif
