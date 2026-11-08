<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $report['title'] }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2b49; }
    header { border-bottom: 2px solid #176ce0; margin-bottom: 12px; padding-bottom: 8px; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    p { margin: 0; color: #697b94; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #eaf2ff; text-align: left; padding: 5px; font-size: 9px; }
    td { padding: 4px 5px; border-bottom: 1px solid #e4eaf3; }
    tr:nth-child(even) td { background: #f8fafd; }
    footer { margin-top: 10px; font-size: 8px; color: #94a0b1; }
</style>
</head>
<body>
<header>
    <h1>Parent Teacher Mobile · {{ $report['title'] }}</h1>
    <p>{{ $context['label'] }} · {{ $context['organization'] }} · {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }} · Generated {{ now()->format('j M Y H:i') }} UTC</p>
</header>
<table>
    <thead><tr>@foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
    <tbody>@forelse($report['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['columns']) }}">No records match these filters.</td></tr>@endforelse</tbody>
</table>
<footer>{{ number_format(count($report['rows'])) }} rows @if($report['truncated'])(limited to the first {{ number_format($report['limit']) }})@endif · Confidential: contains school data. Handle under your organization's data policy.</footer>
</body>
</html>
