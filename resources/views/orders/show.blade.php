@extends('layouts.app')
@section('title', 'Chi tiết đơn #' . $order->id)
@section('body_class', 'order-detail-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/order-detail.css') }}?v=20261106">
@endpush
@section('content')
    @php
        // Chỉ trình bày dữ liệu đã lưu của đơn; không gọi lại bộ kiểm tra hoặc giá hiện tại.
        $savedReport = is_array($order->compatibility_snapshot) ? $order->compatibility_snapshot : [];
        $savedStatus = $savedReport['status'] ?? null;
        $statusLabels = ['PASS' => 'Đạt kiểm tra', 'WARNING' => 'Cần lưu ý', 'CONFLICT' => 'Không phù hợp'];
        $isRetail = $savedStatus === 'NOT_CHECKED';
        $savedRules = collect($savedReport['rules'] ?? [])->sortBy(fn ($rule) => ['CONFLICT' => 0, 'WARNING' => 1, 'PASS' => 2][$rule['status'] ?? ''] ?? 3);
        $familiarWords = ['mainboard' => 'bo mạch chủ', 'Mainboard' => 'Bo mạch chủ', 'Socket' => 'Chân cắm', 'socket' => 'chân cắm', 'PSU' => 'nguồn máy tính', 'GPU' => 'card đồ họa', 'case' => 'vỏ máy', 'revision' => 'phiên bản'];
        $typeLabels = collect(\App\Services\ProductSpecificationSchema::TYPES)->map(fn ($label) => strtr($label, $familiarWords));
        $knownOrderStatus = array_key_exists($order->status, \App\Models\Order::STATUSES);
    @endphp

    <header class="order-detail-heading">
        <div>
            <p class="eyebrow">CHI TIẾT ĐƠN MÔ PHỎNG</p>
            <h1 class="h2">Đơn hàng #{{ $order->id }}</h1>
            <p class="order-detail-date">Ngày tạo: <time datetime="{{ $order->created_at->toAtomString() }}">{{ $order->created_at->format('d/m/Y · H:i') }}</time></p>
        </div>
        <a class="btn btn-outline-dark" href="{{ route('orders.index') }}"><span aria-hidden="true">←</span> Về đơn hàng của tôi</a>
    </header>
    <p class="order-detail-notice">Đây là đơn COD mô phỏng. Không thu tiền và không giao hàng.</p>

    <div class="order-detail-layout">
        <section class="order-detail-panel order-detail-items" aria-labelledby="order-items-title">
            <div class="order-detail-section-heading">
                <h2 class="h5" id="order-items-title">Linh kiện trong đơn</h2>
                <span>{{ $order->items->count() }} dòng hàng</span>
            </div>
            <p class="order-detail-muted">Tên và giá linh kiện giữ theo lúc đặt. Thay đổi trong danh mục không làm đổi đơn này.</p>
            @if($order->items->isNotEmpty())
                <div class="table-responsive">
                    <table class="table order-items-table">
                        <caption class="visually-hidden">Linh kiện và giá được lưu tại thời điểm đặt đơn</caption>
                        <thead>
                            <tr>
                                <th scope="col">Linh kiện</th>
                                <th scope="col" class="text-center">Số lượng</th>
                                <th scope="col" class="text-end">Đơn giá</th>
                                <th scope="col" class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td><strong class="order-item-name">{{ $item->product_name }}</strong><span class="order-item-sku">Mã: {{ $item->sku }}</span></td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end order-item-price">{{ number_format($item->unit_price, 0, ',', '.') }} ₫</td>
                                    <td class="text-end order-item-price"><strong>{{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }} ₫</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p>Không có thông tin dòng hàng được lưu cho đơn này.</p>
            @endif
        </section>

        <aside class="order-detail-panel order-detail-summary" aria-labelledby="order-total-title">
            <h2 class="h5" id="order-total-title">Tóm tắt đơn hàng</h2>
            <p class="order-detail-status"><span>Trạng thái</span><strong class="order-state order-state-{{ $knownOrderStatus ? $order->status : 'unknown' }}">{{ \App\Models\Order::STATUSES[$order->status] ?? 'Chưa rõ trạng thái' }}</strong></p>
            <dl class="order-detail-totals">
                @if($order->subtotal !== null)
                    <div><dt>Tạm tính</dt><dd>{{ number_format($order->subtotal, 0, ',', '.') }} ₫</dd></div>
                @endif
                <div><dt>Ưu đãi</dt><dd>{{ $order->discount > 0 ? '−'.number_format($order->discount, 0, ',', '.').' ₫' : 'Không áp dụng ưu đãi' }}</dd></div>
                <div class="order-detail-grand-total"><dt>Tổng đơn mô phỏng</dt><dd>{{ number_format($order->total, 0, ',', '.') }} ₫</dd></div>
            </dl>
            @if(!empty($order->promotion_snapshot['name']))
                <p class="order-saved-promotion">Ưu đãi tại lúc đặt: <strong>{{ $order->promotion_snapshot['name'] }}</strong></p>
            @endif
            <details class="order-recipient">
                <summary>Thông tin người nhận</summary>
                <dl>
                    <div><dt>Người nhận</dt><dd>{{ $order->recipient }}</dd></div>
                    <div><dt>Số điện thoại</dt><dd>{{ $order->phone }}</dd></div>
                    <div><dt>Địa chỉ</dt><dd>{{ $order->address }}</dd></div>
                </dl>
            </details>
        </aside>

        <section class="order-detail-panel order-saved-checks" aria-labelledby="order-checks-title">
            <div class="order-detail-section-heading">
                <h2 class="h5" id="order-checks-title">Kết quả kiểm tra lúc đặt đơn</h2>
                @if(isset($statusLabels[$savedStatus]))
                    <span class="status-badge status-{{ strtolower($savedStatus) }}">{{ $statusLabels[$savedStatus] }}</span>
                @endif
            </div>
            @if($isRetail)
                <p class="mb-0">Mua lẻ — chưa đánh giá cả PC. Đơn này không có kết luận về toàn bộ cấu hình máy tính.</p>
            @elseif(isset($statusLabels[$savedStatus]))
                <p class="order-detail-muted">Đây là kết quả đã lưu lúc đặt đơn, không phải lần kiểm tra mới theo danh mục hiện tại.</p>
                @if(array_key_exists('selection_complete', $savedReport))
                    <p>{{ $savedReport['selection_complete'] ? 'Đã chọn đủ nhóm linh kiện.' : 'Cấu hình chưa hoàn chỉnh.' }}
                        @if(array_key_exists('checks_complete', $savedReport))
                            {{ $savedReport['checks_complete'] ? 'Đã có đủ thông tin cho các mục kiểm tra.' : 'Còn thiếu thông tin để kiểm tra đầy đủ.' }}
                        @endif
                        @if(isset($savedReport['checked_count'], $savedReport['rule_count']))
                            Đã kiểm tra {{ $savedReport['checked_count'] }}/{{ $savedReport['rule_count'] }} mục.
                        @endif
                    </p>
                @endif
                @if(!empty($savedReport['owned_types']))
                    <p>Linh kiện đã có, không mua trong đơn này: <strong>{{ collect($savedReport['owned_types'])->map(fn ($type) => $typeLabels[$type] ?? $type)->join(', ') }}</strong>.</p>
                @endif
                @if($savedRules->isNotEmpty())
                    <details class="order-saved-rules" @if($savedStatus === 'CONFLICT') open @endif>
                        <summary>Xem các mục kiểm tra đã lưu · {{ $savedRules->count() }} mục</summary>
                        <ul>
                            @foreach($savedRules as $rule)
                                <li data-saved-rule="{{ $rule['code'] ?? '' }}">
                                    <div><strong>{{ strtr($rule['title'] ?? 'Mục kiểm tra', $familiarWords) }}</strong><span>{{ $statusLabels[$rule['status'] ?? ''] ?? 'Chưa rõ kết quả' }}</span></div>
                                    <p><x-compatibility-message :message="$rule['message'] ?? ''" :word-labels="$familiarWords" /></p>
                                    <small>{{ !empty($rule['checked']) ? 'Đã kiểm tra đủ thông tin' : 'Còn thiếu thông tin hoặc cần xác nhận' }}</small>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <p>Không có thông tin từng mục kiểm tra được lưu.</p>
                @endif
                <p class="order-checks-limit">“Đạt kiểm tra” chỉ áp dụng cho những mục có đủ thông tin đã kiểm tra. Kết quả không bảo đảm mọi điều kiện lắp ráp, hiệu năng hoặc điện tiêu thụ thực tế.</p>
            @else
                <p class="mb-0">Chưa có kết quả kiểm tra được lưu cho đơn này.</p>
            @endif
        </section>
    </div>
@endsection
