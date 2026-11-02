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
</head>
<body class="ws-body" data-role="{{ $context['role'] }}">
<a class="ws-skip" href="#main">Skip to content</a>
@php
    $nav = ['home' => 'Home', 'messages' => 'Messages', 'schedule' => 'Schedule', 'events' => 'Events', 'profile' => 'Profile'];
    $icons = ['home'=>'home', 'messages'=>'comments', 'schedule'=>'calendar', 'events'=>'star', 'profile'=>'user-circle', 'students'=>'graduation-cap', 'updates'=>'file-text', 'classes'=>'book', 'notices'=>'bullhorn', 'people'=>'users', 'files'=>'paperclip'];
    $extra = [];
    if ($context['role'] === 'parent' || $access->can($context, 'students.view')) { $extra = ['students' => $context['role'] === 'parent' ? 'My students' : 'Students'];
        if ($context['role'] !== 'staff') { $extra['updates'] = 'Student updates'; } }
    if (in_array($context['role'], ['teacher', 'school_admin', 'district_admin', 'super_admin']) || $access->can($context, 'academics.manage')) { $extra['classes'] = $context['role'] === 'teacher' ? 'My classes' : 'Academic structure'; }
    $extra['notices'] = 'Announcements';
    $extra['files'] = 'Attachments';
    $extra['support'] = 'Support'; $icons['support'] = 'life-ring';
    if ($access->can($context, 'memberships.manage')) { $extra['people'] = 'People & access'; }
    if (in_array($context['role'], ['super_admin', 'sub_super_admin', 'school_admin', 'district_admin'])) {
        $extra['administration'] = 'Organizations & reports'; $icons['administration'] = 'university';
    }
    $url = fn($target, $query = []) => route('workspace.page', ['page' => $target, 'context' => $context['id']] + $query);
    // The four administrative panels share one admin menu in the approved reference order; items the
    // context cannot use are hidden here and every page still re-authorizes on the server.
    $admin = in_array($context['role'], \App\Services\WorkspaceAnalytics::ADMIN_ROLES, true);
    if ($admin) {
        $role = $context['role'];
        $item = fn($label, $icon, $target, $query = [], $fragment = '', $current = null) => ['label' => $label, 'icon' => $icon,
            'url' => $url($target, $query).$fragment, 'current' => $current ?? ($page === $target && (request('role') ?? '') === ($query['role'] ?? ''))];
        $adminNav = array_filter([
            $item('Dashboard', 'th-large', 'home'),
            $item('Video Calls', 'video-camera', 'video'),
            $access->can($context, 'memberships.manage') ? $item('Parents / Guardians', 'users', 'people', ['role' => 'parent']) : null,
            $access->can($context, 'memberships.manage') ? $item('Teachers', 'user', 'people', ['role' => 'teacher']) : null,
            $access->can($context, 'memberships.manage') ? $item('Staff', 'id-badge', 'people', ['role' => 'staff']) : null,
            $access->can($context, 'students.view') ? $item('Students', 'graduation-cap', 'students') : null,
            $item($role === 'school_admin' ? 'School' : 'Schools', 'university', 'administration', [], '#schools'),
            in_array($role, ['super_admin', 'sub_super_admin', 'district_admin']) ? $item('Districts', 'map-o', 'administration', [], '#districts', false) : null,
            in_array($role, ['super_admin', 'district_admin']) ? $item('Administrators', 'user-secret', 'administration', [], '#administrators', false) : null,
            $role === 'super_admin' ? $item('Sub Super Admins', 'sitemap', 'administration', [], '#sub-super-admins', false) : null,
            $item('Communications', 'bullhorn', 'notices'),
            $access->can($context, 'analytics.view') ? $item('Analytics & Insights', 'bar-chart', 'analytics') : null,
            $access->can($context, 'reports.export') ? $item('Reports & Exports', 'file-text-o', 'reports') : null,
            $role === 'super_admin' ? $item('Subscriptions & Billing', 'credit-card', 'billing') : null,
            $item('Support', 'life-ring', 'support'),
            $access->can($context, 'audit.view') ? $item('Audit / Activity Log', 'history', 'audit') : null,
            $item('Settings', 'cog', 'profile'),
        ]);
        $workspaceNav = array_filter([
            $item('Messages', 'comments', 'messages'),
            $item('Schedule', 'calendar', 'schedule'),
            $item('Events', 'star', 'events'),
            in_array($role, ['school_admin', 'district_admin', 'super_admin']) || $access->can($context, 'academics.manage') ? $item('Academic structure', 'book', 'classes') : null,
            $access->can($context, 'students.view') ? $item('Student updates', 'file-text', 'updates') : null,
            $access->can($context, 'memberships.manage') ? $item('People & access', 'address-book-o', 'people') : null,
            $item('Attachments', 'paperclip', 'files'),
        ]);
    }
@endphp
<aside class="ws-sidebar" aria-label="Workspace navigation">
    <a class="ws-brand" href="{{ $url('home') }}">Parent Teacher <span>Mobile</span></a>
    @if($admin)
    <p class="ws-caption">{{ strtoupper($context['label']) }}</p>
    <nav class="ws-admin-nav">@foreach($adminNav as $link)<a href="{{ $link['url'] }}" @if($link['current']) aria-current="page" @endif><i class="fa fa-{{ $link['icon'] }}" aria-hidden="true"></i><span>{{ $link['label'] }}</span></a>@endforeach</nav>
    <p class="ws-caption">YOUR WORKSPACE</p>
    <nav>@foreach($workspaceNav as $link)<a href="{{ $link['url'] }}" @if($link['current']) aria-current="page" @endif><i class="fa fa-{{ $link['icon'] }}" aria-hidden="true"></i><span>{{ $link['label'] }}</span></a>@endforeach</nav>
    @else
    <p class="ws-caption">YOUR WORKSPACE</p>
    <nav>@foreach($nav as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
    <p class="ws-caption">{{ strtoupper($context['label']) }}</p>
    <nav>@foreach($extra as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
    @endif
    @if(auth()->user()->hasRole('Super Admin'))<a class="ws-legacy" href="{{ route('home') }}">Classic admin tools ↗</a>@endif
    <div class="ws-sidebar-foot">@if($admin)<span class="ws-role-badge"><i class="fa fa-{{ $context['role'] === 'super_admin' ? 'star' : 'shield' }}" aria-hidden="true"></i>{{ $context['label'] }}</span>@endif Together, brighter futures.<br><span>Parent Teacher Mobile · Version {{ config('app.version') }}</span></div>
</aside>
<div class="ws-shell">
    <header class="ws-topbar">
        {{-- Super Admin already covers the full platform, and a single workspace has nothing to switch to. --}}
        @if(count($contexts) > 1 && $context['role'] !== 'super_admin')
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
        @if(session('workspace_success'))<div class="ws-alert ws-success" role="status">{{ session('workspace_success') }}</div>@endif
        @if($errors->any())<div class="ws-alert ws-error" role="alert"><strong>Please check your entry.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('workspace-content')
    </main>
    <footer class="ws-footer">{{ $context['organization'] }} <span>·</span> {{ $context['label'] }}</footer>
</div>
<nav class="ws-mobile-nav" aria-label="Primary navigation">@foreach($nav as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
@stack('workspace-scripts')
</body>
</html>
