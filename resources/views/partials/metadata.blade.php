@php
    $pageTitle = trim($__env->yieldContent('title', 'TECHFORGE'));
    $pageTitle = $pageTitle === 'TECHFORGE' ? $pageTitle : $pageTitle.' · TECHFORGE';
    $pageDescription = trim($__env->yieldContent('meta_description', 'Đọc tin công nghệ, tra cứu linh kiện, kiểm tra tương thích và đối chiếu chi phí cấu hình PC với ngân sách.'));
    $isPublicPage = request()->routeIs('home', 'articles.index', 'articles.show', 'products.index', 'products.show', 'reference-builds.index');
    // Giữ trang phân trang/chuyên mục/bộ lọc khác nhau; bỏ tham số theo dõi không liên quan.
    $canonicalParameters = request()->routeIs('articles.index') ? request()->only(['q', 'category', 'page'])
        : (request()->routeIs('products.index') ? request()->only(['q', 'type', 'brand', 'chip_vendor', 'min_price', 'max_price', 'availability', 'sort', 'demo', 'page']) : []);
    $canonicalParameters = array_filter($canonicalParameters, fn ($value) => $value !== null && $value !== '');
    ksort($canonicalParameters);
    $canonicalUrl = url()->current().($canonicalParameters ? '?'.http_build_query($canonicalParameters) : '');
@endphp
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
@if($isPublicPage)
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="TECHFORGE">
    <meta property="og:type" content="{{ request()->routeIs('articles.show') ? 'article' : 'website' }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @stack('metadata')
@else
    <meta name="robots" content="noindex, nofollow">
@endif
