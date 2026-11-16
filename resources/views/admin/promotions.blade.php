@extends('layouts.app')
@section('title', 'Ưu đãi bộ linh kiện')
@section('content')
    <header class="my-3 mb-4">
        <p class="eyebrow">QUẢN LÝ ƯU ĐÃI</p>
        <div class="section-heading flex-wrap gap-3">
            <h1 class="h2 mb-0">Ưu đãi bộ linh kiện</h1>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark" href="{{ route('admin.index') }}">Về quản trị</a>
                <a class="btn btn-forge" href="{{ route('admin.promotions.create') }}">Tạo ưu đãi</a>
            </div>
        </div>
        <p class="text-secondary mt-3 mb-1">Mỗi đơn mô phỏng nhận tối đa một ưu đãi tốt nhất đủ điều kiện, dựa trên linh kiện thực sự mua.</p>
        <p class="text-secondary small mb-0">Các mốc hiển thị theo giờ Việt Nam (UTC+7). Trạng thái thời gian được tính lúc mở trang.</p>
    </header>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        @forelse ($promotions as $promotion)
            @php
                $starts = $promotion->starts_at;
                $ends = $promotion->ends_at;
                $period = match (true) {
                    $checkedAt->lt($starts) => 'Chưa bắt đầu',
                    $checkedAt->gt($ends) => 'Đã hết hạn',
                    default => 'Đang trong khung giờ',
                };
            @endphp
            <div class="col">
                <article class="summary-card h-100 d-flex flex-column p-4">
                    <h2 class="h5 text-break">{{ $promotion->name }}</h2>
                    <div class="d-flex flex-wrap gap-2 my-2">
                        <span class="badge {{ $promotion->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $promotion->is_active ? 'Đã bật' : 'Đã tắt' }}
                        </span>
                        <span class="badge border text-body">{{ $period }}</span>
                    </div>
                    <p class="mt-2 mb-3">
                        <strong>Giảm {{ number_format($promotion->discount_amount, 0, ',', '.') }} ₫</strong>
                        <span class="text-secondary"> · {{ count($promotion->required_product_ids ?? []) }} linh kiện cần mua</span>
                    </p>
                    <dl class="row small mb-3">
                        <dt class="col-4">Bắt đầu</dt>
                        <dd class="col-8"><time datetime="{{ $starts->toIso8601String() }}">{{ $starts->copy()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</time></dd>
                        <dt class="col-4">Kết thúc</dt>
                        <dd class="col-8 mb-0"><time datetime="{{ $ends->toIso8601String() }}">{{ $ends->copy()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</time></dd>
                    </dl>
                    <a class="btn btn-outline-dark mt-auto align-self-start" href="{{ route('admin.promotions.edit', $promotion) }}">Sửa ưu đãi</a>
                </article>
            </div>
        @empty
            <div class="col w-100">
                <div class="summary-card p-4">
                    <h2 class="h5">Chưa có ưu đãi</h2>
                    <p class="text-secondary mb-0">Chọn Tạo ưu đãi để thiết lập mức giảm cho một nhóm linh kiện mua cùng nhau.</p>
                </div>
            </div>
        @endforelse
    </div>
@endsection
