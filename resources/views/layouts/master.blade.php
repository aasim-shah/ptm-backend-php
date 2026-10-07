<!DOCTYPE html>
@php
    $lang = Session::get('language');
@endphp
@if($lang)
    @if ($lang->is_rtl)
        <html lang="en" dir="rtl">
    @else
        <html lang="en">
    @endif
@else
    <html lang="en">
@endif
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title') || {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.include')
    @yield('css')
    <script src="https://www.gstatic.com/firebasejs/9.0.2/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.0.2/firebase-firestore-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.0.2/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/7.20.0/firebase-messaging.js"></script>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon.ico') }}">

    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyAwYUCDNT-2HGqN0eYvS2N_C-8wn7fWbwE",
            authDomain: "new-ptm-app.firebaseapp.com",
            projectId: "new-ptm-app",
            storageBucket: "new-ptm-app.appspot.com",
            messagingSenderId: "876353677089",
            appId: "1:876353677089:web:a013a654db20239e034060",
            measurementId: "G-KV7EDT5SGC"
        };

        const firebaseInit =  firebase.initializeApp(firebaseConfig);
        var meta = document.createElement('meta');
        meta.httpEquiv = "Content-Security-Policy";
        meta.content = "default-src gap://ready file://* *; style-src 'self' http://* https://* 'unsafe-inline'; script-src 'self' http://* https://* 'unsafe-inline' 'unsafe-eval'";
        document.getElementsByTagName('head')[0].appendChild(meta);

    </script>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

</head>
<body class="sidebar-fixed">
<div class="container-scroller">

    {{-- header --}}
    @include('layouts.header')

    <div class="container-fluid page-body-wrapper">

        {{-- siderbar --}}
        @include('layouts.sidebar')

        <div class="main-panel">

            @yield('content')

            {{-- footer --}}
            @include('layouts.footer')

        </div>

    </div>

</div>

@include('layouts.footer_js')

{{-- After Update Notes Modal --}}
@include('after-update-note-modal')


@yield('js')

@yield('script')

</body>

</html>
