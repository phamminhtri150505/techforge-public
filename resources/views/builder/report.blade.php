    <section id="report" class="report-section" aria-labelledby="report-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow mb-1">BƯỚC 02</p>
                <h2 id="report-title">Hiểu cấu hình của bạn</h2>
            </div><span
                class="status-badge status-{{ strtolower($report['status']) }}">{{ $report['status'] }}</span>
        </div>
        <div class="report-overview row g-3 mb-3">
            <div class="col-md-4">
                <strong>{{ $report['selection_complete'] ? 'Đã chọn đủ 8 nhóm' : 'Cấu hình chưa hoàn chỉnh' }}</strong>
                <p>{{ count($selected) }}/8 nhóm đã chọn</p>
            </div>
            <div class="col-md-4">
                <strong>{{ $report['checks_complete'] ? 'Đủ dữ liệu trong phạm vi luật' : 'Chưa đủ dữ liệu kiểm tra' }}</strong>
                <p>{{ $report['checked_count'] }}/{{ $report['rule_count'] }} luật đã đánh giá</p>
            </div>
            <div class="col-md-4"><strong>Kết quả từ dữ liệu hệ thống</strong>
                <p>Đối chiếu các ràng buộc kỹ thuật</p>
            </div>
        </div>
        @if ($report['missing_types'])
            <p class="small text-secondary">Còn thiếu:
                {{ collect($report['missing_types'])->map(fn($type) => $types[$type])->join(', ') }}.</p>
        @endif
        <div class="row g-3">
            @foreach ($report['rules'] as $rule)
                <div class="col-md-6">
                    <article class="rule-card">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <h3>{{ $rule['title'] }}</h3><span
                                class="status-badge status-{{ strtolower($rule['status']) }}">{{ $rule['status'] }}</span>
                        </div>
                        <p><x-compatibility-message :message="$rule['message']" /></p><span class="rule-evaluation">
                            {{ $rule['checked'] ? 'Đã đánh giá' : 'Chưa đủ dữ liệu / cần xác nhận' }}</span>
                    </article>
                </div>
            @endforeach
        </div>
        <p class="scope-note mt-3">PASS chỉ có nghĩa là vượt qua các luật đã kiểm tra đủ dữ liệu trong phạm vi hệ
            thống; không phải cam kết “tương thích 100%”. Không suy luận nghẽn cổ chai. Mức PSU khuyến nghị không
            phải tổng TDP hay công suất tiêu thụ tối đa.</p>
    </section>
