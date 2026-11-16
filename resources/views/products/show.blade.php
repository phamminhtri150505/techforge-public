@extends('layouts.app')
@section('title', $product->name)
@section('meta_description', \Illuminate\Support\Str::limit($product->name.' — '.$types[$product->type].'. Xem thông số, nguồn dữ liệu và lựa chọn cho cấu hình PC.', 160))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-detail.css') }}?v=20261112-dark-premium">
@endpush
@push('scripts')
<script src="{{ asset('js/product-detail.js') }}?v=20261101" defer></script>
@endpush
@section('content')
@php($productImage = $product->imageDetails())
<article class="product-detail-page">
    <nav class="product-breadcrumb" aria-label="Đường dẫn linh kiện"><ol>
        <li><a href="{{ route('products.index') }}">Danh mục linh kiện</a></li>
        <li><a href="{{ route('products.index', ['type' => $product->type]) }}">{{ $types[$product->type] }}</a></li>
        <li aria-current="page">{{ $product->name }}</li>
    </ol></nav>
    <div @class(['product-detail-grid' => $productImage])>
        @if($productImage)
            <figure class="product-photo">
                <div class="product-photo-stage"><img src="{{ asset($productImage['path']) }}" alt="{{ $product->imageAlt() }}" decoding="async" loading="eager" fetchpriority="high" width="{{ $productImage['width'] }}" height="{{ $productImage['height'] }}"></div>
                <figcaption>
                    <p class="product-photo-kind">{{ $product->imageCaption() }}</p>
                    @if($productImage['kind'] === 'licensed_photo')
                        <p><a href="{{ $productImage['source_page_url'] }}">{{ $productImage['source_owner'] }}</a> · <a href="{{ $productImage['license_url'] }}">{{ $productImage['license_name'] }}</a></p>
                    @endif
                    <details><summary>Nguồn và ghi chú ảnh</summary>
                        <p><a href="{{ $productImage['source_page_url'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $productImage['source_owner'] }} <span class="visually-hidden">(mở tab mới)</span></a> · đối chiếu {{ $productImage['checked_on'] }}</p>
                        @if(!empty($productImage['original_video_url']))
                            <p><a href="{{ $productImage['original_video_url'] }}" rel="nofollow noopener noreferrer">Xem video gốc</a></p>
                        @endif
                        <p>{{ $productImage['note'] }}</p>
                    </details>
                </figcaption>
            </figure>
        @endif
        <div class="product-overview">
            <header><p class="eyebrow">@if($product->brand){{ $product->brand }} <span aria-hidden="true">/</span> @endif{{ $types[$product->type] }}</p>
                <h1>{{ $product->name }}</h1>
                <dl class="product-identifiers"><div><dt>SKU</dt><dd>{{ $product->sku }}</dd></div>@if($product->manufacturer_code)<div><dt>Mã hãng</dt><dd>{{ $product->manufacturer_code }}</dd></div>@endif</dl>
                @unless($productImage)<p class="small text-secondary">{{ str_starts_with($product->sku, 'DEMO-') ? 'Linh kiện giả lập.' : 'Chưa có ảnh đúng mẫu đã xác minh.' }}</p>@endunless
            </header>
            @if($highlights)
                <section class="product-highlights" aria-labelledby="product-highlights-title"><h2 id="product-highlights-title">Thông số nổi bật</h2>
                    <dl>@foreach($highlights as $highlight)<div><dt>{{ $highlight['label'] }}</dt><dd>{{ $highlight['value'] }}</dd></div>@endforeach</dl>
                </section>
            @endif
            <section class="product-price-block" aria-label="Giá và trạng thái nhận đơn">
                <p class="product-price">{{ $product->formattedPrice() }}</p>
                <p class="product-price-kind">{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</p>
                <p class="product-availability">{{ \App\Models\Product::AVAILABILITY[$product->availability] }} <span>· không phải tồn kho thực tế</span></p>
                @if($product->price_source_url)
                    <p class="product-price-source">Nguồn giá: <a href="{{ $product->price_source_url }}" rel="nofollow noopener noreferrer" target="_blank">{{ $product->price_retailer }} <span class="visually-hidden">(mở tab mới)</span></a> · đối chiếu {{ $product->price_observed_on?->format('d/m/Y') }}</p>
                @endif
                @if($product->price_note)<details class="product-price-note"><summary>Điều kiện và ghi chú giá</summary><p>{{ $product->price_note }}</p></details>@endif
            </section>
            @if($confirmation)
                @include('products.confirm-selection')
            @else
                @if($currentId === $product->id)<p class="product-current-selection">Đang được chọn trong Builder. Hai nút dưới cập nhật theo trạng thái mua thêm hoặc đã sở hữu bạn chọn.</p>@endif
                <div class="product-builder-actions">
                    @foreach(['buy' => 'Chọn vào Builder →', 'owned' => 'Tôi đã có linh kiện này'] as $ownership => $label)
                        <form method="post" action="{{ route('products.builder', $product) }}">@csrf
                            <input type="hidden" name="ownership" value="{{ $ownership }}"><input type="hidden" name="selection_token" value="{{ $selectionTokens[$ownership] }}">
                            <button class="btn {{ $ownership === 'buy' ? 'btn-forge' : 'btn-outline-dark' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
                @if($currentId && $currentId !== $product->id)<p class="product-action-help">Builder đang có {{ $currentProduct?->name ?? 'một linh kiện khác cùng nhóm' }}. Bạn sẽ được xác nhận trước khi thay.</p>@endif
            @endif
            <p class="product-action-help">Linh kiện “đã có” vẫn tham gia kiểm tra, không cộng vào chi phí mua thêm. Kết quả tương thích chỉ có sau khi đối chiếu cấu hình trong Builder.</p>
            @if($product->availability === 'orderable' && $product->price_kind !== 'unknown')
                <form class="product-retail-form" method="post" action="{{ route('products.cart', $product) }}">@csrf
                    <div><label for="quantity">Số lượng</label><input id="quantity" name="quantity" type="number" class="form-control" value="{{ is_scalar(old('quantity', 1)) ? old('quantity', 1) : 1 }}" min="1" max="10" required aria-describedby="retail-help"></div>
                    <button class="btn btn-dark">Thêm vào giỏ mua lẻ</button>
                </form>
                <p class="product-action-help" id="retail-help">Từ 1–10 sản phẩm mỗi mã. Giỏ mua lẻ không phải kết quả kiểm tra cả PC.</p>
            @else
                <p class="product-unavailable">{{ \App\Models\Product::AVAILABILITY[$product->availability] }} — chưa nhận đơn mô phỏng cho sản phẩm này. Bạn vẫn có thể chọn làm linh kiện đã sở hữu để kiểm tra.</p>
            @endif
            <p class="product-simulation-note">TECHFORGE chỉ mô phỏng đặt hàng, không thu tiền hoặc giao hàng.</p>
        </div>
    </div>
    <section class="product-full-specs" aria-labelledby="full-specs-title"><div class="product-section-heading"><p class="eyebrow">Đối chiếu trước khi chọn</p><h2 id="full-specs-title">Thông số & nguồn đối chiếu</h2><p>Các thông số hiện có trong danh mục; tốc độ công bố không phải benchmark TECHFORGE.</p></div>
        @include('products.specifications')
        <div class="product-data-source"><p>{{ $product->data_source }}</p>
            @foreach($product->catalog_sources ?? [] as $source)<p><a href="{{ $source['url'] }}" rel="nofollow noopener noreferrer" target="_blank">Tài liệu hãng {{ $loop->iteration }} <span class="visually-hidden">(mở tab mới)</span></a> · đối chiếu {{ $source['checked_on'] }}</p>@endforeach
        </div>
    </section>
    <div class="product-reading-grid">
        <section class="product-guidance" aria-labelledby="selection-guidance-title"><p class="eyebrow">Hiểu để chọn đúng</p><h2 id="selection-guidance-title">Lưu ý khi lựa chọn linh kiện</h2><p>{{ $guidance['text'] }}</p>
            @if($guidance['source'])<a href="{{ $guidance['source'] }}" target="_blank" rel="noopener noreferrer">{{ $guidance['owner'] }} <span class="visually-hidden">(mở tab mới)</span> ↗</a>@endif
            <p class="product-guidance-limit">Thông số riêng lẻ không xác nhận phù hợp cả PC. PASS chỉ nói về các luật đã đủ dữ liệu và vượt qua; WARNING cần xác minh thêm, CONFLICT chỉ ra vi phạm ràng buộc kỹ thuật.</p>
        </section>
        @if($relatedArticles->isNotEmpty())<section class="product-related" aria-labelledby="product-related-title"><p class="eyebrow">Đọc trước khi quyết định</p><h2 id="product-related-title">Kiến thức liên quan</h2><ul>@foreach($relatedArticles as $article)<li><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }} <span aria-hidden="true">→</span></a></li>@endforeach</ul></section>@endif
    </div>
</article>
@endsection
