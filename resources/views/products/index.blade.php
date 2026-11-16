@extends('layouts.app')
@section('title', 'Danh mục linh kiện')
@section('content')
<div class="catalog-heading"><div><p class="eyebrow">CHỌN ĐÚNG TỪ CHI TIẾT</p><h1>Không gian linh kiện<span>.</span></h1></div><a class="btn btn-outline-dark" href="{{ route('builder.index') }}">Phương án của tôi ↗</a></div>
<p class="text-secondary catalog-intro">Thông số có nguồn đối chiếu. Giá giả lập và giá tham khảo cửa hàng được ghi nhãn riêng; chưa xác minh giá sẽ hiện Liên hệ. Nhận đơn chỉ mô phỏng, không phản ánh tồn kho thực tế.</p>
<details class="catalog-info"><summary>Về ảnh và nguồn dữ liệu</summary><p>Nguồn ảnh được ghi ở trang chi tiết. Sản phẩm đang thiếu ảnh vẫn có tên, giá và thông số để tra cứu.</p></details>
<div class="catalog-layout">
<aside class="catalog-filters"><details class="catalog-filter-panel" open><summary>Bộ lọc linh kiện <span aria-hidden="true">⌄</span></summary><form method="get" action="{{ route('products.index') }}">
<h2 class="h5">Tìm linh kiện của bạn</h2>
<label class="form-label small mt-2" for="q">Tên, mã sản phẩm hoặc hãng</label><input id="q" name="q" class="form-control" maxlength="100" placeholder="Ví dụ: 12400F, mã hãng hoặc SKU" value="{{ request('q') }}">
<label class="form-label small mt-3" for="type">Nhóm linh kiện</label><select id="type" name="type" class="form-select"><option value="">Tất cả nhóm</option>@foreach($types as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select>
<label class="form-label small mt-3" for="brand">Hãng</label><select id="brand" name="brand" class="form-select"><option value="">Tất cả hãng</option>@foreach($brands as $brand)<option @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach</select>
<label class="form-label small mt-3" for="chip_vendor">Hãng chip GPU</label><select id="chip_vendor" name="chip_vendor" class="form-select"><option value="">Tất cả hãng chip</option>@foreach(['NVIDIA', 'AMD', 'Intel'] as $chipVendor)<option @selected(request('chip_vendor') === $chipVendor)>{{ $chipVendor }}</option>@endforeach</select><p class="small text-secondary mt-1">Bộ lọc này chỉ áp dụng cho GPU có nhãn chip đã xác minh.</p>
<div class="row g-2 mt-2"><div class="col-6"><label class="form-label small" for="min_price">Giá từ</label><input class="form-control" id="min_price" name="min_price" type="number" min="0" max="1000000000" value="{{ request('min_price') }}"></div><div class="col-6"><label class="form-label small" for="max_price">Đến (VND)</label><input class="form-control" id="max_price" name="max_price" type="number" min="0" max="1000000000" value="{{ request('max_price') }}"></div></div>
<label class="form-label small mt-3" for="availability">Trạng thái</label><select id="availability" name="availability" class="form-select"><option value="">Tất cả</option>@foreach(\App\Models\Product::AVAILABILITY as $value => $label)<option value="{{ $value }}" @selected(request('availability') === $value)>{{ $label }}</option>@endforeach</select>
<label class="form-label small mt-3" for="sort">Sắp xếp</label><select id="sort" name="sort" class="form-select">@foreach(['name' => 'Tên sản phẩm', 'price_asc' => 'Giá tăng dần', 'price_desc' => 'Giá giảm dần'] as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'name') === $value)>{{ $label }}</option>@endforeach</select>
<div class="form-check my-3"><input class="form-check-input" type="checkbox" id="demo" name="demo" value="1" @checked(request()->boolean('demo'))><label class="form-check-label small" for="demo">Hiện thêm linh kiện giả lập</label></div>
<button class="btn btn-forge w-100">Áp dụng bộ lọc</button><a class="d-block text-center small mt-3" href="{{ route('products.index') }}">Đặt lại</a>
</form></details></aside>
<section aria-label="Danh sách linh kiện"><div class="d-flex justify-content-between mb-3"><strong>{{ $products->total() }} linh kiện</strong><span class="small text-secondary">Danh mục đang tiếp tục đối chiếu</span></div><div class="product-grid">
@forelse($products as $product)
@php($productImage = $product->imageDetails())
<article class="product-card" data-reveal>
    @if($productImage)
        <a class="product-art" href="{{ route('products.show', $product) }}"><img src="{{ asset($productImage['path']) }}" alt="{{ $product->imageAlt() }}" width="{{ $productImage['width'] }}" height="{{ $productImage['height'] }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" @if($loop->first) fetchpriority="high" @endif><span class="product-type">{{ $types[$product->type] }}</span></a>
    @endif
    <div class="product-card-body"><p class="small text-secondary mb-2">{{ $product->brand ?? 'Tham chiếu hãng' }} · {{ \App\Models\Product::AVAILABILITY[$product->availability] }}</p>
        <h2><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h2>
        @unless($productImage)<p class="small text-secondary">{{ $types[$product->type] }} · {{ str_starts_with($product->sku, 'DEMO-') ? 'Linh kiện giả lập' : 'Chưa có ảnh' }}</p>@endunless
        <p class="product-price">{{ $product->formattedPrice() }}</p><p class="small text-secondary">{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</p><a class="product-detail-link" href="{{ route('products.show', $product) }}">Thông số & lựa chọn <span>↗</span></a>
    </div>
</article>
@empty<div class="empty-state"><h2 class="h4">Chưa có linh kiện phù hợp.</h2><p>Thử đổi bộ lọc hoặc tìm bằng mã sản phẩm khác.</p></div>@endforelse
</div><div class="mt-4">{{ $products->links() }}</div></section>
</div>
@endsection
