@extends('layouts.app')
@section('title', 'Giỏ hàng')
@section('body_class', 'cart-page')
@push('styles')<link rel="stylesheet" href="{{ asset('css/cart-experience.css') }}?v=20261106">@endpush
@push('scripts')<script src="{{ asset('js/cart-experience.js') }}?v=20261106" defer></script>@endpush
@php
    // Chỉ đổi cách diễn đạt; mã trạng thái và phép kiểm vẫn lấy từ máy chủ.
    $cartStatusLabels = ['PASS' => 'Đạt kiểm tra', 'WARNING' => 'Cần lưu ý', 'CONFLICT' => 'Không phù hợp'];
    $cartWords = ['Mainboard' => 'Bo mạch chủ', 'mainboard' => 'bo mạch chủ', 'GPU' => 'card đồ họa',
        'Nguồn (PSU)' => 'Nguồn máy tính', 'PSU' => 'nguồn', 'Socket' => 'Đế cắm', 'socket' => 'đế cắm',
        'case' => 'vỏ máy', 'revision' => 'phiên bản'];
    $cartTypes = array_replace(\App\Services\ProductSpecificationSchema::TYPES,
        ['mainboard' => 'Bo mạch chủ', 'gpu' => 'Card đồ họa', 'psu' => 'Nguồn máy tính']);
@endphp
@section('content')
<div class="section-heading mt-3"><div><p class="eyebrow mb-1">MUA THỨ BẠN CẦN</p><h1 class="h2">Giỏ hàng của bạn</h1></div><a href="{{ route('products.index') }}" class="btn btn-outline-dark">Tiếp tục xem linh kiện ↗</a></div>
<p class="text-secondary">Mua lẻ hoặc mua thêm từ cấu hình đã chọn. Giá giả lập/tham khảo theo từng mã; COD và ưu đãi là mô phỏng, không thu tiền, không giao hàng thật.</p>
@if($cart)
    @if($issues)
        <div class="alert alert-warning">@foreach($issues as $messages) @foreach($messages as $message)<p>{{ $message }}</p>@endforeach @endforeach<p class="mb-0">Giỏ được giữ lại để bạn chỉnh sửa; chưa thể tạo đơn.</p></div>
    @endif
    <div class="row g-4">
        <section class="col-lg-7">
            <div class="summary-card">
                @foreach($cart['items'] as $id => $quantity)
                    @php($product = $build['lines'][$id]['product'] ?? $repairProducts->get($id))
                    <article class="cart-line">
                        @if($image = $product?->imageDetails())
                            <a class="cart-product-image" href="{{ route('products.show', $product) }}" aria-label="Xem {{ $product->name }}"><img src="{{ asset($image['path']) }}" alt="{{ $product->imageAlt() }}" width="76" height="76" loading="lazy"></a>
                        @endif
                        <div class="flex-grow-1"><h2 class="h6">@if($product)<a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>@else Linh kiện không còn trong danh mục @endif</h2>
                        <p class="small text-secondary">{{ $product ? $product->formattedPrice().' / sản phẩm · '.\App\Models\Product::PRICE_KINDS[$product->price_kind] : 'Không có giá hiện tại' }}</p>
                        <form method="post" action="{{ route('cart.items.update', $id) }}" class="d-flex gap-2 align-items-center">@csrf @method('PATCH')
                        <label class="small" for="quantity-{{ $id }}">Số lượng</label><input class="form-control form-control-sm" style="width:75px" id="quantity-{{ $id }}" name="quantity" type="number" min="0" max="10" value="{{ $quantity }}" required>
                        <button class="btn btn-outline-dark btn-sm">Cập nhật</button></form><form class="cart-remove-line" method="post" action="{{ route('cart.items.update', $id) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="0"><button class="btn btn-link text-danger btn-sm" aria-label="Bỏ {{ $product?->name ?? 'linh kiện không còn trong danh mục' }} khỏi giỏ">Bỏ khỏi giỏ</button></form></div>
                        <strong class="text-nowrap">{{ $product && $product->price_kind !== 'unknown' ? number_format($product->price * $quantity, 0, ',', '.').' ₫' : 'Liên hệ' }}</strong>
                    </article>
                @endforeach
                @if($build && $build['report'])
                    @php($cartRules = collect($build['report']['rules'])->where('status', '!=', 'PASS')->sortBy(fn ($rule) => $rule['status'] === 'CONFLICT' ? 0 : 1))
                    <div class="scope-card mt-3 cart-build-scope">
                        <strong>Cấu hình đang kiểm tra · {{ $cartStatusLabels[$build['report']['status']] }}</strong>
                        <p class="small mt-2">{{ count($build['selected']) }}/8 nhóm đã chọn · {{ count($build['owned']) }} nhóm đã có (không mua trong đơn này). Các dòng mua lẻ thêm vào không thuộc phương án kiểm tra.</p>
                        @if($build['owned'])<p class="small">Linh kiện đã có: {{ collect($build['owned'])->map(fn ($type) => $cartTypes[$type])->join(', ') }}.</p>@endif
                        <p class="small">{{ $build['report']['selection_complete'] ? 'Đã chọn đủ nhóm.' : 'Cấu hình chưa hoàn chỉnh.' }} Đã kiểm tra {{ $build['report']['checked_count'] }}/{{ $build['report']['rule_count'] }} mục.
                            {{ $build['report']['checks_complete'] ? 'Đủ thông tin cho các mục kiểm tra hiện có.' : 'Còn thiếu thông tin để kiểm tra đầy đủ.' }}</p>
                        @if($cartRules->isNotEmpty())
                            <details class="cart-rule-details" @if($build['report']['status'] === 'CONFLICT') open @endif>
                                <summary>Xem {{ $cartRules->count() }} điểm cần lưu ý hoặc chỉnh sửa</summary>
                                <ul class="small mt-3">@foreach($cartRules as $rule)<li data-cart-rule-status="{{ $rule['status'] }}"><strong>{{ strtr($rule['title'], $cartWords) }}:</strong> <x-compatibility-message :message="$rule['message']" :word-labels="$cartWords" /></li>@endforeach</ul>
                            </details>
                        @endif
                        <p class="small text-secondary mb-0 mt-3">Kết quả áp dụng cho các mục đã kiểm tra đủ thông tin, không bảo đảm mọi điều kiện lắp ráp.</p>
                    </div>
                @elseif($build)<div class="scope-card mt-3"><strong>Giỏ mua lẻ</strong><p class="mb-0 mt-2">Chưa đánh giá tương thích cả PC. Đưa linh kiện và các phần đang có vào trang chọn linh kiện để kiểm tra.</p></div>@endif
                @if($cart['parts'])
                    <details class="cart-edit-confirm mt-3"><summary>Chỉnh sửa cấu hình trong giỏ →</summary><div class="cart-clear-body"><p>Cấu hình trong giỏ sẽ thay các linh kiện và dấu “Tôi đã có” trên trang chọn linh kiện. Ngân sách, nhu cầu và giỏ hàng vẫn được giữ. Thêm lại từ cấu hình sẽ thay giỏ, kể cả các món mua lẻ bổ sung.</p><form method="post" action="{{ route('cart.edit') }}">@csrf<button class="btn btn-outline-dark btn-sm">Xác nhận mở cấu hình</button><button class="btn btn-outline-dark btn-sm" type="button" data-cancel-cart-action>Hủy</button></form></div></details>
                @endif
                <details class="cart-clear-confirm mt-3"><summary>Bỏ tất cả khỏi giỏ</summary><div class="cart-clear-body"><p>Toàn bộ linh kiện trong giỏ sẽ được bỏ. Các linh kiện đang chọn để xây dựng PC và cấu hình đã lưu vẫn được giữ.</p><form action="{{ route('cart.destroy') }}" method="post">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Xác nhận bỏ tất cả</button><button class="btn btn-outline-dark btn-sm" type="button" data-cancel-cart-action>Giữ giỏ hàng</button></form></div></details>
            </div>
        </section>
        <section class="col-lg-5">
        @if($build)
            <div class="summary-card">
                <h2 class="h4">Tóm tắt đơn hàng</h2><p class="cart-simulation-note">Đặt hàng thử nghiệm, không thu tiền và không giao hàng. Bạn không cần cung cấp thông tin cá nhân thật.</p>
                <div class="money-row"><span>Tạm tính</span><strong>{{ number_format($build['subtotal'], 0, ',', '.') }} ₫</strong></div>
                <div class="money-row"><span>Ưu đãi</span><strong>{{ $build['discount'] > 0 ? '−'.number_format($build['discount'], 0, ',', '.').' ₫' : 'Chưa có ưu đãi áp dụng' }}</strong></div>
                @if($build['promotion'])<div class="deal-note">{{ $build['promotion']['name'] }} · một ưu đãi tốt nhất cho đơn này</div>@endif
                <div class="money-row total-row"><span>Tổng đơn mô phỏng</span><strong>{{ number_format($build['total'], 0, ',', '.') }} ₫</strong></div>
                @auth
                <form method="post" action="{{ route('orders.store') }}" class="mt-4">@csrf<input type="hidden" name="checkout_token" value="{{ $token }}">
                    <label class="form-label" for="recipient">Người nhận</label><input class="form-control mb-3" name="recipient" id="recipient" value="{{ old('recipient') }}" maxlength="100" required>
                    <label class="form-label" for="phone">Số điện thoại</label><input class="form-control mb-3" name="phone" id="phone" type="tel" value="{{ old('phone') }}" inputmode="tel" maxlength="16" pattern="\+?[0-9]{9,15}" required>
                    <label class="form-label" for="address">Địa chỉ</label><textarea class="form-control mb-3" name="address" id="address" maxlength="500" required>{{ old('address') }}</textarea>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="simulation_ack" id="simulation_ack" value="1" required><label class="form-check-label" for="simulation_ack">Tôi hiểu đây là đơn mô phỏng, không phát sinh giao dịch thực.</label></div>
                    @if(($build['report']['status'] ?? null) === 'WARNING')
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="accept_warnings" id="accept_warnings" value="1" required><label class="form-check-label" for="accept_warnings">Tôi đã đọc các cảnh báo, phần chưa đủ dữ liệu và giới hạn kiểm tra.</label></div>@endif
                    <button class="btn btn-forge w-100" @disabled(($build['report']['status'] ?? null) === 'CONFLICT')>Tạo đơn COD mô phỏng</button>
                    @if(($build['report']['status'] ?? null) === 'CONFLICT')<p class="text-danger small mt-2">Có linh kiện không phù hợp. Hãy chỉnh cấu hình trước khi tạo đơn mô phỏng.</p>@endif
                </form>
                @else<a class="btn btn-dark w-100 mt-3" href="{{ route('login') }}">Đăng nhập để đặt mô phỏng</a>@endauth
            </div>
        @else
            <div class="summary-card cart-unavailable-total"><h2 class="h4">Chưa thể tính tổng đơn</h2><p>Hãy xem thông báo và chỉnh lại các linh kiện trong giỏ. Chưa thể tạo đơn mô phỏng lúc này.</p></div>
        @endif
        </section>
    </div>
@else
    <div class="empty-state"><span class="empty-symbol">＋</span><h2>Giỏ của bạn đang chờ.</h2><p>Khám phá danh mục hoặc chọn phần cần mua từ Builder.</p><a class="btn btn-forge" href="{{ route('products.index') }}">Xem linh kiện</a> <a class="btn btn-outline-dark" href="{{ route('builder.index') }}">Mở Builder</a></div>
@endif
@endsection
