@php
    $typeLabels = ['cpu' => 'Bộ xử lý (CPU)', 'mainboard' => 'Bo mạch chủ', 'ram' => 'Bộ nhớ RAM', 'gpu' => 'Card đồ họa',
        'psu' => 'Nguồn máy tính', 'storage' => 'Ổ lưu trữ', 'cooler' => 'Tản nhiệt', 'case' => 'Vỏ máy'];
    $typeOrder = array_flip(array_keys($typeLabels));
    $savedIds = $promotion->required_product_ids ?? [];
    // Khi bỏ hết dấu tick, trình duyệt không gửi trường này. Dùng lựa chọn trống nếu đang mở lại form sau khi gửi.
    $hasOldInput = session()->hasOldInput();
    $checkedIds = collect((array) old('required_product_ids', $hasOldInput ? [] : $savedIds))
        ->filter(fn ($id) => is_string($id) || is_int($id))->map(fn ($id) => (string) $id)->all();
@endphp
<fieldset class="mt-4" data-promotion-picker>
    <legend class="h5">Sản phẩm áp dụng</legend>
    <p class="text-secondary">Chọn từ 2 đến 8 linh kiện cần mua cùng nhau để nhận mức giảm cố định.</p>
    @if (array_diff($savedIds, $products->modelKeys()))
        <p class="text-warning">Có linh kiện đã lưu không còn trong danh mục. Kiểm tra lại các mục được chọn trước khi lưu.</p>
    @endif

    @if ($products->isNotEmpty())
        <div class="mb-3" data-promotion-search hidden>
            <label class="form-label" for="promotion-product-search">Tìm linh kiện</label>
            <input class="form-control" id="promotion-product-search" type="search" maxlength="120" placeholder="Nhập tên hoặc mã linh kiện">
        </div>
        <div class="mb-3" data-promotion-selection hidden>
            <p class="fw-semibold mb-1" data-promotion-count role="status"></p>
            <ul class="list-inline mb-2" data-promotion-selected></ul>
            <p class="text-secondary small mb-0" data-promotion-limit hidden>Đã đủ 8 linh kiện. Bỏ chọn một mục để thêm mục khác.</p>
        </div>
        <p class="text-secondary mb-3" data-promotion-no-results hidden>Không tìm thấy linh kiện phù hợp. Hãy thử tên hoặc mã khác.</p>
    @endif

    <div class="row g-3">
        @forelse ($products->groupBy('type')->sortBy(fn ($group, $type) => $typeOrder[$type] ?? count($typeOrder)) as $type => $group)
            <div class="col-md-6" data-promotion-group>
                <details class="border rounded-3 p-3" @if ($hasOldInput && $group->contains(fn ($product) => in_array((string) $product->id, $checkedIds, true))) open @endif>
                    <summary class="fw-semibold">{{ $typeLabels[$type] ?? $type }} <span class="text-secondary small">· {{ $group->count() }} linh kiện</span></summary>
                    <div class="mt-3">
                        @foreach ($group as $product)
                            <div class="form-check mb-2" data-promotion-product>
                                <input class="form-check-input" type="checkbox" name="required_product_ids[]" id="product-{{ $product->id }}" value="{{ $product->id }}" @checked(in_array((string) $product->id, $checkedIds, true))>
                                <label class="form-check-label text-break" for="product-{{ $product->id }}">
                                    <span data-product-name>{{ $product->name }}</span> <small class="text-secondary" data-product-code>{{ $product->sku }}</small>
                                    @unless ($product->is_active)
                                        <span class="badge text-bg-secondary">Đã ẩn</span>
                                    @endunless
                                </label>
                            </div>
                        @endforeach
                    </div>
                </details>
            </div>
        @empty
            <p class="col-12 text-secondary mb-0">Chưa có linh kiện đang hiển thị để chọn.</p>
        @endforelse
    </div>
</fieldset>
