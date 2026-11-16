@php
    $typeNames = ['cpu' => 'Bộ xử lý (CPU)', 'mainboard' => 'Bo mạch chủ', 'ram' => 'Bộ nhớ RAM', 'gpu' => 'Card đồ họa', 'psu' => 'Nguồn máy tính', 'storage' => 'Ổ lưu trữ', 'cooler' => 'Tản nhiệt', 'case' => 'Vỏ máy'];
    $editing = $product->exists;
    $oldText = fn ($key) => is_scalar(old($key, $product->$key)) ? old($key, $product->$key) : '';
    $submittedSpecifications = old('specifications', []);
    $specificationValue = fn ($key) => is_array($submittedSpecifications) && array_key_exists($key, $submittedSpecifications)
        ? $submittedSpecifications[$key] : ($savedSpecifications[$key] ?? null);
    $priceKinds = ['manual' => 'Giá do quản trị nhập', 'simulated' => 'Giá minh họa', 'unknown' => 'Chưa có giá — hiển thị Liên hệ'];
    if ($product->price_kind === 'retail_reference') {
        $priceKinds = ['retail_reference' => 'Giá tham khảo từ cửa hàng'] + $priceKinds;
    }
@endphp
<div class="product-create">
    <header class="my-3">
        <p class="eyebrow">QUẢN LÝ LINH KIỆN</p>
        <h1 class="h2">{{ $editing ? 'Sửa linh kiện' : 'Thêm linh kiện' }}</h1>
        <p class="text-secondary">{{ $editing ? $product->name : 'Chọn loại linh kiện rồi điền thông tin theo hướng dẫn. Thông số chưa biết có thể bổ sung sau.' }}</p>
    </header>
    @if($editing)
        @if($image = $product->imageDetails())
            <figure class="d-flex align-items-center gap-3 mb-4">
                <img class="product-thumbnail" src="{{ asset($image['path']) }}" alt="{{ $product->imageAlt() }}" width="96" height="96">
                <figcaption class="small">Ảnh đang hiển thị<br><a href="{{ $image['source_page_url'] }}">{{ $image['source_owner'] }}</a>
                    @if($image['kind'] === 'licensed_photo') · <a href="{{ $image['license_url'] }}">{{ $image['license_name'] }}</a>@endif
                </figcaption>
            </figure>
        @endif
        <section class="summary-card mb-4">
            <h2 class="h5">1. Loại linh kiện</h2>
            <p class="mb-2">{{ $typeNames[$selectedType] }}</p>
            <p class="form-text mb-0">Mỗi loại có thông số riêng. Màn này giữ nguyên loại để tránh mất thông số đang dùng.</p>
        </section>
    @else
    <form method="get" action="{{ route('admin.products.create') }}" id="product-type-picker" class="summary-card mb-4">
        <h2 class="h5">1. Chọn loại linh kiện</h2>
        <label class="form-label" for="product-type-choice">Bạn muốn thêm loại nào?</label>
        <select class="form-select" id="product-type-choice" name="type">@foreach($typeNames as $key => $label)<option value="{{ $key }}" @selected($selectedType === $key)>{{ $label }}</option>@endforeach</select>
        <button class="btn btn-outline-dark mt-3" type="submit">Áp dụng loại linh kiện</button>
        <noscript><p class="mt-2">Hãy chọn loại và bấm Áp dụng trước khi điền thông tin bên dưới.</p></noscript>
    </form>
    @endif
    <form method="post" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" id="product-create-form">
        @csrf
        @if($editing)
            @method('PUT')
            <input type="hidden" name="edit_version" value="{{ $editVersion }}">
        @endif
        <input type="hidden" name="type" id="product-input-type" value="{{ $selectedType }}">
        <input type="hidden" name="specification_input" value="fields">
        <section class="summary-card mb-4" aria-labelledby="product-basic-title">
            <h2 class="h5" id="product-basic-title">2. Thông tin linh kiện</h2>
            @if($editing)
                <p class="form-text">Chỉ đổi tên, mã hoặc hãng khi cần đính chính. Ảnh chỉ xuất hiện khi thông tin khớp với mẫu đã xác minh.</p>
            @endif
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label" for="name">Tên linh kiện *</label><input class="form-control" id="name" name="name" maxlength="200" value="{{ $oldText('name') }}" required placeholder="Ví dụ: Intel Core i5-12400F"></div>
                <div class="col-md-4"><label class="form-label" for="sku">Mã linh kiện *</label><input class="form-control" id="sku" name="sku" maxlength="60" value="{{ $oldText('sku') }}" required placeholder="Ví dụ: CPU-I5-12400F"><p class="form-text">Mỗi linh kiện có một mã riêng, không trùng mã đã có.</p></div>
                <div class="col-md-6"><label class="form-label" for="brand">Hãng sản xuất</label><input class="form-control" id="brand" name="brand" maxlength="60" value="{{ $oldText('brand') }}" placeholder="Ví dụ: Intel, ASUS, Kingston"></div>
                <div class="col-md-6"><label class="form-label" for="is_active">Hiển thị trên website</label><select class="form-select" id="is_active" name="is_active"><option value="1" @selected(old('is_active', $product->is_active) == '1')>Hiển thị</option><option value="0" @selected(old('is_active', $product->is_active) == '0')>Tạm ẩn</option></select></div>
                <div class="col-md-6"><label class="form-label" for="price_kind">Giá này là</label><select class="form-select" id="price_kind" name="price_kind">@foreach($priceKinds as $key => $label)<option value="{{ $key }}" @selected(old('price_kind', $product->price_kind ?? 'manual') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="price">Giá bán (đồng)</label><input class="form-control" id="price" name="price" type="number" min="0" max="1000000000" step="1" value="{{ $oldText('price') }}" placeholder="Ví dụ: 3990000"><p class="form-text">Nhập số tiền không có dấu phân cách. Chưa có giá thì chọn mục tương ứng bên cạnh.</p></div>
                @if($product->price_source_url)
                    <div class="col-12 small">
                        <p class="mb-1">Nguồn giá đang lưu là <a href="{{ $product->price_source_url }}" rel="nofollow noopener noreferrer" target="_blank">{{ $product->price_retailer }}</a>, đối chiếu ngày {{ $product->price_observed_on?->format('d/m/Y') }}.</p>
                        @if($product->price_note)<p class="mb-1">{{ $product->price_note }}</p>@endif
                        <p class="form-text mb-0">Nếu đổi số tiền, hệ thống sẽ bỏ nguồn cũ và chuyển sang giá do quản trị nhập, trừ khi bạn chọn Giá minh họa hoặc Chưa có giá.</p>
                    </div>
                @endif
                <div class="col-12"><label class="form-label" for="availability">Nhận đơn trên TECHFORGE</label><select class="form-select" id="availability" name="availability">@foreach(\App\Models\Product::AVAILABILITY as $key => $label)<option value="{{ $key }}" @selected(old('availability', $product->availability ?? 'orderable') === $key)>{{ $label }}</option>@endforeach</select><p class="form-text">Chưa có giá sẽ hiển thị Liên hệ và chưa nhận đơn. Đây là đơn mô phỏng, không thể hiện tồn kho cửa hàng.</p></div>
                <div class="col-12"><label class="form-label" for="data_source">Nguồn thông tin *</label><textarea class="form-control" id="data_source" name="data_source" maxlength="1000" rows="2" required placeholder="Dán liên kết hãng/cửa hàng, hoặc ghi rõ đây là dữ liệu minh họa.">{{ $oldText('data_source') }}</textarea></div>
            </div>
        </section>
        <section class="summary-card mb-4" aria-labelledby="product-specs-title">
            <h2 class="h5" id="product-specs-title">3. Thông số linh kiện</h2>
            <p class="text-secondary">Chép thông tin từ hãng hoặc cửa hàng. Chưa biết thì để trống; không điền số 0 thay cho chưa biết. Số 0 cổng/khe nghĩa là xác nhận không có.</p>
            @foreach(($editing ? [$selectedType => $specificationFields[$selectedType]] : $specificationFields) as $type => $fields)
                <fieldset data-product-fields="{{ $type }}" @disabled($type !== $selectedType) @if($type !== $selectedType) hidden @endif>
                    <legend class="h6 mb-3">{{ $typeNames[$type] }}</legend>
                    <div class="row g-3">
                        @foreach($fields as $key => $field)
                            @unless($field['extra']) @include('admin.product-specification-field', ['value' => $type === $selectedType ? $specificationValue($key) : null]) @endunless
                        @endforeach
                    </div>
                    <details class="product-extra-fields mt-4" @if($editing && $errors->has('specifications.*')) open @endif><summary>{{ $editing ? 'Xem và sửa thông tin bổ sung' : 'Thêm thông tin bổ sung (không bắt buộc)' }}</summary><div class="row g-3 mt-1">
                        @foreach($fields as $key => $field)
                            @if($field['extra']) @include('admin.product-specification-field', ['value' => $type === $selectedType ? $specificationValue($key) : null]) @endif
                        @endforeach
                    </div></details>
                </fieldset>
            @endforeach
        </section>
        <p class="text-secondary">Các mục có dấu * là bắt buộc. Khi lưu, hệ thống kiểm tra thông số; để trống không đồng nghĩa đã phù hợp để lắp máy.</p>
        <div class="d-flex gap-3 align-items-center mb-4"><button class="btn btn-forge" type="submit">{{ $editing ? 'Lưu thay đổi' : 'Lưu linh kiện' }}</button><a class="btn btn-outline-dark" href="{{ route('admin.index') }}">Về quản trị</a></div>
    </form>
</div>
