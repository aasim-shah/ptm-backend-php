<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $context['label'] }} · Parent Teacher Mobile</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/lumen-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/workspaces.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/colourful-portals.css') }}?v={{ filemtime(public_path('assets/css/colourful-portals.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/portal-ui.css') }}?v={{ filemtime(public_path('assets/css/portal-ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-ui.css') }}?v={{ filemtime(public_path('assets/css/admin-ui.css')) }}">
</head>
<body class="ws-body" data-role="{{ $context['role'] }}">
<a class="ws-skip" href="#main">Skip to content</a>
@php
    $nav = ['home' => 'Home', 'messages' => 'Messages', 'schedule' => 'Schedule', 'events' => 'Events', 'profile' => 'Profile'];
    $icons = ['home'=>'home', 'messages'=>'comments', 'schedule'=>'calendar', 'events'=>'star', 'profile'=>'user-circle', 'students'=>'graduation-cap', 'updates'=>'file-text', 'classes'=>'book', 'notices'=>'bullhorn', 'people'=>'users', 'files'=>'paperclip', 'support'=>'life-ring'];
    $url = fn($target, $query = []) => route('workspace.page', ['page' => $target, 'context' => $context['id']] + $query);
    $named = fn($route, $query = []) => route($route, ['context' => $context['id']] + $query);
    $platform = \App\Services\WorkspaceAccess::platformWide($context);
    $admin = in_array($context['role'], \App\Services\WorkspaceAnalytics::ADMIN_ROLES, true);
    $link = fn($label, $icon, $key, $href) => ['label' => $label, 'icon' => $icon, 'url' => $href, 'current' => $page === $key];
    $role = $context['role'];
    if ($admin) {
        // The four administrative panels share one menu in the approved reference order. Items a
        // context cannot use are hidden here, and every page still re-authorizes on the server.
        $people = $access->can($context, 'memberships.manage');
        $adminNav = array_filter([
            $link('Dashboard', 'th-large', 'home', $named('workspace.home')),
            $link('Video Calls', 'video-camera', 'video', $named('workspace.video')),
            $people ? $link('Parents / Guardians', 'users', 'parents', $named('workspace.parents')) : null,
            $people ? $link('Teachers', 'user', 'teachers', $named('workspace.teachers')) : null,
            $people ? $link('Staff', 'id-badge', 'staff', $named('workspace.staff')) : null,
            $access->can($context, 'students.view') ? $link('Students', 'graduation-cap', 'students', $named('workspace.students')) : null,
            $platform || $role === 'school_admin' ? $link('Role requests', 'id-card-o', 'role-requests', $named('workspace.role-requests')) : null,
            $role === 'school_admin' && count($context['schools']) === 1
                ? $link('School', 'university', 'schools', route('workspace.school', ['id' => array_key_first($context['schools']), 'context' => $context['id']]))
                : $link('Schools', 'university', 'schools', $named('workspace.schools')),
            in_array($role, ['super_admin', 'sub_super_admin', 'district_admin'], true) ? $link('Districts', 'map-o', 'districts', $named('workspace.districts')) : null,
            in_array($role, ['super_admin', 'district_admin'], true) ? $link('Administrators', 'user-secret', 'administrators', $named('workspace.administrators')) : null,
            $platform ? $link('Sub Super Admins', 'sitemap', 'sub-super-admins', $named('workspace.sub-super-admins')) : null,
            $link('Communications', 'bullhorn', 'notices', $url('notices')),
            $access->can($context, 'analytics.view') ? $link('Analytics & Insights', 'bar-chart', 'analytics', $named('workspace.analytics')) : null,
            $access->can($context, 'reports.export') ? $link('Reports & Exports', 'file-text-o', 'reports', $named('workspace.reports')) : null,
            $people ? $link('Bulk Imports', 'upload', 'imports', $named('workspace.imports')) : null,
            $platform ? $link('Subscriptions & Billing', 'credit-card', 'billing', $named('workspace.billing')) : null,
            $platform || in_array($role, ['school_admin', 'district_admin'], true) ? $link('Ad-free Requests', 'ban', 'ad-free', $named('workspace.ad-free-requests')) : null,
            $platform ? $link('Integrations', 'plug', 'integrations', $named('workspace.integrations')) : null,
            $link('Support', 'life-ring', 'support', $named('workspace.support')),
            $access->can($context, 'audit.view') ? $link('Audit / Activity Log', 'history', 'audit', $named('workspace.audit')) : null,
            match (true) {
                $platform => $link('Settings', 'cog', 'settings', $named('workspace.settings')),
                $role === 'school_admin' && count($context['schools']) === 1 => $link('Settings', 'cog', 'school-settings', route('workspace.school', ['id' => array_key_first($context['schools']), 'context' => $context['id']]).'#profile'),
                default => $link('Settings', 'cog', 'profile', $url('profile')),
            },
        ]);
        $workspaceNav = array_filter([
            $link('Messages', 'comments', 'messages', $url('messages')),
            $link('Schedule', 'calendar', 'schedule', $url('schedule')),
            $link('Events', 'star', 'events', $url('events')),
            in_array($role, ['school_admin', 'district_admin', 'super_admin'], true) || $access->can($context, 'academics.manage') ? $link('Academic structure', 'book', 'classes', $url('classes')) : null,
            $access->can($context, 'updates.write') ? $link('Student updates', 'file-text', 'updates', $url('updates')) : null,
            $link('Attachments', 'paperclip', 'files', $url('files')),
            // Settings already opens the profile for District Admins and Sub-Super Admins.
            $platform || ($role === 'school_admin' && count($context['schools']) === 1) ? $link('My Profile', 'user-circle', 'profile', $url('profile')) : null,
        ]);
    } else {
        $adminNav = [];
        $workspaceNav = array_map(fn ($key) => $link($nav[$key], $icons[$key], $key, $url($key)), array_keys($nav));
        $extra = [];
        if ($role === 'parent' || $access->can($context, 'students.view')) { $extra[] = $link($role === 'parent' ? 'My students' : 'Students', 'graduation-cap', 'students', $url('students')); }
        if ($role !== 'staff' && $access->can($context, 'updates.write')) { $extra[] = $link('Student updates', 'file-text', 'updates', $url('updates')); }
        if ($role === 'teacher' || $access->can($context, 'academics.manage')) { $extra[] = $link($role === 'teacher' ? 'My classes' : 'Academic structure', 'book', 'classes', $url('classes')); }
        $extra[] = $link('Announcements', 'bullhorn', 'notices', $url('notices'));
        $extra[] = $link('Attachments', 'paperclip', 'files', $url('files'));
        $extra[] = $link('Support', 'life-ring', 'support', $named('workspace.support'));
    }
@endphp
<aside class="ws-sidebar" id="ws-sidebar" aria-label="Workspace navigation">
    <a class="ws-brand" href="{{ $named('workspace.home') }}">Parent Teacher <span>Mobile</span></a>
    @if($admin)
    <p class="ws-caption">{{ strtoupper($context['label']) }}</p>
    <nav class="ws-admin-nav">@foreach($adminNav as $item)<a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif><i class="fa fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a>@endforeach</nav>
    <p class="ws-caption">YOUR WORKSPACE</p>
    <nav>@foreach($workspaceNav as $item)<a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif><i class="fa fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a>@endforeach</nav>
    @else
    <p class="ws-caption">YOUR WORKSPACE</p>
    <nav>@foreach($workspaceNav as $item)<a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif><i class="fa fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a>@endforeach</nav>
    <p class="ws-caption">{{ strtoupper($context['label']) }}</p>
    <nav>@foreach($extra as $item)<a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif><i class="fa fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a>@endforeach</nav>
    @endif
    <div class="ws-sidebar-foot">@if($admin)<span class="ws-role-badge"><i class="fa fa-{{ $role === 'super_admin' ? 'star' : 'shield' }}" aria-hidden="true"></i>{{ $context['label'] }}</span>@endif Together, brighter futures.<br><span>Parent Teacher Mobile · Version {{ config('app.version') }}</span></div>
</aside>
<div class="ws-sidebar-scrim" data-nav-close></div>
<div class="ws-shell">
    <header class="ws-topbar">
        <button type="button" class="ws-menu-toggle" aria-controls="ws-sidebar" aria-expanded="false" data-nav-toggle><i class="fa fa-bars" aria-hidden="true"></i>Menu</button>
        @if($role === 'super_admin')
        {{-- A Super Admin can open one school or district to support it, then return to the full platform. --}}
        @php $supportSchools = \Illuminate\Support\Facades\DB::table('schools')->orderBy('school_name')->limit(500)->pluck('school_name', 'id');
             $supportDistricts = \Illuminate\Support\Facades\DB::table('workspace_districts')->orderBy('name')->pluck('name', 'id'); @endphp
        <form method="post" action="{{ route('workspace.select') }}" class="ws-context">@csrf
            <label for="support-context">Viewing</label>
            <div><select id="support-context" name="context" onchange="this.form.requestSubmit()">
                <option value="platform" @selected($context['id'] === 'platform')>Full platform</option>
                @if($supportDistricts->count())<optgroup label="Support a district">@foreach($supportDistricts as $id => $name)<option value="platform:d{{ $id }}" @selected($context['id'] === 'platform:d'.$id)>{{ $name }}</option>@endforeach</optgroup>@endif
                <optgroup label="Support a school">@foreach($supportSchools as $id => $name)<option value="platform:s{{ $id }}" @selected($context['id'] === 'platform:s'.$id)>{{ $name }}</option>@endforeach</optgroup>
            </select><noscript><button type="submit" class="ws-btn ws-btn-small">Open</button></noscript></div>
        </form>
        @elseif(count($contexts) > 1)
        <form method="post" action="{{ route('workspace.select') }}" class="ws-context">@csrf
            <label for="workspace-context">Role & organization</label>
            <div><select id="workspace-context" name="context">@foreach($contexts as $option)<option value="{{ $option['id'] }}" @selected($option['id'] === $context['id'])>{{ $option['label'] }} · {{ $option['organization'] }}</option>@endforeach</select><button type="submit" class="ws-btn ws-btn-small">Switch</button></div>
        </form>
        @else
        <div class="ws-context"><span class="ws-context-label">Role & organization</span><strong class="ws-context-current">{{ $context['label'] }} · {{ $context['organization'] }}</strong></div>
        @endif
        <div class="ws-account"><a class="ws-notification-link" href="{{ route('workspace.notifications') }}" aria-label="Notifications" title="Notifications"><i class="fa fa-bell-o" aria-hidden="true"></i></a><a class="ws-account-name" href="{{ $url('profile') }}"><span class="ws-account-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->first_name,0,1).mb_substr(auth()->user()->last_name,0,1)) }}</span><span>{{ auth()->user()->first_name }}</span></a><form method="post" action="{{ route('workspace.logout') }}">@csrf<button class="ws-link" type="submit">Sign out</button></form></div>
    </header>
    <main id="main" tabindex="-1" class="ws-main">
        @if(!empty($context['support_view']))<div class="ws-alert ap-hint--warn" role="status"><i class="fa fa-life-ring" aria-hidden="true"></i> Support view: you are seeing <strong>{{ $context['organization'] }}</strong> with Super Admin authority. Lists are limited to this organization. <form method="post" action="{{ route('workspace.select') }}" class="ap-inline">@csrf<input type="hidden" name="context" value="platform"><button class="ap-link-btn">Return to full platform</button></form></div>@endif
        @if(session('workspace_success'))<div class="ws-alert ws-success" role="status">{{ session('workspace_success') }}</div>@endif
        @if($errors->any())<div class="ws-alert ws-error" role="alert"><strong>Please check your entry.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('workspace-content')
    </main>
    <footer class="ws-footer">{{ $context['organization'] }} <span>·</span> {{ $context['label'] }}</footer>
</div>
@unless($admin || $role === 'staff')
<nav class="ws-mobile-nav" aria-label="Primary navigation">@foreach($nav as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
@endunless
<script>
(function () {
    var body = document.body, toggle = document.querySelector('[data-nav-toggle]');
    function setNav(open) { body.classList.toggle('ws-nav-open', open); if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    if (toggle) toggle.addEventListener('click', function () { setNav(!body.classList.contains('ws-nav-open')); });
    document.querySelectorAll('[data-nav-close]').forEach(function (el) { el.addEventListener('click', function () { setNav(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
    // Dialogs: <button data-dialog-open="id"> opens <dialog id="id">; [data-dialog-close] closes it.
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-dialog-open]');
        if (opener) { var d = document.getElementById(opener.getAttribute('data-dialog-open')); if (d && d.showModal) { e.preventDefault(); d.showModal(); var f = d.querySelector('input:not([type=hidden]),select,textarea'); if (f) f.focus(); } }
        var closer = e.target.closest('[data-dialog-close]');
        if (closer) { var dlg = closer.closest('dialog'); if (dlg) dlg.close(); }
    });
    // Tabs inside dialogs: buttons with data-tab show the matching [data-tab-panel].
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-tab]'); if (!tab) return;
        var root = tab.closest('[data-tabs]');
        root.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b === tab ? 'true' : 'false'); });
        root.querySelectorAll('[data-tab-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-tab-panel') !== tab.getAttribute('data-tab'); });
    });
    // Reopen the dialog whose form failed validation so the person sees the errors in place.
    var failed = @json(old('_dialog'));
    if (failed) { var d = document.getElementById(failed); if (d && d.showModal) d.showModal(); }
    // Confirm destructive actions.
    document.addEventListener('submit', function (e) { var msg = e.target.getAttribute('data-confirm'); if (msg && !confirm(msg)) e.preventDefault(); });
})();
</script>
@stack('workspace-scripts')
</body>
</html>
