<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }} | Login</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon.ico') }}">
    @if(file_exists(public_path('site.webmanifest')))
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @endif

    @include('layouts.include')
    <style>
        /* Hide the /login portion from the URL in the address bar */
        body::after {
            content: "/";
            visibility: hidden;
        }
    </style>
</head>

<body class="lm-auth">
<main class="lm-auth__wrap">
    <div class="lm-auth__card">
        <div class="lm-auth__brand">
            <span class="lm-brand__mark"><i class="fa fa-graduation-cap"></i></span>
            <span class="lm-brand__text">
                <span class="lm-brand__name">Parent Teacher</span>
                <span class="lm-brand__tag">Mobile</span>
            </span>
        </div>

        @if(session('workspace_success'))<p role="status">{{ session('workspace_success') }}</p>@endif
        <h1 class="lm-auth__title">Sign in</h1>
        <p class="lm-auth__sub">Administrative web access: Super Admin, Sub-Super Admin, District Admin and School Admin. Parents and teachers use the mobile app. Staff require explicit web authorization.</p>

        <form action="{{ route('loginWeb') }}" id="frmLogin" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">{{ __('email') }}</label>
                <input id="email" type="email" class="form-control form-control-lg" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="name@school.com">
            </div>
            <input type="hidden" name="timezone_offset" id="timezone_offset">

            <div class="form-group">
                <div class="d-flex justify-content-between align-items-baseline">
                    <label for="password">{{ __('password') }}</label>
                    @if (Route::has('password.request'))
                        <a class="lm-auth__link" href="{{ route('password.request') }}">{{ __('forgot_password') }}</a>
                    @endif
                </div>
                <div class="lm-auth__password">
                    <input id="password" type="password" class="form-control form-control-lg" name="password" required autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="lm-auth__eye" aria-label="Show password">
                        <i class="fa fa-eye-slash" id="togglePassword"></i>
                    </button>
                </div>
            </div>

            <input type="submit" name="btnlogin" id="login_btn" value="{{ __('login') }}" class="btn btn-block btn-theme btn-lg mt-4"/>
        </form>
        @if(config('environment.DEMO_MODE'))
            <div class="lm-auth__demo">
                <span>Demo credentials</span>
                <div class="d-flex" style="gap: 8px">
                    <button class="btn btn-light flex-fill" id="superadmin_btn">Super Admin</button>

                </div>
            </div>
        @endif
    </div>
    <p class="lm-auth__foot">© {{ date('Y') }} Parent Teacher Mobile</p>
</main>

<script src="{{ asset('/assets/js/vendor.bundle.base.js') }}"></script>
<script src="{{ asset('/assets/js/jquery.validate.min.js') }}"></script>
<script src="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.js') }}"></script>

<script type='text/javascript'>
    $(document).ready(function() {
            var current_date = new Date();
            curent_zone = -current_date.getTimezoneOffset() * 60;
            document.getElementById('timezone_offset').value = curent_zone;

    });
        // Listen for the DOMContentLoaded event
    document.addEventListener("DOMContentLoaded", function() {
        // Check if the current URL is '/login'

    });
    $("#frmLogin").validate({
        rules: {
            username: "required",
            password: "required",
        },
        success: function(label, element) {
            $(element).parent().removeClass('has-danger')
            $(element).removeClass('form-control-danger')
        },
        errorPlacement: function (label, element) {
            if ($(element).attr("name") == "password"){
                label.insertAfter(element.parent()).addClass('text-danger mt-2');
            }else{
                label.addClass('mt-2 text-danger');
                label.insertAfter(element);
            }
        },
        highlight: function (element, errorClass) {
            $(element).parent().addClass('has-danger')
            $(element).addClass('form-control-danger')
        }
    });

    const togglePassword = document.querySelector(".lm-auth__eye");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        // this.classList.toggle("fa-eye");
        if (password.getAttribute("type") === 'password') {
            $('#togglePassword').addClass('fa-eye-slash');
            $('#togglePassword').removeClass('fa-eye');
        } else {
            $('#togglePassword').removeClass('fa-eye-slash');
            $('#togglePassword').addClass('fa-eye');
        }
    });

    @if(config('environment.DEMO_MODE'))
    $('#superadmin_btn').on('click', function (e) {
        $('#email').val('superadmin@gmail.com');
        $('#password').val('superadmin');
        $('#login_btn').attr('disabled', true);
        $(this).attr('disabled', true);
        $('#frmLogin').submit();
    })
    @endif

</script>
</body>

@if (Session::has('error'))
    <script type='text/javascript'>
        $.toast({
            text: '{{ Session::get('error') }}',
            showHideTransition: 'slide',
            icon: 'error',
            loaderBg: '#f2a654',
            position: 'top-right'
        });
    </script>
@endif

@if ($errors->any())
    @foreach ($errors->all() as $error)
        <script type='text/javascript'>
            $.toast({
                text: '{{ $error }}',
                showHideTransition: 'slide',
                icon: 'error',
                loaderBg: '#f2a654',
                position: 'top-right'
            });
        </script>
    @endforeach
@endif

</html>
