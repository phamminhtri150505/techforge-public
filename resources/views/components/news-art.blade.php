@props(['article', 'priority' => false])
@php
    $art = config('news_art.'.$article->slug);
    $style = array_key_exists((string) $article->cover_style, \App\Models\Article::COVER_STYLES) ? $article->cover_style : 'circuit';
    $hasPhoto = isset($art['image'], $art['owner']) && is_string($art['image'])
        && str_starts_with($art['image'], 'images/products/') && !str_contains($art['image'], '..')
        && is_file(public_path($art['image']));
    $image = $hasPhoto ? $art['image'] : 'images/editorial/'.$style.'.svg';
@endphp
<div class="news-art news-art--{{ $art['theme'] ?? 'blue' }}" role="img" aria-label="Hình ảnh cho {{ $article->title }}">
    <span class="news-art-orbit" aria-hidden="true"></span><span class="news-art-grid" aria-hidden="true"></span>
    <span class="news-art-label" aria-hidden="true">{{ $art['label'] ?? 'KIẾN THỨC CÔNG NGHỆ' }}</span>
    @if ($article->slug === 'nhat-ky-rule-engine')
        <div class="news-signal" aria-hidden="true"><span>PASS <b>✓</b></span><span>WARNING <b>!</b></span><span>CONFLICT <b>×</b></span></div>
    @else
        <img class="news-art-object" src="{{ asset($image) }}" alt="" width="640" height="480" loading="{{ $priority ? 'eager' : 'lazy' }}" @if($priority) fetchpriority="high" @endif>
    @endif
    <span class="news-art-topic" aria-hidden="true">{{ $art['topic'] ?? 'HIỂU ĐỂ CHỌN ĐÚNG' }}</span>
</div>
