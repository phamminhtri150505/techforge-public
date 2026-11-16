<div class="reference-cost" data-reference-cost>
    <p class="reference-meta mb-1">{{ count($build['selected']) < count($types) ? 'Tạm tính các linh kiện đã chọn' : 'Tổng giá tham khảo' }}</p>
    @if(count($build['unpriced_types']) === count($build['selected']))
        <p class="reference-total">Liên hệ</p>
    @else
        <p class="reference-total">{{ number_format($build['total'], 0, ',', '.') }} ₫</p>
    @endif
    @if($build['unpriced_types'])
        <p class="reference-price-warning">Chưa đủ giá: {{ collect($build['unpriced_types'])->map(fn ($type) => $types[$type])->join(', ') }}. Tổng chưa đầy đủ, không phải miễn phí.</p>
    @elseif(count($build['selected']) < count($types))
        <p class="reference-meta">Chi phí {{ count($build['selected']) }} nhóm, không phải giá một PC đầy đủ.</p>
    @endif
    @if($build['discount']['amount'])
        <p class="reference-meta">Đã trừ ưu đãi hiện tại {{ number_format($build['discount']['amount'], 0, ',', '.') }} ₫; chỉ xét linh kiện cần mua, giỏ sẽ tính lại khi đặt.</p>
    @endif
    @if(collect($build['selected'])->contains(fn ($product) => $product->price_kind === 'simulated'))
        <p class="reference-meta">Có giá giả lập; xem loại giá ở từng linh kiện.</p>
    @endif
    @if(collect($build['selected'])->contains(fn ($product) => $product->availability !== 'orderable' || $product->price_kind === 'unknown'))
        <p class="reference-price-warning">{{ $compact ? 'Chưa thể đặt toàn bộ mẫu trong giỏ mô phỏng.' : 'Có linh kiện cần liên hệ, chưa xác minh giá hoặc tạm ngừng nhận đơn; chưa thể mua toàn bộ phương án trong giỏ mô phỏng.' }}</p>
    @endif
</div>
