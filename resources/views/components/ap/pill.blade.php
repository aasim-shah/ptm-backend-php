@props(['status', 'label' => null])
<span class="ap-pill ap-pill--{{ \Illuminate\Support\Str::slug((string) $status) }}">{{ $label ?? ucfirst(str_replace('_', ' ', (string) $status)) }}</span>
