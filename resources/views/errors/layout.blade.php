<!doctype html>
<html lang="vi" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · TECHFORGE</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v=20261112-dark-premium">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v=20261112-dark-premium">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=20261112-dark-premium">
    <script src="{{ asset('js/site.js') }}?v=20261016" defer></script>
</head>

<body>
    <main id="main-content" tabindex="-1" class="container py-5">
        <div class="summary-card mx-auto" style="max-width:650px">
            <p class="eyebrow">TECHFORGE · @yield('code')</p>
            <h1 class="h3">@yield('message')</h1>
            <p class="text-secondary mt-3">@yield('hint')</p><a class="btn btn-dark" href="/">Về trang chủ</a>
        </div>
    </main>
</body>

</html>
