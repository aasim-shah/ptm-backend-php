<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Parent Teacher Mobile</title>
    <style>
        :root { --ink: #1d2433; --muted: #5b6475; --line: #e3e7ef; --accent: #2f5bd3; --warn-bg: #fff6e0; --warn: #7a5300; --bg: #ffffff; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #e7ebf3; --muted: #a3acbd; --line: #2c3342; --accent: #8fb0ff; --warn-bg: #3a2f12; --warn: #ffd98a; --bg: #12161f; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 4px; }
        h2 { font-size: 1.15rem; margin: 28px 0 8px; }
        p, li { color: var(--ink); }
        .meta { color: var(--muted); font-size: .9rem; margin: 0 0 20px; }
        .notice { background: var(--warn-bg); color: var(--warn); border-radius: 8px; padding: 12px 14px; font-size: .95rem; margin: 0 0 20px; }
        a { color: var(--accent); }
        ul { padding-left: 1.2rem; }
        footer { border-top: 1px solid var(--line); margin-top: 36px; padding-top: 16px; color: var(--muted); font-size: .9rem; }
        img, table { max-width: 100%; }
    </style>
</head>
<body>
<main>
    <h1>@yield('title')</h1>
    @if (!empty($html))
        <div class="content">{!! $html !!}</div>
    @else
        <p class="meta">Version {{ $version }}</p>
        <p class="notice"><strong>Draft placeholder.</strong> This text is a placeholder until the owner of Parent Teacher Mobile supplies the final wording. Items in [brackets] must be completed.</p>
        @yield('placeholder')
    @endif
    <footer>
        <a href="{{ url('/terms') }}">Terms &amp; Conditions</a> · <a href="{{ url('/privacy-policy') }}">Privacy Policy</a>
    </footer>
</main>
</body>
</html>
