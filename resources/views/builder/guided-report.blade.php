@php
    // Chỉ nhóm thông điệp để trình bày; giữ nguyên từng kết quả và cờ đủ dữ liệu từ bộ kiểm tra.
    $ruleGroups = collect($report['rules'])->groupBy('status');
    $conflicts = $ruleGroups->get('CONFLICT', collect());
    $warnings = $ruleGroups->get('WARNING', collect());
    $passes = $ruleGroups->get('PASS', collect());
    $uncheckedWarnings = $warnings->where('checked', false)->count();
    $checkedWarnings = $warnings->where('checked', true)->count();
@endphp
<section id="report" class="report-section guided-report" aria-labelledby="report-title" data-report-status="{{ $report['status'] }}">
    <p class="checked-stale-notice" data-checked-stale-notice role="status" @if(! $hasPendingInput) hidden @endif>
        <strong>Kết quả của lần kiểm tra trước.</strong> Chưa áp dụng cho lựa chọn vừa chỉnh. Nhấn “Cập nhật & kiểm tra” để nhận kết quả mới.
    </p>
    <div class="section-heading">
        <div><p class="eyebrow mb-1">BƯỚC 02</p><h2 id="report-title">Hiểu cấu hình của bạn</h2></div>
        <span class="status-badge status-{{ strtolower($report['status']) }}">{{ $report['status'] }}</span>
    </div>
    @if($conflicts->isNotEmpty())
        <div class="report-guidance report-guidance-conflict" role="alert">
            <h3>Có {{ $conflicts->count() }} xung đột kỹ thuật cần xử lý</h3>
            <p>Đọc các ràng buộc bên dưới, thay linh kiện liên quan rồi cập nhật và kiểm tra lại. Cấu hình có CONFLICT chưa thể chuyển sang đặt đơn.</p>
        </div>
    @elseif(count($selected) === 0)
        <div class="report-guidance">
            <h3>Bạn chưa chọn linh kiện</h3>
            <p>Hãy bắt đầu từ CPU hoặc chọn cấu hình tham khảo. Chưa đủ thông tin để kiểm tra; các WARNING hiện tại do thiếu dữ liệu, không phải đã phát hiện xung đột.</p>
            <div class="d-flex flex-wrap gap-2"><a class="btn btn-forge btn-sm" href="#part-cpu">Chọn CPU</a><a class="btn btn-outline-dark btn-sm" href="{{ route('reference-builds.index') }}">Xem cấu hình tham khảo ↗</a></div>
        </div>
    @elseif($warnings->isNotEmpty())
        <div class="report-guidance">
            <h3>Còn điều kiện cần lưu ý hoặc xác minh</h3>
            <p>WARNING có thể do thiếu dữ liệu hoặc một điều kiện cần kiểm tra thêm; khác với xung đột kỹ thuật CONFLICT. Bạn có thể tiếp tục chọn linh kiện và mở từng nhóm kết quả bên dưới.</p>
        </div>
    @else
        <div class="report-guidance">
            <h3>Các luật có đủ dữ liệu đã kiểm tra đạt</h3>
            <p>Đọc phạm vi kiểm tra trước khi quyết định lắp ráp. Kết quả này không bảo đảm mọi điều kiện lắp đặt thực tế.</p>
        </div>
    @endif
    <div class="report-overview row g-3 my-3">
        <div class="col-md-6"><strong>{{ $report['selection_complete'] ? 'Đã chọn đủ 8 nhóm' : 'Cấu hình chưa hoàn chỉnh' }}</strong><p>{{ count($selected) }}/8 nhóm đã chọn</p></div>
        <div class="col-md-6"><strong>{{ $report['checks_complete'] ? 'Đủ dữ liệu trong phạm vi luật' : 'Chưa đủ dữ liệu kiểm tra' }}</strong><p>{{ $report['checked_count'] }}/{{ $report['rule_count'] }} luật đã đánh giá</p></div>
    </div>
    @if($report['missing_types'])
        <p class="small text-secondary">Còn thiếu: {{ collect($report['missing_types'])->map(fn ($type) => $types[$type])->join(', ') }}. Có thể lưu bản nháp chưa đủ nhóm.</p>
    @endif
    <div class="rule-counts" aria-label="Tóm tắt kết quả từng luật">
        <span>{{ $conflicts->count() }} CONFLICT</span><span>{{ $warnings->count() }} WARNING</span><span>{{ $passes->count() }} PASS</span>
    </div>
    @if($conflicts->isNotEmpty())
        <section class="rule-group rule-group-conflict" data-rule-group="CONFLICT" aria-labelledby="conflict-group-title">
            <h3 id="conflict-group-title">Xung đột kỹ thuật cần xử lý · {{ $conflicts->count() }}</h3>
            <div class="grouped-rule-grid">@include('builder.rule-cards', ['rules' => $conflicts])</div>
        </section>
    @endif
    @if($warnings->isNotEmpty())
        <details class="rule-group" data-rule-group="WARNING">
            <summary><span class="rule-group-title">Điều kiện cần lưu ý hoặc xác minh · {{ $warnings->count() }}</span><span>Mở để xem WARNING</span></summary>
            <p class="small text-secondary mt-3">{{ $uncheckedWarnings }} điều kiện chưa đủ dữ liệu / cần xác nhận; {{ $checkedWarnings }} điều kiện đã đánh giá và cần lưu ý. Thông điệp chi tiết giữ nguyên từ bộ kiểm tra.</p>
            <div class="grouped-rule-grid">@include('builder.rule-cards', ['rules' => $warnings])</div>
        </details>
    @endif
    @if($passes->isNotEmpty())
        <details class="rule-group" data-rule-group="PASS">
            <summary><span class="rule-group-title">Điều kiện đã kiểm tra đạt · {{ $passes->count() }}</span><span>Mở để xem PASS</span></summary>
            <div class="grouped-rule-grid mt-3">@include('builder.rule-cards', ['rules' => $passes])</div>
        </details>
    @endif
    <button type="button" class="btn btn-outline-dark btn-sm mt-2" data-expand-rules hidden>Mở tất cả {{ $report['rule_count'] }} quy tắc</button>
    <p class="scope-note mt-3">PASS chỉ có nghĩa là vượt qua các luật đã kiểm tra đủ dữ liệu trong phạm vi hệ thống; không phải cam kết “tương thích 100%”. Không suy luận nghẽn cổ chai. Mức PSU khuyến nghị không phải tổng TDP hay công suất tiêu thụ tối đa.</p>
</section>
