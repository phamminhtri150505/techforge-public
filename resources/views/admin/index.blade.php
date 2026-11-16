@extends('layouts.app')
@section('title', 'Quản trị')
@section('body_class', 'admin-dashboard')
@section('content')
    <p class="eyebrow mt-3">
        QUẢN TRỊ TECHFORGE</p>
    <h1 class="h2 mb-3">Danh mục và đơn mô phỏng</h1>
    <a class="btn btn-outline-dark" href="{{ route('admin.promotions.index') }}">Quản lý ưu đãi bộ →</a>
    <section class="summary-card my-4">
        <div class="section-heading">
            <h2 class="h4">Sản phẩm</h2><a class="btn btn-dark btn-sm" href="{{ route('admin.products.create') }}">Thêm sản
                phẩm</a>
        </div>
        <p class="small text-secondary">Ngừng hiển thị bằng trạng thái “Ẩn”. Không xóa dữ liệu đã dùng trong cấu hình/đơn
            hàng.</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>SKU / sản phẩm</th>
                        <th>Loại</th>
                        <th>Giá (VND)</th>
                        <th>Hiển thị</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2">
                                @if($image = $product->imageDetails())<img class="product-thumbnail" src="{{ asset($image['path']) }}" alt="" width="42" height="42" loading="lazy">@endif
                                <div><small>{{ $product->sku }}</small><br>{{ $product->name }}</div>
                            </div></td>
                            <td>{{ $product->type }}</td>
                            <td>{{ $product->formattedPrice() }}<br><small>{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</small></td>
                            <td>{{ $product->is_active ? 'Có' : 'Ẩn' }}</td>
                            <td><a href="{{ route('admin.products.edit', $product) }}">Sửa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>{{ $products->links() }}
    </section>
    <section class="summary-card my-4">
        <div class="section-heading">
            <h2 class="h4">Bài viết</h2>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.articles.inbox') }}">
                    Tin chờ duyệt @if ($hasNewNewsDigest)<span class="badge bg-success ms-1">Có bản tổng hợp mới</span>@endif
                </a>
                <a class="btn btn-dark btn-sm" href="{{ route('admin.articles.create') }}">Thêm bài viết</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tiêu đề</th>
                        <th>Chuyên mục</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr>
                            <td>{{ $article->title }}</td>
                            <td>{{ $article->category }}</td>
                            <td>{{ $article->is_published ? 'Công khai' : 'Bản nháp' }}</td>
                            <td><a href="{{ route('admin.articles.edit', $article) }}">Sửa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>{{ $articles->links() }}
    </section>
    <section class="summary-card my-4">
        <h2 class="h4 mb-3">Đơn COD mô phỏng</h2>
        <p class="small text-secondary">Chờ xác nhận → Đã xác nhận → Đang giao → Hoàn tất. Được hủy trước khi giao; trạng
            thái kết thúc không được mở lại.</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Mã / người nhận</th>
                        <th>Tổng</th>
                        <th>Hiện tại</th>
                        <th>Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>#{{ $order->id }} · {{ $order->recipient }}<br><small>{{ $order->phone }} ·
                                    {{ $order->address }}</small></td>
                            <td class="text-nowrap">{{ number_format($order->total, 0, ',', '.') }} ₫</td>
                            <td>{{ \App\Models\Order::STATUSES[$order->status] }}</td>
                            <td>
                                @if ($order->availableTransitions())
                                <form method="post" action="{{ route('admin.orders.update', $order) }}"
                                    class="d-flex gap-2">@csrf @method('PATCH')<select class="form-select form-select-sm"
                                        name="status" aria-label="Trạng thái đơn {{ $order->id }}" required>
                                        <option value="" selected disabled>Chọn bước tiếp theo</option>
                                        @foreach ($order->availableTransitions() as $value => $label)
                                            <option value="{{ $value }}">
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-dark btn-sm">Lưu</button>
                                </form>
                                @else
                                    <span class="small text-secondary">Đã kết thúc</span>
                                @endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="4">Chưa có đơn.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>{{ $orders->links() }}
    </section>
@endsection
