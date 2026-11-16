@extends('layouts.app')
@section('title', 'PC Builder')
@section('meta_description', 'Chọn linh kiện, giữ lại phần đã có, tính chi phí mua thêm và kiểm tra các ràng buộc của cấu hình PC.')
@section('main_class', 'pb-5')
@section('body_class', 'builder-page')
@section('custom_notices', '1')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/builder-experience.css') }}?v=20261112-dark-premium">
@endpush
@push('scripts')
    <script src="{{ asset('js/builder.js') }}?v=20261114-photos" defer></script>
    <script src="{{ asset('js/recommendations.js') }}?v=20261111-needs" defer></script>
@endpush
@section('content')
        <section data-reveal class="hero row align-items-center g-4">
            <div class="col-lg-8">
                <p class="eyebrow">KHÔNG GIAN LẮP RÁP CỦA BẠN</p>
                <h1>Build mới.<br><span>Nâng cấp theo cách bạn.</span></h1>
                <p class="hero-description">Giữ lại thứ bạn đã có, chọn thêm thứ bạn cần. Cùng một không gian để kiểm tra linh kiện và tính chi phí mua thêm.</p>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('reference-builds.index') }}">Cấu hình tham khảo từ linh kiện thật ↗</a>
            </div>
            <div class="col-lg-4">
                <div class="hero-note"><span class="note-index">01 / PC BUILDER</span>
                    <p>Phác thảo chiếc PC<br>theo từng lựa chọn.</p>
                    <div class="rig-map">@foreach($types as $type => $label)<button type="button" class="rig-node {{ $previewProducts[$type] ? 'chosen' : '' }} {{ $previewProducts[$type] && in_array($type, $previewOwned, true) ? 'owned' : '' }}" data-rig-type="{{ $type }}" aria-label="Đến nhóm {{ $label }} — {{ ! $previewProducts[$type] ? 'Chưa chọn' : (in_array($type, $previewOwned, true) ? 'Đã có' : 'Đã chọn') }}"><img src="{{ asset('images/components/'.$type.'.svg') }}" alt=""><span>{{ $label }}</span></button>@endforeach</div>
                    <span class="small">Sơ đồ minh họa · chọn một nhóm để chỉnh</span>
                </div>
            </div>
        </section>
        <nav class="builder-wayfinding" aria-label="Các bước xây dựng cấu hình"><a href="#catalog"><b>01</b>Chọn linh kiện</a><a href="#report"><b>02</b>Kiểm tra</a><a href="#assistant"><b>03</b>Ngân sách & nhu cầu</a></nav>
        <div class="demo-disclaimer"><span class="small-dot"></span><strong>Về dữ liệu linh kiện</strong><span>Danh mục có giá tham khảo và dữ liệu minh họa được ghi rõ ở từng sản phẩm.
                Chưa xác minh toàn bộ BIOS và tồn kho; tổng tạm tính chưa phải báo giá mua hàng.</span></div>
        @if (session('notice'))
            <div class="alert alert-light border mt-3" role="status">{{ session('notice') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mt-3" role="alert"><strong>Chưa cập nhật được cấu hình.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="row g-4 mt-2 align-items-start">
            <section class="col-lg-8" id="catalog" aria-labelledby="catalog-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow mb-1">BƯỚC 01</p>
                        <h2 id="catalog-title">Chọn linh kiện</h2>
                    </div><button type="button" id="demo-button" class="btn btn-outline-dark btn-sm">Dùng cấu hình mẫu
                        ↗</button>
                </div>
                <form method="post" action="{{ route('builder.check') }}" id="builder-form" data-pending="{{ $hasPendingInput ? '1' : '0' }}">
                    @csrf
                    @foreach ($types as $type => $label)
                        @php
                            $groupProducts = $catalog->get($type, collect());
                            $previewProduct = $previewProducts[$type];
                            $previewImage = $previewProduct?->imageDetails();
                            $isOwned = $previewProduct && in_array($type, $previewOwned, true);
                        @endphp
                        <article class="component-card" data-component-type="{{ $type }}">
                            <div class="component-content">
                                <div class="d-flex justify-content-between gap-3 mb-2"><label
                                        for="part-{{ $type }}"
                                        class="component-label">{{ $label }}</label><span
                                        class="component-number">{{ sprintf('%02d', $loop->iteration) }} / 08</span>
                                </div>
                                <div class="part-preview @unless($previewImage) part-preview-text-only @endunless" aria-live="polite" aria-atomic="true">
                                    <figure class="part-preview-art mb-0" @unless($previewImage) hidden @endunless>
                                        @if($previewImage)<img data-preview-image src="{{ asset($previewImage['path']) }}" alt="{{ $previewProduct->imageAlt() }}" width="110" height="100" loading="lazy" decoding="async">@endif
                                        <figcaption data-preview-caption>{{ $previewImage ? $previewProduct->imageCaption() : '' }}</figcaption>
                                    </figure>
                                    <div class="part-preview-content">
                                        <span class="part-state" data-preview-state>{{ ! $previewProduct ? 'Chưa chọn' : ($isOwned ? 'Đã có' : ($previewProduct->price_kind === 'unknown' || $previewProduct->availability === 'contact' ? 'Đã chọn · Liên hệ' : 'Đã chọn · cần mua')) }}</span>
                                        <p class="part-preview-name" data-preview-name>{{ $previewProduct?->name ?? 'Chọn một mã trong danh mục '.$label }}</p>
                                        <p class="part-preview-specs" data-preview-specs>{{ $previewProduct ? $previewProduct->builderSummary() : 'Có thể bỏ trống nhóm này. Thông số sẽ hiện sau khi chọn linh kiện.' }}</p>
                                        <p class="part-preview-price mb-1" data-preview-price>{{ $previewProduct?->formattedPrice() ?? 'Chưa có lựa chọn' }}</p>
                                        <p class="small text-secondary mb-1" data-preview-price-kind>{{ $previewProduct ? \App\Models\Product::PRICE_KINDS[$previewProduct->price_kind] : '' }}</p>
                                        <p class="small text-secondary mb-1" data-preview-availability>{{ $previewProduct ? \App\Models\Product::AVAILABILITY[$previewProduct->availability].' · không phải tồn kho thực tế' : '' }}</p>
                                        <a class="small" data-preview-detail href="{{ $previewProduct ? route('products.show', $previewProduct) : '#' }}" @if(! $previewProduct) hidden @endif>Xem đầy đủ thông số & nguồn ↗</a>
                                    </div>
                                </div>
                                <details class="part-picker mt-3" open>
                                    <summary><span data-picker-label>{{ $previewProduct ? 'Thay linh kiện' : 'Chọn linh kiện' }}</span> <span class="small text-secondary">· {{ $groupProducts->count() }} mã</span></summary>
                                    <div class="part-search mt-2" hidden>
                                        <label class="small form-label" for="search-{{ $type }}">Tìm nhanh trong nhóm {{ $label }}</label>
                                        <div class="d-flex gap-2"><input type="search" class="form-control form-control-sm" id="search-{{ $type }}" data-part-search placeholder="Tên, hãng, mã hoặc thông số" autocomplete="off" aria-controls="part-{{ $type }}" aria-describedby="search-result-{{ $type }}"><button type="button" class="btn btn-outline-secondary btn-sm" data-search-clear>Xóa tìm</button></div>
                                        <p class="small text-secondary mt-1 mb-2" id="search-result-{{ $type }}" data-search-result role="status">{{ $groupProducts->count() }} mã trong nhóm.</p>
                                    </div>
                                <select class="form-select part-select mt-2" id="part-{{ $type }}"
                                    name="parts[{{ $type }}]">
                                    <option value="">Chưa chọn {{ $label }}</option>
                                    @foreach ($groupProducts as $product)
                                        @php($image = $product->imageDetails())
                                        <option value="{{ $product->id }}" data-price="{{ $product->price }}"
                                            data-price-known="{{ $product->price_kind === 'unknown' ? '0' : '1' }}"
                                            data-name="{{ $product->name }}"
                                            data-specs="{{ $product->builderSummary() }}"
                                            data-search="{{ $product->name.' '.$product->brand.' '.$product->sku.' '.$product->manufacturer_code.' '.$product->builderSummary() }}"
                                            @if($image)
                                                data-image="{{ asset($image['path']) }}"
                                                data-image-alt="{{ $product->imageAlt() }}"
                                                data-image-caption="{{ $product->imageCaption() }}"
                                            @endif
                                            data-detail-url="{{ route('products.show', $product) }}"
                                            data-formatted-price="{{ $product->formattedPrice() }}"
                                            data-price-kind="{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}"
                                            data-availability="{{ $product->availability }}"
                                            data-availability-label="{{ \App\Models\Product::AVAILABILITY[$product->availability] }}"
                                            data-reference="{{ $product->sku === 'DEMO-' . strtoupper($type) . '-A' ? '1' : '0' }}"
                                            @selected($previewProduct?->id === $product->id)>{{ $product->name }} —
                                            {{ $product->formattedPrice() }} · {{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</option>
                                    @endforeach
                                </select>
                                </details>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                                    <div class="form-check"><input type="checkbox" class="form-check-input owned-check" name="owned[]" value="{{ $type }}" id="owned-{{ $type }}" @checked(in_array($type, $previewOwned, true))><label class="form-check-label small" for="owned-{{ $type }}">Tôi đã có {{ $label }}</label></div>
                                    <a class="small" href="{{ route('products.index', ['type' => $type]) }}">Khám phá danh mục ↗</a>
                                </div>
                                @error('parts.' . $type)
                                    <p class="text-danger small mt-2 mb-0">{{ $message }}</p>
                                @enderror
                            </div>
                        </article>
                    @endforeach
                    <button class="btn btn-forge w-100 py-3 mt-3" type="submit">Cập nhật & kiểm tra cấu hình <span
                            aria-hidden="true">→</span></button>
                    <p class="small text-secondary mt-2">Nhấn “Cập nhật & kiểm tra” để gửi lựa chọn. Có thể để trống nhóm chưa biết. Tích “Tôi đã có” sau khi chọn đúng mã sản phẩm; nhóm đó vẫn tham gia kiểm tra và không tính vào chi phí mua thêm. RAM là một kit; tản nhiệt là tản khí. Ảnh chỉ giúp nhận diện, không xác nhận thông số hay tương thích.</p>
                </form>
            </section>
            <aside class="col-lg-4 summary-column" aria-label="Tổng quan cấu hình">
                <section class="summary-card">
                    <div class="d-flex justify-content-between">
                        <p class="eyebrow">CẤU HÌNH CỦA BẠN</p><span class="live-dot">KIỂM TRA KỸ THUẬT</span>
                    </div>
                    <h2>Chi phí mua thêm.</h2>
                    <button type="submit" form="builder-form" class="btn btn-forge w-100 mb-3">Cập nhật &amp; kiểm tra</button>
                    <p class="text-secondary small">Linh kiện đã có không tính tiền. Giá giả lập/tham khảo theo từng mã, đơn vị Việt Nam đồng.</p>
                    <div class="price-total" id="preview-total">{{ number_format($total, 0, ',', '.') }} <span>₫</span>
                    </div>
                    <p class="small text-secondary" id="price-note">{{ $hasPendingInput ? 'Giá trong tổng quan thuộc lần kiểm tra trước; biểu mẫu chưa được cập nhật thành công.' : ($unpriced_types ? 'Chưa đủ giá phần cần mua — tạm tính linh kiện đã có giá, cần liên hệ' : 'Tổng giá theo linh kiện cần mua') }}</p>
                    <p class="text-warning-emphasis small" id="unpriced-purchase-note" @if(! $unpriced_types) hidden @endif>Chưa có giá phần cần mua: {{ implode(', ', array_map(fn($type) => $types[$type], $unpriced_types)) }}.</p>
                    @if($discount['amount'] > 0)<div class="deal-note"><strong>{{ $discount['promotion']['name'] }}</strong><br>Giảm {{ number_format($discount['amount'], 0, ',', '.') }} ₫ trên phần cần mua</div>@endif
                    <p class="small text-secondary" id="configuration-value">Giá trị tham chiếu toàn bộ lựa chọn: {{ number_format($configuration_total, 0, ',', '.') }} ₫ · {{ count($owned) }} nhóm đã có</p>
                    <p class="small text-secondary" id="unpriced-selection-note" @if(! $unpriced_selection) hidden @endif>Giá trị toàn bộ chưa đầy đủ vì còn linh kiện Liên hệ; linh kiện đã có vẫn tham gia kiểm tra kỹ thuật.</p>
                    <div class="progress mb-2" role="progressbar" aria-label="Số nhóm đã chọn"
                        aria-valuenow="{{ count($selected) }}" aria-valuemin="0" aria-valuemax="8">
                        <div class="progress-bar" id="selection-progress"
                            style="width: {{ (count($selected) / 8) * 100 }}%"></div>
                    </div>
                    <p class="small mb-4"><strong id="selection-count">{{ count($selected) }}</strong> / 8 nhóm linh
                        kiện đã chọn</p>
                    <div id="pending-note" class="alert alert-warning small" role="status" @if(! $hasPendingInput) hidden @endif>Lựa chọn trong biểu mẫu chưa được gửi thành công. Nhấn “Cập nhật & kiểm tra” để tính giá, ưu đãi và nhận kết quả mới. Báo cáo và thao tác lưu/giỏ đang dùng lần kiểm tra trước.</div>
                    <a class="btn btn-dark w-100" href="#report">Xem kết quả kiểm tra ↓</a>
                    <form action="{{ route('builder.reset') }}" method="post" class="mt-2">@csrf
                        @method('DELETE')<button class="btn btn-link text-secondary w-100 small">Bỏ tất cả lựa
                            chọn</button></form>
                    <hr>
                    <p class="small text-secondary">Lựa chọn được giữ trong phiên trình duyệt. Lưu hoặc thêm vào giỏ sẽ
                        dùng cấu hình đã kiểm tra gần nhất; hãy cập nhật sau khi đổi linh kiện.</p>
                    @auth
                        <form action="{{ route('configurations.store') }}" method="post" class="mb-3">@csrf<label
                                for="configuration-name" class="form-label small">Tên bản lưu</label><input
                                id="configuration-name" class="form-control mb-2" name="name" maxlength="100"
                                placeholder="Ví dụ: PC học tập" required><button class="btn btn-outline-dark w-100"
                                data-requires-current @disabled($hasPendingInput)>Lưu cấu hình đã kiểm tra</button></form>
                @else<a href="{{ route('login') }}" class="d-block mb-3">Đăng nhập để lưu cấu hình</a>@endauth
                <form action="{{ route('cart.store') }}" method="post">@csrf<button
                        class="btn btn-outline-dark w-100" data-requires-current @disabled($hasPendingInput)>Đưa cấu hình đã kiểm tra vào
                        giỏ</button><p class="small text-secondary mt-2">Chuyển phần cần mua và thay giỏ hiện tại, kể cả các dòng mua lẻ đã thêm.</p></form>
            </section>
            <section class="explanation-card mt-3"><span class="eyebrow">HIỂU KẾT QUẢ</span>
                <p><strong>PASS</strong> · Vượt qua các luật có đủ dữ liệu.</p>
                <p><strong>WARNING</strong> · Cần lưu ý hoặc bổ sung dữ liệu.</p>
                <p class="mb-0"><strong>CONFLICT</strong> · Có ràng buộc kỹ thuật bị vi phạm.</p>
            </section>
        </aside>
    </div>
    <div class="builder-checked-results {{ $hasPendingInput ? 'is-stale' : '' }}">
        @include('builder.guided-report')
        <fieldset class="border-0 p-0 m-0" data-assistant-fields @disabled($hasPendingInput)>
            @include('builder.assistant')
        </fieldset>
    </div>
    <section id="scope" class="scope-card">
        <div>
            <p class="eyebrow">TRƯỚC KHI LẮP RÁP</p>
            <h2>Hiểu phạm vi kiểm tra của cấu hình.</h2>
            <p>Đọc cảnh báo của từng linh kiện và đối chiếu hướng dẫn từ nhà sản xuất trước khi lắp máy.</p>
            <details>
                <summary>Những điều đã kiểm tra và cần đối chiếu thêm</summary>
                <p class="mt-2">Kiểm tra socket, CPU support/BIOS, RAM, kích thước lắp đặt, lưu trữ và mức nguồn
                    khuyến nghị. Chưa xét đầu nguồn, lane PCIe, QVL, radiator, khoảng hở RAM và hiệu năng thực tế.
                    Chuẩn ổ M.2 được kiểm tra theo thông số ổ và khe cắm đã lưu trong danh mục.</p>
            </details>
        </div>
    </section>
@endsection
