@if($product->specifications)
    <dl class="product-specifications">
        @foreach($product->specifications as $key => $value)
            <div><dt>{{ config('specifications.labels.'.$key, $key) }}</dt><dd>
                @if($key === 'cpu_support' && is_array($value) && $value !== [])
                    <ul>@foreach($value as $support)<li>
                        <strong>{{ $support['model_code'] }}</strong>: {{ ['supported' => 'Hỗ trợ theo dữ liệu đã nhập', 'unsupported' => 'Không hỗ trợ', 'bios_update' => 'Cần xác minh BIOS'][$support['status']] }}
                        @if(isset($support['minimum_bios']))<p>Bảng hãng: BIOS {{ $support['minimum_bios'] }}@if(isset($support['pcb_revision'])) · PCB {{ $support['pcb_revision'] }}@endif.
                            <a href="{{ $support['source_url'] }}" target="_blank" rel="noopener noreferrer">Nguồn hãng <span class="visually-hidden">(mở tab mới)</span></a> · Đối chiếu {{ $support['checked_on'] }}. Chưa xác minh BIOS đang cài.</p>@endif
                    </li>@endforeach</ul>
                @elseif($value === null || $value === '' || $value === [])
                    Chưa có dữ liệu — cần xác minh
                @elseif(isset($storageValues[$key])){{ $storageValues[$key] }}
                @elseif(is_array($value)){{ implode(', ', $value) }}
                @elseif(is_bool($value)){{ $value ? 'Có' : 'Không' }}
                @else{{ $value }}@endif
            </dd></div>
        @endforeach
    </dl>
@else
    <p class="product-action-help">Chưa có thông số để đối chiếu. Không thể xác nhận tương thích từ tên hoặc ảnh sản phẩm.</p>
@endif
