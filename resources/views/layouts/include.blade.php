@php
    $lang = Session::get('language');
@endphp
<link rel="stylesheet" href="{{ asset('/assets/css/vendor.bundle.base.css') }}" async>

<link rel="stylesheet" href="{{ asset('/assets/fonts/font-awesome.min.css') }}" async/>
<link rel="stylesheet" href="{{ asset('/assets/select2/select2.min.css') }}" async>
<link rel="stylesheet" href="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/color-picker/color.min.css') }}" async>
@if ($lang)
    @if ($lang->is_rtl)
        <link rel="stylesheet" href="{{ asset('/assets/css/rtl.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('/assets/css/style.css') }}">
    @endif
@else
    <link rel="stylesheet" href="{{ asset('/assets/css/style.css') }}">
@endif
<link rel="stylesheet" href="{{ asset('/assets/css/datepicker.min.css') }}" async>
<link rel="stylesheet" href="{{ asset('/assets/css/ekko-lightbox.css') }}">

<link rel="stylesheet" href="{{ asset('/assets/bootstrap-table/bootstrap-table.min.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/bootstrap-table/fixed-columns.min.css') }}">

{{-- <link rel="shortcut icon" href="{{asset(config('global.LOGO_SM')) }}" /> --}}
<link rel="shortcut icon" href="{{ url(Storage::url(config('environment.FAVICON'))) }}"/>
<link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon.ico') }}">

@php
    $theme_color = getSettings('theme_color');
    // echo json_encode($theme_color);
    $theme_color = $theme_color['theme_color'];
@endphp
<style>
    :root {
        --theme-color: <?=$theme_color ?>;
    }
</style>
{{-- Lumen theme: loads last so it overrides the template. Its tokens also replace --theme-color. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Dancing+Script:wght@600&display=swap">
<link rel="stylesheet" href="{{ asset('/assets/css/lumen-theme.css') }}?v={{ @filemtime(public_path('assets/css/lumen-theme.css')) }}">
<script>
    var baseUrl = "{{ URL::to('/') }}";
    const onErrorImage = (e) => {
        // e.target.src = "{{ asset('/storage/no_image_available.jpg') }}";
    };
</script>
@yield('scripts')

<link rel="stylesheet" href="{{ asset('assets/css/colourful-portals.css') }}?v={{ filemtime(public_path('assets/css/colourful-portals.css')) }}">

<link rel="stylesheet" href="{{ asset('assets/css/portal-ui.css') }}?v={{ filemtime(public_path('assets/css/portal-ui.css')) }}">
