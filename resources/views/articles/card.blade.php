<article @class(['library-card', 'library-card--spotlight' => $spotlight ?? false])>
    <a class="library-card-art" href="{{ route('articles.show', $article->slug) }}" aria-label="Đọc: {{ $article->title }}"><x-news-art :article="$article" :priority="$priority ?? false" /></a>
    <div class="library-card-body">
        <div class="library-card-meta"><span>{{ $article->category }}</span>@if($spotlight ?? false)<span class="library-featured">ĐÁNG CHÚ Ý</span>@endif</div>
        <h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h3>
        <p>{{ $article->excerpt }}</p>
        <div class="library-card-footer"><time datetime="{{ $article->created_at->toDateString() }}">{{ $article->created_at->format('d.m.Y') }}</time><a href="{{ route('articles.show', $article->slug) }}" aria-label="Đọc tiếp: {{ $article->title }}">Đọc tiếp <span aria-hidden="true">→</span></a></div>
    </div>
</article>
