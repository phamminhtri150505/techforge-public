@if($recommendations && ! $recommendationErrors->any())
    @php
        $result = $recommendations['result'];
        $criteria = $recommendations['requirements'];
        $money = fn ($amount) => number_format($amount, 0, ',', '.').' ₫';
        $productsById = $catalog->collapse()->keyBy('id');
    @endphp
    <div class="recommendation-results mt-4">
        <h3 class="h5">Kết quả đề xuất</h3>
        <p>{{ $purposes[$criteria['purpose']] }} · Ngân sách {{ $money($criteria['budget']) }}
            @if(!empty($criteria['min_ram_gb'])) · RAM từ {{ $criteria['min_ram_gb'] }} GB @endif
            @if(!empty($criteria['min_storage_gb'])) · Lưu trữ từ {{ $criteria['min_storage_gb'] }} GB @endif
        </p>
        @if($result['message'])
            <p class="alert alert-warning" role="status">{{ $result['message'] }}</p>
        @endif
        @if($result['limited'])
            <p class="small text-secondary">Hệ thống xét một số phương án trong phạm vi tìm kiếm để trả kết quả nhanh.</p>
        @endif
        <div class="row g-3">
            @foreach($result['proposals'] as $index => $proposal)
                <div class="col-md-4">
                    <article class="summary-card h-100 p-3">
                        <h4 class="h5">{{ $proposal['label'] }}</h4>
                        <p class="recommendation-price mb-2 text-secondary"><strong>{{ $money($proposal['total']) }}</strong> Cần mua · Còn {{ $money($proposal['remaining']) }}</p>
                        @if($proposal['discount'])<p class="small">Đã tính ưu đãi {{ $money($proposal['discount']) }}.</p>@endif
                        <p class="small d-flex flex-wrap align-items-center gap-2">
                            <span class="status-badge status-{{ strtolower($proposal['status']) }}">{{ $proposal['status'] }}</span>
                            <span class="text-secondary">{{ $proposal['checked'] }}/14 luật đã đánh giá</span>
                        </p>
                        <p class="small">{{ $proposal['reason'] }}<br>
                            CPU {{ $proposal['cores'] ? $proposal['cores'].' nhân' : 'chưa rõ số nhân' }} ·
                            VRAM {{ $proposal['vram'] ? $proposal['vram'].' GB' : 'chưa rõ' }} ·
                            RAM {{ $proposal['ram'] ? $proposal['ram'].' GB' : 'chưa rõ' }} ·
                            Lưu trữ {{ $proposal['storage'] ? $proposal['storage'].' GB' : 'chưa rõ' }}.
                        </p>
                        <details class="recommendation-parts mb-2">
                            <summary>Xem {{ count($proposal['items']) }} linh kiện</summary>
                            <ul class="list-unstyled small mt-3 mb-0">
                                @foreach($proposal['items'] as $type => $item)
                                    @php
                                        $product = $productsById->get($item['id']);
                                        $image = $product?->name === $item['name'] ? $product->imageDetails() : null;
                                    @endphp
                                    <li class="mb-2 d-flex align-items-start gap-2">
                                        @if($image)<img class="product-thumbnail" src="{{ asset($image['path']) }}" alt="{{ $product->imageAlt() }}" width="52" height="52" loading="lazy" decoding="async">@endif
                                        <div><strong>{{ $types[$type] }}</strong><br>
                                        <a href="{{ route('products.show', $item['id']) }}">{{ $item['name'] }}</a><br>
                                        @if(in_array($type, $proposal['owned'], true))
                                            Giữ lại · Đã sở hữu
                                        @else
                                            Mua thêm · {{ $money($item['price']) }} · {{ $item['price_label'] }}
                                        @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                        @if($proposal['warnings'])
                            <details class="small mb-3"><summary>{{ count($proposal['warnings']) }} điều kiện cần xem lại</summary>
                                <ul class="small mt-2">
                                    @foreach($proposal['warnings'] as $warning)
                                        <li><strong>{{ $warning['title'] }}:</strong> {{ $warning['message'] }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        <details class="recommendation-apply pt-2"><summary class="btn btn-outline-dark btn-sm">Đưa vào PC Builder</summary>
                            <p class="small mt-2">Phương án này sẽ thay toàn bộ lựa chọn đang có trong Builder và đánh dấu các món cần giữ. Đóng mục này để hủy.</p>
                            <form action="{{ route('builder.recommendations.apply') }}" method="post">
                                @csrf
                                <input type="hidden" name="batch" value="{{ $recommendations['batch'] }}">
                                <input type="hidden" name="proposal" value="{{ $index }}">
                                <button class="btn btn-outline-dark btn-sm" type="submit" name="confirm_replace" value="1" data-recommendation-current @disabled($hasPendingInput)>Xác nhận áp dụng</button>
                            </form>
                        </details>
                    </article>
                </div>
            @endforeach
        </div>
        @if($result['proposals'])
            <p class="small text-secondary mt-3 mb-0">PASS chỉ áp dụng cho các luật đã kiểm tra. Giá tham khảo hoặc giả lập được ghi theo từng món. Tồn kho và hiệu năng thực tế cần được xác minh riêng.</p>
        @endif
    </div>
@endif
