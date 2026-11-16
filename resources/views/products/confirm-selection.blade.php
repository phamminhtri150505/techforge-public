<section class="product-replacement" aria-labelledby="replace-product-title">
    <p class="eyebrow">Kiểm tra thay đổi</p><h2 id="replace-product-title">Thay {{ $types[$product->type] }} đang chọn?</h2>
    <dl><div><dt>Hiện tại</dt><dd>{{ $currentProduct?->name ?? 'Linh kiện đã chọn không còn trong danh mục' }}</dd></div><div><dt>Sắp chọn</dt><dd>{{ $product->name }}</dd></div><div><dt>Trạng thái sau khi thay</dt><dd>{{ $confirmation['ownership'] === 'owned' ? 'Đã sở hữu — không tính chi phí mua thêm' : 'Cần mua thêm — không kế thừa dấu đã sở hữu' }}</dd></div></dl>
    <p>Các nhóm khác, nhu cầu và giỏ hàng được giữ nguyên. Giá và kết quả sẽ được tính lại từ dữ liệu hiện tại.</p>
    <div class="product-confirm-actions">
        <form method="post" action="{{ route('products.builder', $product) }}">@csrf
            <input type="hidden" name="ownership" value="{{ $confirmation['ownership'] }}"><input type="hidden" name="selection_token" value="{{ $confirmation['selection_token'] }}"><input type="hidden" name="confirm_replace" value="1">
            <button class="btn btn-forge">Xác nhận thay linh kiện</button>
        </form>
        <a class="btn btn-outline-dark" href="{{ route('products.show', $product) }}">Hủy, giữ cấu hình hiện tại</a>
    </div>
</section>
