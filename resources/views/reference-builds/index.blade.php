@extends('layouts.app')
@section('title', 'Cấu hình tham khảo')
@section('body_class', 'reference-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reference-builds.css') }}?v=20261104">
@endpush
@push('scripts')
    <script src="{{ asset('js/reference-builds.js') }}?v=20261104" defer></script>
@endpush
@section('content')
@php
    // Chỉ đổi lời hiển thị cho người dùng; mã trạng thái và kết quả kỹ thuật giữ nguyên.
    $statusLabels = ['PASS' => 'Đạt kiểm tra', 'WARNING' => 'Cần lưu ý', 'CONFLICT' => 'Không phù hợp'];
    $familiarWords = ['Mainboard' => 'Bo mạch chủ', 'mainboard' => 'bo mạch chủ', 'GPU' => 'card đồ họa',
        'Nguồn (PSU)' => 'Nguồn máy tính', 'PSU' => 'nguồn', 'vỏ case' => 'vỏ máy', 'case' => 'vỏ máy', 'Builder' => 'trang chọn linh kiện',
        'Socket' => 'Đế cắm', 'socket' => 'đế cắm', 'revision' => 'phiên bản'];
    $types = array_replace($types, ['mainboard' => 'Bo mạch chủ', 'gpu' => 'Card đồ họa', 'psu' => 'Nguồn máy tính']);
@endphp
    <header class="reference-heading">
        <div><p class="eyebrow">CHỌN ĐIỂM BẮT ĐẦU</p><h1>Cấu hình tham khảo</h1>
            <p class="text-secondary mb-0">So sánh các cấu hình mẫu, xem linh kiện và chỉnh lại theo nhu cầu.</p></div>
        <a class="btn btn-outline-dark" href="{{ route('builder.index') }}">Tự chọn linh kiện ↗</a>
    </header>
    <section id="reference-overview" aria-label="So sánh nhanh các cấu hình" class="reference-overview">
        @foreach($previews as $slug => $preview)
            @php
                $build = $preview['build'];
                $priorityRule = $build ? (collect($build['report']['rules'])->firstWhere('status', 'CONFLICT')
                    ?? collect($build['report']['rules'])->firstWhere('status', 'WARNING')) : null;
            @endphp
            <article class="reference-overview-card" data-overview="{{ $slug }}">
                <span class="reference-index" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <h2>{{ strtr($preview['name'], $familiarWords) }}</h2>
                <p class="reference-purpose">{{ strtr(\Illuminate\Support\Str::before($preview['purpose'], '.'), $familiarWords) }}.</p>
                @if($build === null)
                    <p class="text-warning-emphasis">Danh mục đã thay đổi. Chưa thể tính giá hoặc dùng phương án này.</p>
                @else
                    <div class="reference-metrics">
                        <span><strong>{{ count($build['selected']) }}/{{ count($types) }}</strong> nhóm đã chọn</span>
                        <span><strong>{{ $build['report']['checked_count'] }}/{{ $build['report']['rule_count'] }}</strong> mục đã kiểm tra</span>
                    </div>
                    @include('reference-builds.price-summary', ['build' => $build, 'compact' => true])
                    <div class="reference-status"><span class="status-badge status-{{ strtolower($build['report']['status']) }}">{{ $statusLabels[$build['report']['status']] }}</span>
                        <span>{{ $build['report']['selection_complete'] ? 'Đã chọn đủ nhóm' : 'Cấu hình chưa hoàn chỉnh' }}</span></div>
                    <p class="reference-priority">{{ $priorityRule ? 'Cần lưu ý: '.strtr($priorityRule['title'], $familiarWords).'.' : 'Các mục có đủ thông tin đều đã kiểm tra đạt. Xem giải thích bên dưới.' }}</p>
                @endif
                <a class="btn btn-outline-dark reference-view" href="#reference-{{ $slug }}" data-open-reference>Xem linh kiện <span aria-hidden="true">↓</span></a>
            </article>
        @endforeach
    </section>
    <aside class="reference-scope" aria-label="Phạm vi dữ liệu">
        <strong>Dữ liệu tham khảo, chưa phải báo giá mua hàng.</strong> Giá lấy từ danh sách linh kiện hiện có, chưa bảo đảm cửa hàng còn hàng.
        Chọn đủ {{ count($types) }} nhóm chưa có nghĩa là đã kiểm tra được mọi điều kiện lắp ráp. Hiệu năng thực tế có thể khác nhau.
    </aside>
    <div class="reference-detail-heading"><h2>Xem từng cấu hình</h2><span class="text-secondary">Mở khi cần · giữ đủ thông tin linh kiện và kết quả kiểm tra</span></div>
    @foreach($previews as $slug => $preview)
        @php($build = $preview['build'])
        <details class="reference-detail" id="reference-{{ $slug }}" data-reference="{{ $slug }}">
            <summary>
                <span><span class="reference-detail-title">{{ strtr($preview['name'], $familiarWords) }}</span><span class="reference-detail-caption">{{ $build ? count($build['selected']).'/'.count($types).' nhóm đã chọn' : 'Danh mục cần kiểm tra lại' }} · Xem linh kiện, nguồn giá và kết quả</span></span>
                @if($build)<span class="status-badge status-{{ strtolower($build['report']['status']) }}">{{ $statusLabels[$build['report']['status']] }}</span>@endif
            </summary>
            <div class="reference-detail-body">
                <p class="text-secondary">{{ strtr($preview['purpose'], $familiarWords) }}</p>
                @if($build === null)
                    <div class="alert alert-warning"><strong>Danh mục đã thay đổi.</strong><ul class="mb-0">@foreach($preview['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>
                @else
                    @if(! $build['report']['selection_complete'])
                        <div class="reference-partial">
                            <strong>Phương án nâng cấp gồm {{ count($build['selected']) }} nhóm linh kiện, chưa phải PC hoàn chỉnh.</strong>
                            <p class="mb-0">Còn thiếu: {{ collect($build['report']['missing_types'])->map(fn ($type) => $types[$type])->join(', ') }}.
                                @if(($build['selected']['cpu']->specifications['integrated_graphics'] ?? null) === false && ! isset($build['selected']['gpu']))
                                    CPU đang chọn không có đồ họa tích hợp; bạn cần thêm card đồ họa phù hợp.
                                @endif
                            </p>
                        </div>
                    @endif
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <table class="table align-middle reference-parts" aria-label="Linh kiện và giá — {{ $preview['name'] }}">
                                <thead><tr><th>Nhóm / linh kiện</th><th>Giá và nhận đơn mô phỏng</th></tr></thead>
                                <tbody>@foreach($build['selected'] as $type => $product)
                                    <tr><td><div class="reference-part">
                                        @if($image = $product->imageDetails())<img src="{{ asset($image['path']) }}" alt="{{ $product->imageAlt() }}" loading="lazy" width="64" height="64">@endif
                                        <div><span class="reference-meta">{{ $types[$type] }}</span><br><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a><br><span class="reference-meta">{{ $product->sku }}</span></div>
                                    </div></td>
                                        <td><strong class="reference-price">{{ $product->formattedPrice() }}</strong>
                                            <span class="reference-price-kind">{{ \App\Models\Product::PRICE_KINDS[$product->price_kind] }}</span>
                                            <span class="reference-availability @if($product->availability !== 'orderable' || $product->price_kind === 'unknown') text-warning-emphasis @endif">{{ \App\Models\Product::AVAILABILITY[$product->availability] }}</span>
                                            @if($product->price_source_url)
                                                <span class="reference-meta"><a href="{{ $product->price_source_url }}" target="_blank" rel="nofollow noopener noreferrer">{{ $product->price_retailer ?: 'Nguồn giá' }} ↗</a> · {{ $product->price_observed_on?->format('d/m/Y') }}</span>
                                            @endif
                                            @if($product->price_note)<details class="reference-price-note"><summary>Điều kiện giá</summary><p class="reference-meta mb-0">{{ $product->price_note }}</p></details>@endif
                                        </td></tr>
                                @endforeach</tbody>
                            </table>
                        </div>
                        <div class="col-lg-4">
                            <aside class="reference-purchase" aria-label="Chi phí và chọn mẫu — {{ $preview['name'] }}">
                                @include('reference-builds.price-summary', ['build' => $build, 'compact' => false])
                                <details class="reference-confirm">
                                    <summary class="btn btn-outline-dark">Dùng cấu hình này</summary>
                                    <div class="reference-confirm-body">
                                        <h3>Xác nhận thay toàn bộ lựa chọn</h3>
                                        <p>Hiện có {{ $currentPartsCount }} nhóm linh kiện được chọn; {{ $currentOwnedCount }} nhóm được đánh dấu “Tôi đã có”.</p>
                                        <p>Mẫu sẽ <strong>thay toàn bộ linh kiện đang chọn</strong> và <strong>bỏ hết dấu “Tôi đã có”</strong>. Bạn có thể đánh dấu lại các linh kiện có sẵn sau đó. Nhu cầu và giỏ hàng hiện tại vẫn được giữ.</p>
                                        @if(! $build['report']['selection_complete'])
                                            <p class="text-warning-emphasis">Chỉ áp dụng {{ count($build['selected']) }} nhóm của mẫu; không giữ lại {{ count($build['report']['missing_types']) }} nhóm còn thiếu từ cấu hình cũ.</p>
                                        @endif
                                        <form method="post" action="{{ route('reference-builds.select', $slug) }}">
                                            @csrf
                                            <input type="hidden" name="selection_token" value="{{ $selectionTokens[$slug] }}">
                                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_replace" value="1" id="confirm-{{ $slug }}" required><label class="form-check-label" for="confirm-{{ $slug }}">Tôi đã hiểu và đồng ý áp dụng mẫu.</label></div>
                                            <div class="d-flex gap-2 flex-wrap"><button class="btn btn-forge">Xác nhận áp dụng</button><button class="btn btn-outline-dark" type="reset" data-cancel-reference>Hủy</button></div>
                                        </form>
                                    </div>
                                </details>
                                <p class="reference-meta mt-3 mb-0">Chỉ thay lựa chọn sau khi bạn xác nhận. Giá và kết quả sẽ được kiểm tra lại. Nếu bạn đổi linh kiện ở tab khác, hãy tải lại trang này trước khi xác nhận.</p>
                            </aside>
                        </div>
                    </div>
                    @include('reference-builds.report', ['report' => $build['report'], 'slug' => $slug])
                @endif
                <a href="#reference-overview" class="reference-back">↑ Trở lại so sánh nhanh</a>
            </div>
        </details>
    @endforeach
@endsection
