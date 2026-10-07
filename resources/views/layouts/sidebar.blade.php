<!-- partial:../../partials/_sidebar.html -->
<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        {{-- dashboard --}}
        <li class="nav-item {{ request()->is('/') || request()->is('home') || url()->current() == route('login') ? 'active' : '' }}">
            <a href="{{ url('/') }}" class="nav-link"> <span class="menu-title">{{ __('dashboard') }}</span> <i
                    class="fa fa-dashboard menu-icon"></i> </a>
        </li>



        {{-- parents --}}
        @can('parents-create')
        <li class="nav-item {{ request()->is('chat-parent/*') ? 'active' : '' }}">
            <a href="{{ route('parents.index') }}" class="nav-link"> <span
                        class="menu-title">{{ __('parents') }}</span> <i class="fa fa-users menu-icon"></i> </a>
        </li>
        @endcan

        {{-- teacher --}}
        @can('teacher-create')
            <li class="nav-item {{ request()->is('chat-teacher/*') ? 'active' : '' }}">
                <a href="{{ route('teachers.index') }}" class="nav-link"> <span
                        class="menu-title">Teachers</span> <i class="fa fa-user menu-icon"></i> </a>
            </li>
        @endcan
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#student-menu" aria-expanded="false"
               aria-controls="academics-menu"> <span class="menu-title">{{ __('students') }}</span>

                <i class="fa fa-graduation-cap menu-icon"></i> </a>
            <div class="collapse" id="student-menu">
                <ul class="nav flex-column sub-menu">

                    @canany(['student-list', 'class-teacher'])

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('students.index') }}">
                            {{ __('student_details') }}
                        </a>
                    </li>
                    @endcanany

                </ul>
            </div>
        </li>

        @hasrole(['Super Admin'])
        <li class="nav-item">
            <a class="nav-link" href="{{ route('school.index') }}"> <span
                        class="menu-title">{{ __('schools') }}</span> <i class="fa fa-university menu-icon"></i> </a>
        </li>

        @endrole

        {{-- principal --}}
        @canany(['create-principal','principal-list'])
        <li class="nav-item">
            <a href="{{ route('principal.index') }}" class="nav-link"> <span
                        class="menu-title">Principals</span> <i class="fa fa-user-circle menu-icon"></i> </a>
        </li>
        @endcan

        @hasrole(['Super Admin','Principal'])
        <li class="nav-item">
            <a class="nav-link" href="{{ route('push-notifications') }}"> <span
                        class="menu-title">Push Notifications</span> <i class="fa fa-bell menu-icon"></i> </a>
        </li>
        @endrole
        @hasrole(['Principal'])

        <li class="nav-item">
            <a class="nav-link" href="{{ route('class.index') }}"> <span
                        class="menu-title">Manage Class</span> <i class="fa fa-users menu-icon"></i> </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('subject.index') }}"> <span
                        class="menu-title">Manage Subject</span> <i class="fa fa-book menu-icon"></i> </a>
        </li>

         <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#chat-menu" aria-expanded="false"
               aria-controls="academics-menu"> <span class="menu-title">{{ __('chat') }}</span>

                <i class="fa fa-mail-forward menu-icon"></i> </a>
            <div class="collapse" id="chat-menu">
                <ul class="nav flex-column sub-menu">

                <li class="nav-item">
                    <a href="{{ route('t_chat',null) }}" class="nav-link"> <span
                                class="menu-title">{{ __('t_chat') }}</span> <i class="fa fa-mail-forward menu-icon"></i> </a>
                </li>
                    <li class="nav-item">
                    <a href="{{ route('p_chat',null) }}" class="nav-link"> <span
                                class="menu-title">{{ __('p_chat') }}</span> <i class="fa fa-mail-forward menu-icon"></i> </a>
                </li>

                </ul>
            </div>
        </li>
        @endrole

        @hasrole(['Principal'])
         <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#calandar-menu" aria-expanded="false"
               aria-controls="academics-menu"> <span class="menu-title">Calendar</span>

                <i class="fa fa-calendar menu-icon"></i> </a>
            <div class="collapse" id="calandar-menu">
                <ul class="nav flex-column sub-menu">


                    <li class="nav-item">
                        <a href="{{ route('calendar',null) }}" class="nav-link"> <span
                                    class="menu-title">Calendar</span> <i class="fa fa-calendar menu-icon"></i> </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('meeting_list_view') }}" class="nav-link"> <span
                                    class="menu-title">Meetings</span> <i class="fa fa-list menu-icon"></i> </a>
                    </li>

                </ul>
            </div>
        </li>
        @endrole

        {{-- attendance --}}
        @canany(['class-teacher'])
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#attendance-menu" aria-expanded="false"
                    aria-controls="attendance-menu"> <span class="menu-title">{{ __('attendance') }}</span> <i
                        class="fa fa-check menu-icon"></i> </a>
                <div class="collapse" id="attendance-menu">
                    <ul class="nav flex-column sub-menu">
                        @can('attendance-create')
<!--                            <li class="nav-item">-->
<!--                                <a class="nav-link" href="{{ route('attendance.index') }}">-->
<!--                                    {{ __('add_attendance') }}-->
<!--                                </a>-->
<!--                            </li>-->
                        @endcan

                        {{-- view attendance --}}
                        @can('attendance-list')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.view') }}">
                                    {{ __('view_attendance') }}
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </li>
        @endcanany

        {{-- announceent --}}
        @can('announcement-create')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('announcement.index') }}">
                    <span class="menu-title">Announcements</span>
                    <i class="fa fa-check menu-icon"></i> </a>
            </li>
        @endcan


        {{-- settings --}}
        @if (Auth::user()->hasRole('Super Admin'))

        @canany(['setting-create', 'fcm-setting-create', 'email-setting-create', 'privacy-policy', 'contact-us',
            'about-us', 'role-create'])
            <li hidden class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#settings-menu" aria-expanded="false"
                    aria-controls="settings-menu"> <span class="menu-title">{{ __('system_settings') }}</span> <i
                        class="fa fa-cog menu-icon"></i> </a>
                <div class="collapse" id="settings-menu">
                    <ul class="nav flex-column sub-menu">
                        @can('setting-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('app-settings') }}">
                                    {{ __('app_settings') }}</a>
                            </li>
                        @endcan
                        @can('setting-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('settings') }}">
                                    {{ __('general_settings') }}</a>
                            </li>
                        @endcan
                        @can('language-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('language') }}">
                                    {{ __('language_settings') }}</a>
                            </li>
                        @endcan
                        @can('fcm-setting-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('fcm-settings') }}"> {{ __('fcm_key') }}
                                </a>
                            </li>
                        @endcan
                        @can('fees-config')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('fees.config.index') }}"> {{ __('fees') }}
                                    {{ __('configration') }}
                                </a>
                            </li>
                        @endcan
                        @can('email-setting-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('email-settings') }}">
                                    {{ __('email_configuration') }}
                                </a>
                            </li>
                        @endcan
                        @can('privacy-policy')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('privacy-policy') }}">
                                    {{ __('privacy_policy') }}
                                </a>
                            </li>
                        @endcan
                        @can('contact-us')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('contact-us') }}"> {{ __('contact_us') }}
                                </a>
                            </li>
                        @endcan
                        @can('about-us')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('about-us') }}"> {{ __('about_us') }}
                                </a>
                            </li>
                        @endcan
                        @can('terms-condition')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('terms-condition') }}">
                                    {{ __('terms_condition') }}
                                </a>
                            </li>
                        @endcan
                        @can('role-create')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('roles/') }}"> {{ __('role_permission') }}
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
        @endif

            @if (Auth::user()->hasRole('Super Admin'))
                <li hidden class="nav-item">
                    <a class="nav-link" href="{{ route('system-update.index') }}">
                        <span class="menu-title">{{ __('system_update') }}</span>
                        <i class="fa fa-cloud-download menu-icon"></i> </a>
                </li>
            @endif


        </ul>

    </nav>
