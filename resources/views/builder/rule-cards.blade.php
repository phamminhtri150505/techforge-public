@foreach ($rules as $rule)
    <article class="rule-card" data-rule-code="{{ $rule['code'] }}" data-rule-status="{{ $rule['status'] }}">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <h4>{{ strtr($rule['title'], $familiarWords ?? []) }}</h4>
            <span class="status-badge status-{{ strtolower($rule['status']) }}">{{ ($statusLabels ?? [])[$rule['status']] ?? $rule['status'] }}</span>
        </div>
        <p><x-compatibility-message :message="$rule['message']" :word-labels="$familiarWords ?? []" /></p>
        <span class="rule-evaluation">{{ isset($statusLabels) ? ($rule['checked'] ? 'Đã kiểm tra' : 'Còn thiếu thông tin / cần xác nhận') : ($rule['checked'] ? 'Đã đánh giá' : 'Chưa đủ dữ liệu / cần xác nhận') }}</span>
    </article>
@endforeach
