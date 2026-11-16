@extends('layouts.app')
@section('title', 'Kiến thức, linh kiện & PC Builder')
@section('meta_description', 'Tìm hiểu kiến thức phần cứng, khám phá linh kiện và tự xây dựng cấu hình PC cùng TECHFORGE.')
@section('body_class', 'news-home')
@section('main_class', 'news-home-main')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/news-home.css') }}?v=20261112-dark-premium">
@endpush
@push('scripts')
<script src="{{ asset('js/news-home.js') }}?v=20261026" defer></script>
@endpush
@section('content')
<header class="news-masthead home-hero">
    <div><p class="news-kicker">TECHFORGE / HIỂU ĐỂ CHỌN, CHỌN ĐỂ XÂY DỰNG</p><h1>Hiểu công nghệ.<br><em>Tự tin xây dựng PC.</em></h1></div>
    <div class="home-hero-intro"><p>Tìm hiểu kiến thức phần cứng, khám phá linh kiện và tự xây dựng cấu hình máy tính phù hợp với nhu cầu của bạn.</p>
        <div class="home-hero-actions"><a class="home-cta home-cta-primary" href="{{ route('builder.index') }}">Bắt đầu xây dựng PC <span aria-hidden="true">→</span></a><a class="home-cta home-cta-secondary" href="{{ route('products.index') }}">Khám phá linh kiện <span aria-hidden="true">→</span></a></div>
    </div>
</header>
<nav class="home-paths" aria-label="Ba hành trình TECHFORGE">
    <a href="{{ route('articles.index') }}"><span class="home-path-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h6a3 3 0 0 1 3 3v14a3 3 0 0 0-3-3H3zM21 4h-6a3 3 0 0 0-3 3v14a3 3 0 0 1 3-3h6z"/></svg></span><span><strong>Tìm hiểu kiến thức</strong><small>Bài viết & hướng dẫn phần cứng</small></span><span aria-hidden="true">→</span></a>
    <a href="{{ route('products.index') }}"><span class="home-path-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4m6-4v4M9 18v4m6-4v4M2 9h4m-4 6h4m12-6h4m-4 6h4"/></svg></span><span><strong>Khám phá linh kiện</strong><small>Tra cứu thông số trước khi chọn</small></span><span aria-hidden="true">→</span></a>
    <a href="{{ route('builder.index') }}"><span class="home-path-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="2" width="12" height="20" rx="2"/><circle cx="12" cy="7" r="1"/><path d="M9 13h6m-6 4h6"/></svg></span><span><strong>Xây dựng PC</strong><small>Lựa chọn, kiểm tra & lưu cấu hình</small></span><span aria-hidden="true">→</span></a>
</nav>
<nav class="news-topics" aria-label="Chuyên mục tin công nghệ">
    <a href="{{ route('home') }}" aria-current="page">Nổi bật ↘</a>
    @foreach(\App\Models\Article::CATEGORIES as $category)
        <a href="{{ route('articles.index', ['category' => $category]) }}">{{ $category }}</a>
    @endforeach
    <button type="button" class="news-motion" aria-pressed="true" hidden>Chuyển động: Bật</button>
</nav>
@if($featured->isNotEmpty())
<section @class(['news-front', 'news-front--single' => $featured->count() === 1]) aria-label="Bài nổi bật">
    @foreach($featured as $article)
        @if($loop->first)
        <article class="news-lead">
            <a class="news-lead-art" href="{{ route('articles.show', $article->slug) }}" aria-label="Đọc: {{ $article->title }}"><x-news-art :article="$article" :priority="true" /></a>
            <div class="news-lead-copy"><div class="news-meta"><span class="news-tag">TIÊU ĐIỂM</span><span>{{ $article->category }}</span><time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d.m.Y') }}</time></div>
            <h2><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h2>
            <p>{{ $article->excerpt }}</p><a class="news-read" href="{{ route('articles.show', $article->slug) }}">Đọc câu chuyện <span>→</span></a></div>
        </article>
        @if($featured->count() > 1)<div class="news-side"><div class="news-section-label"><span>ĐÁNG CHÚ Ý</span><span>↗</span></div>@endif
        @else
            <article class="news-teaser"><a class="news-teaser-art" href="{{ route('articles.show', $article->slug) }}" aria-label="Đọc: {{ $article->title }}"><x-news-art :article="$article" /></a>
            <div class="news-meta"><span>{{ $article->category }}</span><time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d.m.Y') }}</time></div>
            <h2><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h2></article>
        @endif
        @if($loop->last && $featured->count() > 1)</div>@endif
    @endforeach
</section>
@endif
<div class="home-tools">
    <section class="home-builder" aria-labelledby="home-builder-title">
        <p class="news-kicker">TECHFORGE / PC BUILDER</p>
        <h2 id="home-builder-title">PC của bạn.<br><em>Cấu hình của bạn.</em></h2>
        <p>Chọn linh kiện để xây dựng cấu hình PC phù hợp với nhu cầu và ngân sách.</p>
        <a class="home-cta home-cta-primary" href="{{ route('builder.index') }}">Mở PC Builder <span aria-hidden="true">→</span></a>
        <p class="home-builder-note">Đã có linh kiện? Chọn đúng mã trong danh mục rồi đánh dấu “Tôi đã có” ở Builder.</p>
    </section>
    <section class="home-components" id="home-components" aria-labelledby="home-components-title">
        <div class="home-components-heading"><div><p class="news-kicker">TỪNG CHI TIẾT. MỘT CẤU HÌNH.</p><h2 id="home-components-title">Khám phá linh kiện</h2></div><a class="news-read" href="{{ route('products.index') }}">Tất cả <span aria-hidden="true">→</span></a></div>
        <div class="home-component-grid">
            @foreach(\App\Services\ProductSpecificationSchema::TYPES as $type => $label)
                <a data-component-type="{{ $type }}" href="{{ route('products.index', ['type' => $type]) }}"><img src="{{ asset('images/components/'.$type.'.svg') }}" alt="" width="52" height="52" loading="lazy"><span>{{ $type === 'psu' ? 'PSU' : $label }}</span></a>
            @endforeach
        </div>
    </section>
</div>
@if($latest->isNotEmpty())
<section class="news-latest" aria-labelledby="news-latest-title">
    <div class="news-section-heading"><div><p class="news-kicker">ĐỌC CHẬM. HIỂU SÂU.</p><h2 id="news-latest-title">Góc nhìn & kiến thức</h2></div><a class="news-read" href="{{ route('articles.index') }}">Tất cả bài viết →</a></div>
    <div class="news-feed">
    @foreach($latest as $article)
        <article class="news-feed-item"><a class="news-feed-art" href="{{ route('articles.show', $article->slug) }}" aria-label="Đọc: {{ $article->title }}"><x-news-art :article="$article" /></a>
        <div><div class="news-meta"><span>{{ $article->category }}</span><time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d.m.Y') }}</time></div><h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h3><p>{{ $article->excerpt }}</p><a class="news-read" href="{{ route('articles.show', $article->slug) }}">Đọc tiếp →</a></div></article>
    @endforeach
    </div>
</section>
@elseif($articleCount === 0)
<section class="news-empty" role="status"><h2>Kiến thức PC</h2><p>Chưa có bài viết được công khai. Bạn có thể tra cứu linh kiện hoặc mở PC Builder từ các lựa chọn phía trên.</p></section>
@endif
<p class="news-source-note">{{ $articleCount }} bài đang công khai · Nguồn đối chiếu được ghi trong từng bài.</p>
@endsection
