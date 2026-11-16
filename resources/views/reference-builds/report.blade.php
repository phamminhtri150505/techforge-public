@php
    // Cùng cách nhóm UI-06 và dùng lại rule-cards; chỉ trình bày, không tính lại trạng thái luật.
    $groups = collect($report['rules'])->groupBy('status');
@endphp
<section class="reference-report" aria-labelledby="report-{{ $slug }}">
    <div class="reference-report-heading"><h3 id="report-{{ $slug }}">Kết quả kiểm tra</h3><span class="status-badge status-{{ strtolower($report['status']) }}">{{ $statusLabels[$report['status']] }}</span></div>
    <p>{{ $report['selection_complete'] ? 'Đã chọn đủ '.count($types).' nhóm.' : 'Cấu hình chưa hoàn chỉnh.' }} {{ $report['checks_complete'] ? 'Đã có đủ thông tin cho các mục kiểm tra.' : 'Còn thiếu thông tin để kiểm tra đầy đủ.' }}
        Đã kiểm tra <strong>{{ $report['checked_count'] }}/{{ $report['rule_count'] }}</strong> mục; số này gồm cả mục đạt và mục cần lưu ý hoặc không phù hợp.</p>
    <p class="reference-rule-counts">@foreach(['CONFLICT', 'WARNING', 'PASS'] as $status)<span>{{ $groups->get($status, collect())->count() }} {{ $statusLabels[$status] }}</span>@endforeach</p>
    @foreach(['CONFLICT', 'WARNING', 'PASS'] as $status)
        @php($rules = $groups->get($status, collect()))
        @if($rules->isNotEmpty())
            @if($status === 'CONFLICT')
                <section class="reference-rule-group reference-conflict" aria-label="Linh kiện không phù hợp">
                    <h4>Có {{ $rules->count() }} điểm không phù hợp cần xử lý</h4>
                    <div class="reference-rule-grid">@include('builder.rule-cards', ['rules' => $rules])</div>
                </section>
            @else
                <details class="reference-rule-group" data-rule-group="{{ $status }}">
                    <summary>{{ $status === 'WARNING' ? 'Những điểm cần lưu ý hoặc kiểm tra thêm' : 'Những mục đã kiểm tra đạt' }} · {{ $rules->count() }} mục</summary>
                    @if($status === 'WARNING')<p class="reference-meta mt-3">{{ $rules->where('checked', false)->count() }} mục còn thiếu thông tin hoặc cần xác nhận; {{ $rules->where('checked', true)->count() }} mục đã kiểm tra và có điều cần lưu ý.</p>@endif
                    <div class="reference-rule-grid">@include('builder.rule-cards', ['rules' => $rules])</div>
                </details>
            @endif
        @endif
    @endforeach
    <p class="scope-note mt-3">“Đạt kiểm tra” nghĩa là các mục có đủ thông tin đã kiểm tra đạt. Bạn vẫn cần xem hướng dẫn của hãng trước khi lắp máy. Kết quả này không bảo đảm mọi điều kiện lắp ráp, hiệu năng hoặc mức điện tiêu thụ thực tế.</p>
</section>
