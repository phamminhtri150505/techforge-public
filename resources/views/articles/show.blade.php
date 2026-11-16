@extends('layouts.app')
@section('title', $article->title)
@section('meta_description', \Illuminate\Support\Str::limit($article->excerpt, 160))
@php
    $art = config('news_art.'.$article->slug);
    $photo = $art['image'] ?? null;
    $shareImage = is_string($photo) && isset($art['owner']) && str_starts_with($photo, 'images/products/')
        && !str_contains($photo, '..') && is_file(public_path($photo)) ? asset($photo) : null;
    $structuredData = [
        '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title,
        'description' => $article->excerpt, 'mainEntityOfPage' => route('articles.show', $article->slug),
        'articleSection' => $article->category, 'inLanguage' => 'vi',
        'publisher' => ['@type' => 'Organization', 'name' => 'TECHFORGE'],
    ];
    // Chưa có published_at/tác giả: không suy ngày xuất bản hoặc tên người viết.
    if ($article->created_at) $structuredData['dateCreated'] = $article->created_at->toIso8601String();
    if ($article->updated_at) $structuredData['dateModified'] = $article->updated_at->toIso8601String();
    if ($shareImage) $structuredData['image'] = $shareImage;
@endphp
@push('metadata')
    @if($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:alt" content="Hình ảnh cho {{ $article->title }}">
    @endif
    <script type="application/ld+json">{!! \Illuminate\Support\Js::encode($structuredData) !!}</script>
@endpush
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/news-art.css') }}?v=20261028">
    <link rel="stylesheet" href="{{ asset('css/article-detail.css') }}?v=20261112-dark-premium">
@endpush
@push('scripts')
    <script src="{{ asset('js/article-detail.js') }}?v=20261028" defer></script>
@endpush
@section('content')
<article class="article-detail">
    <nav class="article-breadcrumb" aria-label="Đường dẫn bài viết">
        <ol><li><a href="{{ route('home') }}">Trang chủ</a></li><li><a href="{{ route('articles.index') }}">Góc công nghệ</a></li><li aria-current="page">{{ $article->title }}</li></ol>
    </nav>
    <header class="article-heading">
        <a class="eyebrow" href="{{ route('articles.index', ['category' => $article->category]) }}">{{ $article->category }}</a>
        <h1>{{ $article->title }}</h1>
        <p class="article-deck">{{ $article->excerpt }}</p>
        <div class="article-meta">
            @if($article->created_at)
                <span>Ngày tạo <time datetime="{{ $article->created_at->toIso8601String() }}">{{ $article->created_at->format('d.m.Y') }}</time></span>
            @endif
            @if($article->updated_at && $article->created_at && $article->updated_at->gt($article->created_at))
                <span>Cập nhật <time datetime="{{ $article->updated_at->toIso8601String() }}">{{ $article->updated_at->format('d.m.Y') }}</time></span>
            @endif
            @if($reading['reading_minutes'])
                <span title="Ước lượng theo số đơn vị cách nhau bằng khoảng trắng trong nội dung">Khoảng {{ $reading['reading_minutes'] }} phút đọc</span>
            @endif
        </div>
    </header>
    <div class="article-detail-art"><x-news-art :article="$article" :priority="true" /></div>
    <div class="article-reading-layout {{ $reading['toc'] ? 'article-reading-layout--toc' : '' }}">
        @if($reading['toc'])
            <aside class="article-outline">
                <details open>
                    <summary>Trong bài viết này</summary>
                    <nav aria-label="Mục lục bài viết"><ol>
                        @foreach($reading['toc'] as $heading)
                            <li class="toc-level-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a></li>
                        @endforeach
                    </ol></nav>
                </details>
            </aside>
        @endif
        <div class="article-reading-column">
            <div class="article-prose">{!! $reading['html'] !!}</div>
            <div class="article-provenance"><strong>Nguồn biên soạn</strong><p>{{ $article->data_source }}</p></div>
            @if($requirements)
                @include('articles.practice')
            @elseif($article->requirements)
                <p class="scope-note">Yêu cầu thực hành của bài đang cần kiểm tra lại. Bạn vẫn có thể <a href="{{ route('builder.index') }}">mở PC Builder</a> và nhập nhu cầu của mình.</p>
            @endif
            <a class="article-back" href="{{ route('articles.index') }}">← Trở về Góc công nghệ</a>
        </div>
    </div>
    @if($related->isNotEmpty())
        <section class="article-related" aria-labelledby="related-title">
            <div class="article-related-heading"><div><p class="eyebrow">Cùng chuyên mục {{ $article->category }}</p><h2 id="related-title">Đọc tiếp về chủ đề này</h2></div><a href="{{ route('articles.index', ['category' => $article->category]) }}">Xem chuyên mục →</a></div>
            <div class="article-related-grid">
                @foreach($related as $item)
                    <article class="article-related-card">
                        <a href="{{ route('articles.show', $item->slug) }}" tabindex="-1" aria-hidden="true"><div class="article-related-art"><x-news-art :article="$item" /></div></a>
                        <h3><a href="{{ route('articles.show', $item->slug) }}">{{ $item->title }}</a></h3>
                        <p>{{ $item->excerpt }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
