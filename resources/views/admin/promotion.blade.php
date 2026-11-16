@extends('layouts.app')
@section('title', 'Biên tập ưu đãi bộ')
@section('content')
<h1 class="h2">{{ $promotion->exists ? 'Sửa ưu đãi bộ' : 'Tạo ưu đãi bộ' }}</h1>
@if ($promotion->exists)
    <p class="text-secondary mb-2">Trạng thái đã lưu: <span class="badge {{ $promotion->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $promotion->is_active ? 'Đã bật' : 'Đã tắt' }}</span></p>
    <p class="text-secondary">Thay đổi có thể ảnh hưởng đến các đơn mới. Các đơn đã đặt giữ mức giảm lúc đặt.</p>
@endif
<form class="summary-card mt-4" method="post" action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}">@csrf @if($promotion->exists) @method('PUT') @endif
<label class="form-label" for="name">Tên chương trình</label><input class="form-control mb-3" id="name" name="name" maxlength="100" value="{{ old('name', $promotion->name) }}" required>
<label class="form-label" for="discount_amount">Mức giảm cố định (VND)</label><input class="form-control mb-3" id="discount_amount" name="discount_amount" type="number" min="1" max="1000000000" value="{{ old('discount_amount', $promotion->discount_amount) }}" required>
<div class="row g-3 mb-3">
    @foreach (['starts_at' => 'Bắt đầu', 'ends_at' => 'Kết thúc'] as $field => $label)
        <div class="col-md-6">
            <label class="form-label" for="{{ $field }}">{{ $label }} (giờ Việt Nam, UTC+7)</label>
            {{-- Khi sửa, giữ cả giây của mốc đã lưu để mở và lưu lại không làm đổi giờ hiệu lực. --}}
            <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="datetime-local" @if ($promotion->exists) step="1" @endif
                value="{{ old($field, $promotion->$field?->format($promotion->exists ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i')) }}" required>
        </div>
    @endforeach
</div>
<label class="form-label" for="is_active">Áp dụng</label><select class="form-select mb-3" id="is_active" name="is_active"><option value="0" @selected(!old('is_active', $promotion->is_active))>Tắt</option><option value="1" @selected(old('is_active', $promotion->is_active))>Bật trong khoảng thời gian đã chọn</option></select>
@include('admin.promotion-product-picker')
@push('scripts')
    <script src="{{ asset('js/promotion-product-picker.js') }}" defer></script>
@endpush
<button class="btn btn-forge mt-4">Lưu chương trình</button> <a class="btn btn-link mt-4" href="{{ route('admin.promotions.index') }}">Quay lại</a>
</form>
@endsection
