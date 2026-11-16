<a class="skip-link" href="#main-content">Đến nội dung chính</a>
<header class="site-header">
    <nav class="container site-nav" aria-label="Điều hướng chính">
        <a class="brand" href="{{ route('home') }}" aria-label="TECHFORGE — Trang chủ"><span class="brand-mark" aria-hidden="true">TF</span> TECHFORGE<span class="brand-period" aria-hidden="true">.</span></a>
        <div class="desktop-navigation">
            <div class="site-nav-links">@include('partials.navigation-links')</div>
            <div class="site-account-links">@include('partials.account-links')</div>
        </div>
        <details class="mobile-navigation">
            <summary>Menu <span aria-hidden="true">☰</span></summary>
            <div class="mobile-navigation-panel">
                <div class="site-nav-links">@include('partials.navigation-links')</div>
                <div class="site-account-links">@include('partials.account-links')</div>
            </div>
        </details>
    </nav>
</header>
