@extends('layouts.master')
@section('title')
{{__('dashboard')}}
@endsection

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $authUser = auth()->user();

    // Stat cards: only the counts this role receives from the controller are shown.
    $stats = [];
    if (isset($parent)) {
        $stats[] = ['label' => 'Parents', 'caption' => 'Total parents / guardians', 'value' => $parent, 'icon' => 'fa-users', 'tint' => 'pink',
            'url' => $authUser->can('parents-create') ? route('parents.index') : null];
    }
    if (isset($teacher)) {
        $stats[] = ['label' => 'Teachers', 'caption' => 'Total teachers', 'value' => $teacher, 'icon' => 'fa-id-badge', 'tint' => 'blue',
            'url' => $authUser->can('teacher-create') ? route('teachers.index') : null];
    }
    if (isset($student)) {
        $stats[] = ['label' => 'Students', 'caption' => 'Total students', 'value' => $student, 'icon' => 'fa-graduation-cap', 'tint' => 'green',
            'url' => $authUser->canany(['student-list', 'class-teacher']) ? route('students.index') : null];
    }
    if (isset($schoolsCount)) {
        $stats[] = ['label' => 'Schools', 'caption' => 'Total schools', 'value' => $schoolsCount, 'icon' => 'fa-university', 'tint' => 'orange',
            'url' => $authUser->hasRole('Super Admin') ? route('school.index') : null];
    }

    // Shortcuts to existing admin pages, filtered by the same checks the sidebar uses.
    $canAnnounce = $authUser->can('announcement-create');
    $actions = [];
    if ($authUser->hasRole('Super Admin')) {
        $actions[] = ['title' => 'Add a school', 'text' => 'Register a new school', 'icon' => 'fa-university', 'tint' => 'orange', 'url' => route('school.index')];
    }
    if ($authUser->canany(['create-principal', 'principal-list'])) {
        $actions[] = ['title' => 'Add a principal', 'text' => 'Invite a school leader', 'icon' => 'fa-user-circle', 'tint' => 'indigo', 'url' => route('principal.index')];
    }
    if ($canAnnounce) {
        $actions[] = ['title' => 'New announcement', 'text' => 'Post to the noticeboard', 'icon' => 'fa-bullhorn', 'tint' => 'blue', 'url' => route('announcement.index')];
    }
    if ($authUser->hasAnyRole(['Super Admin', 'Principal'])) {
        $actions[] = ['title' => 'Send a notification', 'text' => 'Push a message to the app', 'icon' => 'fa-bell', 'tint' => 'pink', 'url' => route('push-notifications')];
    }
    if ($authUser->can('parents-create')) {
        $actions[] = ['title' => 'Export parents', 'text' => 'Download the parent list', 'icon' => 'fa-file-excel-o', 'tint' => 'green', 'url' => route('parents.index')];
    }

    $teacherList = $teachers ?? collect();
    $teacherPreview = $teacherList->take(6);
    $hasGender = $boys || $girls;
@endphp

@section('content')
<div class="content-wrapper lm-dashboard">

    {{-- Welcome banner --}}
    <header class="lm-welcome">
        <div class="lm-welcome__text">
            <p class="lm-welcome__eyebrow">Welcome back,</p>
            <h1 class="lm-welcome__title">{{ ucwords(trim($authUser->first_name.' '.$authUser->last_name)) }}!</h1>
            <p class="lm-welcome__sub">Here’s what’s happening across Parent Teacher Mobile today.</p>
        </div>
        <p class="lm-welcome__tagline" aria-hidden="true">
            <span>Together</span>
            <span>Brighter Futures</span>
            <i class="fa fa-heart-o"></i>
        </p>
        <div class="lm-welcome__art" aria-hidden="true">
            @if(file_exists(public_path('assets/images/dashboard-hero.jpg')))
                {{-- Drop a photo at public/assets/images/dashboard-hero.jpg to replace the illustration --}}
                <img src="{{ asset('assets/images/dashboard-hero.jpg') }}" alt="">
            @else
                @include('workspace.hero-art')
            @endif
        </div>
    </header>

    {{-- Stats --}}
    @if(count($stats))
    <div class="lm-kpis lm-kpis--{{ count($stats) }}">
        @foreach($stats as $stat)
            @php $tag = $stat['url'] ? 'a' : 'div'; @endphp
            <{{ $tag }} class="lm-kpi lm-kpi--{{ $stat['tint'] }}" @if($stat['url']) href="{{ $stat['url'] }}" @endif>
                <span class="lm-kpi__icon"><i class="fa {{ $stat['icon'] }}"></i></span>
                <span class="lm-kpi__body">
                    <span class="lm-kpi__label">{{ $stat['label'] }}</span>
                    <span class="lm-kpi__value">{{ number_format((int) $stat['value']) }}</span>
                    <span class="lm-kpi__caption">{{ $stat['caption'] }}</span>
                </span>
            </{{ $tag }}>
        @endforeach
    </div>
    @endif

    <div class="lm-layout">
        <div class="lm-layout__main">

            {{-- Teachers --}}
            @if(isset($teachers))
            <section class="card lm-panel">
                <div class="lm-panel__head">
                    <div>
                        <h4 class="lm-panel__title">{{ __('teacher') }}s <span class="lm-count">{{ $teacherList->count() }}</span></h4>
                        <p class="lm-panel__sub">Active, verified teacher accounts</p>
                    </div>
                    @can('teacher-create')
                        <a href="{{ route('teachers.index') }}" class="lm-panel__link">View all<i class="fa fa-angle-right"></i></a>
                    @endcan
                </div>
                @if($teacherPreview->count())
                    <ul class="lm-list">
                        @foreach($teacherPreview as $row)
                            @php
                                $first = $row->user->first_name ?? '';
                                $last = $row->user->last_name ?? '';
                            @endphp
                            <li class="lm-list__item">
                                <span class="lm-avatar lm-avatar--lg">
                                    {{ mb_substr($first, 0, 1) }}{{ mb_substr($last, 0, 1) }}
                                    @if(!empty($row->user->image))
                                        <img src="{{ $row->user->image }}" alt="" loading="lazy" onerror="this.remove()">
                                    @endif
                                </span>
                                <span class="lm-list__text">
                                    <span class="lm-list__title">{{ trim($first.' '.$last) }}</span>
                                    <span class="lm-list__meta">{{ $row->qualification ?: 'No qualification listed' }}</span>
                                </span>
                                <span class="badge badge-success">Active</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="lm-empty lm-empty--compact">
                        <span class="lm-empty__icon"><i class="fa fa-user"></i></span>
                        <div>
                            <p class="lm-empty__title">No teachers yet</p>
                            <p class="lm-empty__text">Teachers appear here once they sign up and verify their email.</p>
                        </div>
                    </div>
                @endif
            </section>
            @endif

            {{-- Noticeboard --}}
            <section class="card lm-panel">
                <div class="lm-panel__head">
                    <div>
                        <h4 class="lm-panel__title">{{ __('noticeboard') }}</h4>
                        <p class="lm-panel__sub">Latest announcements</p>
                    </div>
                    @if($canAnnounce)
                        <a href="{{ route('announcement.index') }}" class="lm-panel__link">View all<i class="fa fa-angle-right"></i></a>
                    @endif
                </div>
                @if(count($announcement))
                    <ul class="lm-feed">
                        @foreach($announcement as $row)
                            <li class="lm-feed__item">
                                <span class="lm-feed__icon lm-tint-indigo"><i class="fa fa-bullhorn"></i></span>
                                <div class="lm-feed__body">
                                    <div class="lm-feed__top">
                                        <span class="lm-feed__title">{{ $row->title }}</span>
                                        <span class="lm-feed__date">{{ $row->created_at_for_web }}</span>
                                    </div>
                                    <p class="lm-feed__text">{{ $row->description }}</p>
                                    @hasrole(['Super Admin'])
                                        <span class="badge {{ $row->school ? 'badge-secondary' : 'badge-primary' }}">
                                            {{ $row->school ? $row->school->school_name : 'All schools' }}
                                        </span>
                                    @endrole
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="lm-empty lm-empty--compact">
                        <span class="lm-empty__icon"><i class="fa fa-bullhorn"></i></span>
                        <div>
                            <p class="lm-empty__title">No announcements yet</p>
                            <p class="lm-empty__text">Share news with parents and teachers across your schools.</p>
                        </div>
                        @if($canAnnounce)
                            <a href="{{ route('announcement.index') }}" class="btn btn-light btn-sm ml-auto">Create one</a>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        <aside class="lm-layout__side">

            {{-- Quick actions --}}
            @if(count($actions))
            <section class="card lm-panel">
                <div class="lm-panel__head">
                    <div>
                        <h4 class="lm-panel__title">Quick actions</h4>
                        <p class="lm-panel__sub">Common tasks, one click away</p>
                    </div>
                </div>
                <ul class="lm-actions">
                    @foreach($actions as $action)
                        <li>
                            <a href="{{ $action['url'] }}" class="lm-action">
                                <span class="lm-action__icon lm-tint-{{ $action['tint'] }}"><i class="fa {{ $action['icon'] }}"></i></span>
                                <span class="lm-action__text">
                                    <span class="lm-action__title">{{ $action['title'] }}</span>
                                    <span class="lm-action__sub">{{ $action['text'] }}</span>
                                </span>
                                <i class="fa fa-angle-right lm-action__chev"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
            @endif

            {{-- Student gender split --}}
            <section class="card lm-panel">
                <div class="lm-panel__head">
                    <div>
                        <h4 class="lm-panel__title">Students by gender</h4>
                        <p class="lm-panel__sub">Share of enrolled students</p>
                    </div>
                </div>
                @if($hasGender)
                    <div class="lm-donut">
                        <canvas id="gender-ratio-chart"></canvas>
                        <div class="lm-donut__center">
                            <span class="lm-donut__value">{{ number_format((int) $student) }}</span>
                            <span class="lm-donut__label">{{ __('student') }}s</span>
                        </div>
                    </div>
                    <ul class="lm-legend">
                        <li><span class="lm-legend__dot" style="background:#5856D6"></span>{{ __('male') }}<span class="lm-legend__value">{{ $boys }}%</span></li>
                        <li><span class="lm-legend__dot" style="background:#FF2D55"></span>{{ __('female') }}<span class="lm-legend__value">{{ $girls }}%</span></li>
                    </ul>
                @else
                    <div class="lm-empty lm-empty--compact">
                        <span class="lm-empty__icon"><i class="fa fa-pie-chart"></i></span>
                        <div>
                            <p class="lm-empty__title">No student data yet</p>
                            <p class="lm-empty__text">The split appears once students are enrolled in a class.</p>
                        </div>
                    </div>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
@section('script')
@if($hasGender)
<script>
    (function ($) {
        'use strict';
        $(function () {
            const canvas = document.getElementById('gender-ratio-chart');
            if (!canvas) return;
            new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ["{{ __('male') }}", "{{ __('female') }}"],
                    datasets: [{
                        data: [{{ $boys }}, {{ $girls }}],
                        backgroundColor: ['#5856D6', '#FF2D55'],
                        hoverBackgroundColor: ['#4846B0', '#E11D48'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 74,
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: 'rgba(29, 29, 31, 0.92)',
                        cornerRadius: 8,
                        xPadding: 10,
                        yPadding: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (item, data) {
                                return data.labels[item.index] + ': ' + data.datasets[0].data[item.index] + '%';
                            }
                        }
                    },
                    animation: { animateScale: false, animateRotate: true }
                }
            });
        });
    })(jQuery);
</script>
@endif
<script>


    const messagings = firebaseInit.messaging();
    messagings.usePublicVapidKey("BMF317nYWC_7EZ2EcldU5OS2IhGO1UE0yXWV7TgBZ1mekcXe5BTrckxBqRhiWIWzglBvWbtAWST1md1C5rTZiz4");

    function sendTokenToServer(fcm_token) {
        const user_id = '{{auth()->user()->id}}';
        axios.post('/save-token', {
            fcm_token, user_id
        })
            .then(res => {
                console.log(res);
            })
    }

    function retreiveToken() {
        messagings.getToken().then((currentToken) => {
            if (currentToken) {
                sendTokenToServer(currentToken);
            } else {

                alert('You should allow notification!');
            }
        }).catch((err) => {
            console.log(err.message);
        });
    }

    retreiveToken();
    messagings.onTokenRefresh(() => {
        console.log('onTokenRefresh');
        retreiveToken();
    });

    messagings.onMessage((res) => {
        console.log('Message received');
    });

</script>
@endsection
