@props(['items'])
@if($items->hasPages() || $items->total() > 0)
<div class="ap-pager"><span>Showing {{ number_format($items->firstItem() ?? 0) }}–{{ number_format($items->lastItem() ?? 0) }} of {{ number_format($items->total()) }}</span>{{ $items->onEachSide(1)->links('pagination::simple-bootstrap-4') }}</div>
@endif
