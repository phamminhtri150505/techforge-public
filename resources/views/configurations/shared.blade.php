@extends('layouts.app')
@section('title', 'Cấu hình được chia sẻ')
@section('body_class', 'reference-page shared-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reference-builds.css') }}?v=20261104">
    <link rel="stylesheet" href="{{ asset('css/shared-configuration.css') }}?v=20261114-photos">
@endpush
@section('content')
@php
    // Chỉ đổi lời hiển thị; kết luận và giá vẫn do các service hiện có cung cấp.
    $statusLabels = ['PASS' => 'Đạt kiểm tra', 'WARNING' => 'Cần lưu ý', 'CONFLICT' => 'Không phù hợp'];
    $familiarWords = ['Mainboard' => 'Bo mạch chủ', 'mainboard' => 'bo mạch chủ', 'GPU' => 'card đồ họa',
        'Nguồn (PSU)' => 'Nguồn máy tính', 'PSU' => 'nguồn', 'vỏ case' => 'vỏ máy', 'case' => 'vỏ máy',
        'Builder' => 'trang chọn linh kiện', 'Socket' => 'Đế cắm', 'socket' => 'đế cắm', 'revision' => 'phiên bản'];
    $types = array_replace($types, ['mainboard' => 'Bo mạch chủ', 'gpu' => 'Card đồ họa', 'psu' => 'Nguồn máy tính', 'case' => 'Vỏ máy']);
@endphp
<div class="shared-experience">
    <header class="shared-heading">
        <p class="eyebrow">CẤU HÌNH ĐƯỢC CHIA SẺ</p>
        <h1>{{ $configurationName }}</h1>
        <p>Xem các linh kiện, kiểm tra những điểm cần lưu ý rồi tạo phiên bản của riêng bạn.</p>
        <a href="{{ route('reference-builds.index') }}">Khám phá cấu hình tham khảo ↗</a>
    </header>
    @if ($build === null)
        <div class="alert alert-warning" role="alert">
            <strong>Hiện chưa thể sao chép cấu hình này.</strong>
            <ul class="mb-0 mt-2">@foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
            <p class="mb-0 mt-2">Người tạo cần chọn lại linh kiện và lưu bản mới để chia sẻ. Hệ thống không tự bỏ linh kiện khỏi bản sao.</p>
        </div>
    @else
        <div class="shared-overview" aria-label="Tóm tắt cấu hình">
            <span><strong>{{ count($build['selected']) }}/{{ count($types) }}</strong> nhóm linh kiện đã chọn</span>
            <span><strong>{{ $build['report']['checked_count'] }}/{{ $build['report']['rule_count'] }}</strong> mục đã kiểm tra</span>
            <a href="#shared-checks" class="status-badge status-{{ strtolower($build['report']['status']) }}">{{ $statusLabels[$build['report']['status']] }} ↓</a>
        </div>
        @if(! $build['report']['selection_complete'])
            <div class="alert alert-warning"><strong>Cấu hình chưa hoàn chỉnh.</strong> Còn thiếu: {{ collect($build['report']['missing_types'])->map(fn ($type) => $types[$type])->join(', ') }}. Bạn vẫn có thể sao chép để chọn thêm.</div>
        @endif
        <div class="shared-layout">
            <section aria-labelledby="shared-parts-title">
                <h2 id="shared-parts-title" class="h4 mb-3">Các linh kiện trong cấu hình</h2>
                <div class="shared-parts">
                @foreach ($build['selected'] as $type => $product)
                    @php($image = $product->imageDetails())
                    <article @class(['shared-part', 'shared-part-without-photo' => !$image])>
                        @if($image)
                            <a class="shared-image" href="{{ route('products.show', $product) }}" aria-label="Xem {{ $product->name }}"><img src="{{ asset($image['path']) }}" alt="{{ $product->imageAlt() }}" width="88" height="88" loading="lazy"></a>
                        @endif
                        <div class="shared-part-info">
                            <p class="eyebrow mb-1">{{ $types[$type] }}</p>
                            <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                            <p class="reference-meta mb-1">{{ $product->sku }}</p>
                            <details class="shared-source"><summary>Nguồn và điều kiện giá</summary>
                                <p>{{ $product->data_source }}</p>
                                @if($product->price_source_url)<a href="{{ $product->price_source_url }}" target="_blank" rel="nofollow noopener noreferrer">{{ $product->price_retailer ?: 'Xem nguồn giá' }} ↗</a> · {{ $product->price_observed_on?->format('d/m/Y') }}@endif
                                @if($product->price_note)<p>{{ $product->price_note }}</p>@endif
                            </details>
                        </div>
                        <div class="shared-price"><strong>{{ $product->formattedPrice() }}</strong><span>{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</span><span>{{ \App\Models\Product::AVAILABILITY[$product->availability] }}</span></div>
                    </article>
                @endforeach
                </div>
            </section>
            <aside class="shared-summary" aria-labelledby="shared-copy-title">
                <p class="eyebrow">PHIÊN BẢN CỦA BẠN</p>
                <h2 id="shared-copy-title">Thử cấu hình này</h2>
                <p class="reference-meta mb-1">{{ $build['unpriced_selection'] ? 'Tạm tính linh kiện đã có giá' : 'Tổng giá các linh kiện đã chọn' }}</p>
                <p class="shared-total">@if(count($build['unpriced_selection']) === count($build['selected']))Liên hệ@else{{ number_format($build['total'], 0, ',', '.') }} ₫@endif</p>
                @if($build['unpriced_selection'])<p class="text-warning-emphasis small">Chưa đủ giá: {{ collect($build['unpriced_selection'])->map(fn ($type) => $types[$type])->join(', ') }}. Tổng chưa đầy đủ, không phải miễn phí.</p>@endif
                @if(collect($build['selected'])->contains(fn ($product) => $product->availability !== 'orderable' || $product->price_kind === 'unknown'))
                    <p class="text-warning-emphasis small">Có linh kiện cần liên hệ, chưa có giá hoặc tạm ngừng nhận đơn. Bạn vẫn có thể sao chép để chỉnh sửa, nhưng chưa thể đặt toàn bộ cấu hình trong giỏ mô phỏng.</p>
                @endif
                <p class="reference-meta">Giá và kết quả được kiểm tra lại mỗi lần mở trang. Xem loại giá ở từng linh kiện; đây chưa phải báo giá mua hàng. Ưu đãi (nếu có) sẽ được tính lại khi chọn linh kiện cần mua.</p>
                <a class="shared-check-link" href="#shared-checks">Xem kết quả kiểm tra ↓</a>
                <details class="shared-confirm">
                    <summary class="btn btn-forge w-100">Tạo bản sao để chỉnh sửa</summary>
                    <div class="shared-confirm-body">
                        <h3>Xác nhận thay linh kiện đang chọn</h3>
                        <p>Bản sao sẽ <strong>thay toàn bộ lựa chọn hiện tại</strong> trên trang chọn linh kiện và <strong>bỏ hết dấu “Tôi đã có”</strong>. Hãy đánh dấu lại những món bạn có sẵn.</p>
                        <p>Ngân sách, nhu cầu và giỏ hàng của bạn vẫn được giữ. Bản chia sẻ gốc không thay đổi.</p>
                        @if(! $build['report']['selection_complete'])<p class="text-warning-emphasis">Chỉ sao chép {{ count($build['selected']) }} nhóm đang có; các nhóm còn thiếu không được giữ từ lựa chọn cũ.</p>@endif
                        <form method="post" action="{{ route('configurations.remix', $token) }}">
                            @csrf
                            <input type="hidden" name="selection_token" value="{{ $selectionToken }}">
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_remix" value="1" id="confirm-remix" required><label class="form-check-label" for="confirm-remix">Tôi đã hiểu và đồng ý thay lựa chọn.</label></div>
                            <div class="d-flex gap-2 flex-wrap"><button class="btn btn-forge">Xác nhận sao chép</button><button class="btn btn-outline-dark" type="reset">Bỏ xác nhận</button></div>
                        </form>
                        <p class="reference-meta mt-3 mb-0">Chưa có thay đổi cho đến khi bạn xác nhận. Nếu đã đổi linh kiện ở tab khác, hãy tải lại trang này trước khi sao chép.</p>
                    </div>
                </details>
                <p class="reference-meta mt-3 mb-0">Không cần đăng nhập để thử. Đăng nhập khi bạn muốn lưu thành bản riêng.</p>
            </aside>
        </div>
        <div id="shared-checks">@include('reference-builds.report', ['report' => $build['report'], 'slug' => 'shared'])</div>
    @endif
</div>
@endsection
