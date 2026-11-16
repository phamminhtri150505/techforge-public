@extends('layouts.app')
@section('title', 'Góc công nghệ'.($category ? ' — '.$category : '').($term !== '' ? ' — Tìm kiếm' : ''))
@section('meta_description', 'Khám phá kiến thức phần cứng, hướng dẫn lựa chọn linh kiện và những thông tin công nghệ giúp bạn xây dựng PC phù hợp hơn.')
@section('body_class', 'article-library')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/news-art.css') }}?v=20261028">
<link rel="stylesheet" href="{{ asset('css/article-library.css') }}?v=20261112-dark-premium">
@endpush
@section('content')
<header class="library-heading">
    <p class="eyebrow">ĐỌC ĐỂ CHỌN TỐT HƠN</p>
    <h1>Góc công nghệ<span aria-hidden="true">.</span></h1>
    <p>Khám phá kiến thức phần cứng, hướng dẫn lựa chọn linh kiện và những thông tin công nghệ giúp bạn xây dựng PC phù hợp hơn.</p>
</header>
<section class="library-controls" aria-label="Tìm và lọc bài viết">
    <form action="{{ route('articles.index') }}" method="get" role="search" class="library-search">
        @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
        <label for="article-search">Tìm bài viết</label>
        <div class="library-search-fields">
            <span class="library-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg></span>
            <input class="form-control" id="article-search" name="q" type="search" value="{{ $term }}" maxlength="100" placeholder="Tiêu đề, từ khóa hoặc chủ đề" @error('q') aria-invalid="true" aria-describedby="article-search-error" @enderror>
            <button class="btn btn-forge" type="submit">Tìm kiếm</button>
            @if($term !== '')<a class="library-clear" href="{{ route('articles.index', array_filter(['category' => $category])) }}">Xóa từ khóa</a>@endif
        </div>
        @error('q')<p id="article-search-error" class="text-danger mt-2">{{ $message }}</p>@enderror
    </form>
    <nav class="library-categories" aria-label="Chuyên mục bài viết">
        @foreach(['' => 'Tất cả'] + array_combine(\App\Models\Article::CATEGORIES, \App\Models\Article::CATEGORIES) as $value => $label)
            <a @class(['library-category', 'is-active' => ($category ?? '') === $value]) href="{{ route('articles.index', array_filter(['category' => $value, 'q' => $term], fn ($value) => $value !== '')) }}" @if(($category ?? '') === $value) aria-current="page" @endif>
                @if(($category ?? '') === $value)<span aria-hidden="true">✓</span>@endif {{ $label }}
            </a>
        @endforeach
    </nav>
</section>
<div class="library-result-heading">
    <h2>{{ $term !== '' ? 'Kết quả tìm kiếm' : ($category ?? 'Khám phá bài viết') }}</h2>
    <p role="status">{{ $articles->total() }} bài viết
        @if($term !== '') cho “{{ $term }}” @endif
    </p>
</div>
@if($articles->isNotEmpty())
    <div class="library-grid">
        @foreach($articles as $article)
            @include('articles.card', ['spotlight' => $articles->currentPage() === 1 && $loop->first && $article->featured_rank > 0, 'priority' => $loop->first])
        @endforeach
    </div>
    @if($articles->hasPages())<div class="library-pagination">{{ $articles->links() }}</div>@endif
@else
    <section class="library-empty" aria-labelledby="library-empty-title">
        <h3 id="library-empty-title">Không tìm thấy bài viết phù hợp</h3>
        <p>Thử từ khóa khác hoặc bỏ bộ lọc để khám phá các bài viết đang công khai.</p>
        <a class="btn btn-outline-dark" href="{{ route('articles.index') }}">Xem tất cả bài viết →</a>
    </section>
@endif
@endsection
