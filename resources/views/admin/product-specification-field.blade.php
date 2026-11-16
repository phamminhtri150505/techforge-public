@php($inputId = 'spec-'.$type.'-'.$key)
@php($scalar = is_scalar($value) ? $value : '')
<div class="{{ in_array($field['input'], ['cpu_support', 'checks', 'lines'], true) ? 'col-12' : 'col-md-6' }}">
    @if($field['input'] === 'cpu_support')
        @if($editing)<input type="hidden" name="specifications[cpu_support]" value="">@endif
        <details class="product-cpu-support" @if($editing && ($errors->has('specifications.cpu_support.*') || $errors->hasAny(['status', 'minimum_bios', 'source_url', 'checked_on']))) open @endif><summary>{{ $field['label'] }}</summary>
            <p class="form-text">Có thể để trống nếu chưa có bảng hỗ trợ của hãng. Không tự kết luận hỗ trợ chỉ vì cùng chân cắm.</p>
            <div data-cpu-support-rows>
                @foreach(is_array($value) && $value !== [] ? $value : [[]] as $index => $support)
                    @php($support = is_array($support) ? $support : [])
                    <div class="product-support-row row g-2" data-cpu-support-row>
                        @foreach(['model_code' => 'Mã CPU theo hãng', 'status' => 'Tình trạng hỗ trợ', 'minimum_bios' => 'Phiên bản BIOS tối thiểu', 'pcb_revision' => 'Phiên bản bo mạch', 'source_url' => 'Liên kết bảng hỗ trợ của hãng', 'checked_on' => 'Ngày đối chiếu'] as $column => $label)
                            @php($supportId = $inputId.'-'.$index.'-'.$column)
                            <div class="col-md-6"><label class="form-label" for="{{ $supportId }}">{{ $label }}</label>
                                @if($column === 'status')
                                    <select class="form-select" id="{{ $supportId }}" name="specifications[cpu_support][{{ $index }}][{{ $column }}]"><option value="">Chưa chọn</option>@if($editing && ($support[$column] ?? null) === 'supported')<option value="supported" selected>Đã ghi nhận hỗ trợ trước đây</option>@endif<option value="bios_update" @selected(($support[$column] ?? '') === 'bios_update')>Cần kiểm tra hoặc cập nhật BIOS</option><option value="unsupported" @selected(($support[$column] ?? '') === 'unsupported')>Hãng xác nhận không hỗ trợ</option></select>
                                @else
                                    <input class="form-control" id="{{ $supportId }}" name="specifications[cpu_support][{{ $index }}][{{ $column }}]" type="{{ $column === 'checked_on' ? 'date' : ($column === 'source_url' ? 'url' : 'text') }}" maxlength="{{ $column === 'source_url' ? 1000 : 80 }}" value="{{ is_scalar($support[$column] ?? null) ? $support[$column] : '' }}">
                                @endif
                            </div>
                        @endforeach
                        <div class="col-12"><button class="btn btn-link" type="button" data-remove-cpu-support hidden>Bỏ mục CPU này</button></div>
                    </div>
                @endforeach
            </div>
            <button class="btn btn-outline-dark mt-3" type="button" data-add-cpu-support hidden>Thêm CPU vào danh sách</button>
        </details>
    @elseif($field['input'] === 'checks')
        @if($editing)<input type="hidden" name="specifications[{{ $key }}]" value="">@endif
        <span class="form-label d-block" id="{{ $inputId }}-label">{{ $field['label'] }}</span><div class="d-flex flex-wrap gap-3" role="group" aria-labelledby="{{ $inputId }}-label">
            @foreach($field['choices'] as $option => $label)<label class="form-check"><input class="form-check-input" type="checkbox" name="specifications[{{ $key }}][]" value="{{ $option }}" @checked(is_array($value) && in_array((string)$option, $value, true))><span class="form-check-label">{{ $label }}</span></label>@endforeach
        </div><p class="form-text">Chưa biết thì không chọn mục nào.</p>
    @else
        <label class="form-label" for="{{ $inputId }}">{{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}</label>
        @if(in_array($field['input'], ['select', 'boolean'], true))
            <select class="form-select" id="{{ $inputId }}" name="specifications[{{ $key }}]" @required($field['required'])><option value="">Chưa biết / chưa chọn</option>
                @foreach($field['input'] === 'boolean' ? ['1' => 'Có', '0' => 'Không'] : $field['choices'] as $option => $label)<option value="{{ $option }}" @selected((string)$scalar !== '' && (string)$scalar === (string)$option)>{{ $label }}</option>@endforeach
            </select>
        @elseif($field['input'] === 'lines')
            <textarea class="form-control" rows="2" id="{{ $inputId }}" name="specifications[{{ $key }}]" @unless($editing) maxlength="1000" @endunless placeholder="{{ $editing ? 'Ví dụ: AM5' : 'Ví dụ: AM5, LGA1700' }}">{{ $scalar }}</textarea><p class="form-text">{{ $editing ? 'Nhập mỗi chân cắm trên một dòng.' : 'Mỗi chân cắm một dòng, hoặc cách nhau bằng dấu phẩy.' }} Chỉ nhập những loại được hãng liệt kê.</p>
        @else
            <input class="form-control" id="{{ $inputId }}" name="specifications[{{ $key }}]" type="{{ $field['input'] === 'integer' ? 'number' : 'text' }}" @if($field['input'] === 'integer') min="0" step="1" @elseif($field['input'] === 'decimal') inputmode="decimal" @endif maxlength="{{ $key === 'display_outputs' ? 200 : 80 }}" value="{{ $scalar }}">
            @if($field['input'] === 'decimal')<p class="form-text">Giữ đúng số thập phân theo hãng, ví dụ 220.5 hoặc 220,5. Không dùng dấu phân cách hàng nghìn.</p>@endif
        @endif
    @endif
    @error('specifications.'.$key)<p class="text-danger mt-2" role="alert">{{ $message }}</p>@enderror
</div>
