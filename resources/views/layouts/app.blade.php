<!doctype html>
<html lang="vi" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.metadata')
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v=20261114-photos">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v=20261114-photos">
    <script src="{{ asset('js/site.js') }}?v=20261109" defer></script>
    @stack('scripts')
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=20261112-dark-premium">
</head>

<body class="@yield('body_class')">
    @include('partials.nav')
    @if (session('rate_limit_notice'))
        <div class="rate-limit-notice" data-rate-limit-notice>
            <div role="status">
                <strong>Bạn đã thao tác quá nhanh.</strong>
                <p class="mb-0">Hãy đợi một lát rồi thử lại.</p>
            </div>
            <button type="button" class="btn-close" aria-label="Đóng thông báo" hidden></button>
        </div>
    @endif
    <main id="main-content" tabindex="-1" class="container @yield('main_class', 'py-4')">
        @unless(View::hasSection('custom_notices'))
        @include('partials.notices')
        @endunless
        @yield('content')
    </main>
    @include('partials.footer')
</body>

</html>
