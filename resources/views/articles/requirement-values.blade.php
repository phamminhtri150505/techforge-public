<dl class="article-requirements">
    @foreach(['budget' => ['Ngân sách mua thêm', 'VND'], 'purpose' => ['Nhu cầu', ''], 'min_ram_gb' => ['RAM mục tiêu', 'GB'], 'min_storage_gb' => ['Lưu trữ mục tiêu', 'GB']] as $key => [$label, $unit])
        @if(isset($values[$key]) && is_scalar($values[$key]))
            <div><dt>{{ $label }}</dt><dd>{{ $key === 'purpose' ? (\App\Services\AssistantService::PURPOSES[$values[$key]] ?? 'Cần kiểm tra lại') : (is_numeric($values[$key]) ? number_format($values[$key], 0, ',', '.') : 'Cần kiểm tra lại') }} {{ $unit }}</dd></div>
        @endif
    @endforeach
</dl>
