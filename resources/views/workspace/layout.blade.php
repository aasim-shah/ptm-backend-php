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
    if ($access->can($context, 'memberships.manage')) { $extra['people'] = 'People & access'; }
    if (in_array($context['role'], ['super_admin', 'sub_super_admin', 'school_admin', 'district_admin'])) {
        $extra['administration'] = 'Organizations & reports'; $icons['administration'] = 'university';
    }
    $url = fn($target) => route('workspace.page', ['page' => $target, 'context' => $context['id']]);
@endphp
<aside class="ws-sidebar" aria-label="Workspace navigation">
    <a class="ws-brand" href="{{ $url('home') }}">Parent Teacher <span>Mobile</span></a>
    <p class="ws-caption">YOUR WORKSPACE</p>
    <nav>@foreach($nav as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
    <p class="ws-caption">{{ strtoupper($context['label']) }}</p>
    <nav>@foreach($extra as $key => $label)<a href="{{ $url($key) }}" @if($page === $key) aria-current="page" @endif><i class="fa fa-{{ $icons[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></a>@endforeach</nav>
    @if(auth()->user()->hasRole('Super Admin'))<a class="ws-legacy" href="{{ route('home') }}">Existing admin panel ↗</a>@endif
    <div class="ws-sidebar-foot">Together, brighter futures.<br><span>Parent Teacher Mobile</span></div>
</aside>
<div class="ws-shell">
    <header class="ws-topbar">
        <form method="post" action="{{ route('workspace.select') }}" class="ws-context">@csrf
            <label for="workspace-context">Role & organization</label>
            <div><select id="workspace-context" name="context">@foreach($contexts as $option)<option value="{{ $option['id'] }}" @selected($option['id'] === $context['id'])>{{ $option['label'] }} · {{ $option['organization'] }}</option>@endforeach</select><button type="submit" class="ws-btn ws-btn-small">Switch</button></div>
        </form>
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
</body>
</html>
