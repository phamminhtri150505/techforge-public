@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')
@section('body_class', 'order-history-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/order-history.css') }}?v=20261106">
@endpush
@section('content')
    <header class="order-history-heading">
        <div>
            <p class="eyebrow">LỊCH SỬ ĐƠN MÔ PHỎNG</p>
            <h1 class="h2">Đơn hàng của tôi</h1>
            <p class="order-history-description">Xem lại các đơn bạn đã tạo. Không thu tiền và không giao hàng.</p>
        </div>
        <a class="btn btn-outline-dark" href="{{ route('cart.index') }}">Xem giỏ hàng <span aria-hidden="true">↗</span></a>
    </header>

    @if($orders->total() === 0)
        <section class="order-history-empty" aria-labelledby="empty-orders-title">
            <span class="order-empty-symbol" aria-hidden="true">↗</span>
            <h2 class="h4" id="empty-orders-title">Bạn chưa có đơn hàng nào</h2>
            <p>Bạn có thể chọn mua lẻ linh kiện hoặc chọn phần cần mua từ cấu hình PC.</p>
            <div class="order-empty-actions">
                <a class="btn btn-forge" href="{{ route('products.index') }}">Xem linh kiện</a>
                <a class="btn btn-outline-dark" href="{{ route('builder.index') }}">Mở PC Builder</a>
            </div>
        </section>
    @elseif($orders->isEmpty())
        <section class="order-history-empty" aria-labelledby="missing-orders-page-title">
            <h2 class="h4" id="missing-orders-page-title">Trang này không có đơn hàng</h2>
            <p>Hãy quay về trang đầu để xem các đơn đã tạo.</p>
            <a class="btn btn-forge" href="{{ route('orders.index') }}">Về trang đầu</a>
        </section>
    @else
        <section aria-labelledby="order-list-title">
            <div class="order-list-heading">
                <h2 class="h5" id="order-list-title">Đơn đã tạo</h2>
                <p>{{ number_format($orders->total(), 0, ',', '.') }} đơn mô phỏng · Mới nhất trước</p>
            </div>
            <div class="order-history-list">
                @foreach($orders as $order)
                    @php($knownStatus = array_key_exists($order->status, \App\Models\Order::STATUSES))
                    <article class="order-history-card" aria-labelledby="order-title-{{ $order->id }}">
                        <div class="order-history-info">
                            <div class="order-history-card-heading">
                                <h3 class="h5" id="order-title-{{ $order->id }}">Đơn mô phỏng #{{ $order->id }}</h3>
                                <span class="order-status order-status-{{ $knownStatus ? $order->status : 'unknown' }}">
                                    <span class="visually-hidden">Trạng thái: </span>{{ \App\Models\Order::STATUSES[$order->status] ?? 'Chưa rõ trạng thái' }}
                                </span>
                            </div>
                            <p class="order-created-at">Ngày tạo: <time datetime="{{ $order->created_at->toAtomString() }}">{{ $order->created_at->format('d/m/Y · H:i') }}</time></p>
                        </div>
                        <div class="order-history-actions">
                            <div class="order-history-total">
                                <span>Tổng đơn</span>
                                <strong>{{ number_format($order->total, 0, ',', '.') }} ₫</strong>
                            </div>
                            <a class="btn btn-outline-dark" href="{{ route('orders.show', $order) }}">Xem đơn #{{ $order->id }} <span aria-hidden="true">↗</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $orders->links() }}
        </section>
    @endif
@endsection
