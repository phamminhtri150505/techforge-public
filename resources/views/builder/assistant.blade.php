@php
    // Tách phần nhu cầu khỏi các thông điệp kỹ thuật lặp lại; không sửa nội dung phân tích.
    $technicalMessages = collect($report['rules'])->where('status', '!=', 'PASS')
        ->map(fn ($rule) => $rule['title'].' ['.$rule['status'].']: '.$rule['message'])->all();
    $needsMessages = array_filter($analysis['messages'], fn ($message) => ! in_array($message, $technicalMessages, true));
    $recommendations = session('builder.recommendations');
    $recommendationErrors = $errors->getBag('recommendations');
    $ownedInput = old('recommendation_input.owned', old('recommendation_owned',
        $recommendations['owned_input'] ?? array_intersect_key(session('builder.parts', []), array_flip(session('builder.owned', [])))));
    $ownedInput = is_array($ownedInput) ? $ownedInput : [];
    $purposes = \App\Services\AssistantService::PURPOSES;
    $formValue = function ($field, $default = '') use ($requirements) {
        $value = old('recommendation_input.'.$field, old($field, $requirements[$field] ?? $default));

        return is_scalar($value) ? $value : '';
    };
    $hasPendingRequirements = old('recommendation_input') !== null || $errors->hasAny(['budget', 'purpose', 'min_ram_gb', 'min_storage_gb'])
        || (session('rate_limit_notice') && session()->hasOldInput('purpose'));
@endphp
<span id="recommendations"></span>
<section id="assistant" class="scope-card" aria-labelledby="assistant-title">
    <p class="checked-stale-notice" data-checked-stale-notice role="status" @if(! $hasPendingInput) hidden @endif>
        <strong>Lựa chọn linh kiện đã thay đổi.</strong> Hãy cập nhật và kiểm tra cấu hình trước khi tiếp tục. Kết quả bên dưới thuộc lần kiểm tra trước.
    </p>
    <p class="eyebrow">TRỢ LÝ CẤU HÌNH</p>
    <h2 id="assistant-title">Ngân sách và nhu cầu của bạn</h2>
    <p>Dùng cùng ngân sách và mục tiêu để phân tích cấu hình đang chọn hoặc tìm phương án từ danh mục.</p>
    @if (isset($requirements['article_title']))
        <p><strong>Yêu cầu từ bài:</strong> {{ $requirements['article_title'] }}</p>
    @endif
    @if($recommendationErrors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($recommendationErrors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('assistant.update') }}" method="post" class="row g-3 mt-1" data-requirements-form data-pending="{{ $hasPendingRequirements ? '1' : '0' }}">@csrf
        <div class="col-md-3"><label class="form-label" for="budget">Ngân sách (VND)</label><input class="form-control"
                id="budget" name="budget" data-needs-input type="number" min="1000000" max="1000000000"
                value="{{ $formValue('budget') }}" placeholder="Ví dụ: 23000000"></div>
        <div class="col-md-3"><label class="form-label" for="purpose">Nhu cầu</label><select class="form-select"
                id="purpose" name="purpose" data-needs-input>
                @foreach ($purposes as $value => $label)
                    <option value="{{ $value }}" @selected($formValue('purpose', 'office') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label" for="min_ram_gb">RAM mục tiêu (GB)</label><input
                class="form-control" id="min_ram_gb" name="min_ram_gb" data-needs-input type="number" min="1" max="1024"
                value="{{ $formValue('min_ram_gb') }}"></div>
        <div class="col-md-3"><label class="form-label" for="min_storage_gb">Lưu trữ mục tiêu (GB)</label><input
                class="form-control" id="min_storage_gb" name="min_storage_gb" data-needs-input type="number" min="1"
                max="100000" value="{{ $formValue('min_storage_gb') }}"></div>
        <div>
            <details @if(array_filter($ownedInput) || $recommendationErrors->has('owned') || $recommendationErrors->has('owned.*')) open @endif>
                <summary>Linh kiện muốn giữ khi đề xuất (tùy chọn)</summary>
                <div class="row g-3 mt-1">
                    @foreach($types as $type => $label)
                        @php($ownedValue = old('recommendation_input.owned.'.$type, old('recommendation_owned.'.$type, $ownedInput[$type] ?? '')))
                        <div class="col-md-3">
                            <label class="form-label" for="rec-owned-{{ $type }}">{{ $label }}</label>
                            <select class="form-select" id="rec-owned-{{ $type }}" name="recommendation_owned[{{ $type }}]">
                                <option value="">Tìm trong danh mục</option>
                                @foreach($catalog->get($type, collect()) as $product)
                                    @continue(str_starts_with($product->sku, 'DEMO-'))
                                    <option value="{{ $product->id }}" @selected(is_scalar($ownedValue) && (string) $ownedValue === (string) $product->id)>{{ $product->name }} · {{ $product->sku }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-dark" type="submit" data-requires-current>Phân tích cấu hình đang chọn</button>
            <button class="btn btn-forge" type="submit" formaction="{{ route('builder.recommendations.store') }}"
                name="sync_requirements" value="1" data-recommendation-submit data-recommendation-current>Đề xuất cấu hình</button>
            <span role="status" data-recommendation-progress hidden>Đang tìm phương án trong danh mục…</span>
        </div>
        <p class="small text-secondary">Nhập ngân sách để tìm đề xuất. Linh kiện muốn giữ chỉ áp dụng khi tìm phương án mới.</p>
    </form>
    <p class="requirements-pending-notice" data-requirements-pending-note role="status" @if(! $hasPendingRequirements) hidden @endif>Kết quả đang dùng nhu cầu đã gửi trước đó. Hãy bấm một trong hai nút để dùng thông tin vừa nhập.</p>
    <div class="needs-analysis mt-4" data-requirements-analysis>
        <h3>Phân tích cấu hình đang chọn</h3>
        <ul class="needs-messages">
            @foreach ($needsMessages as $message)
                <li class="mb-2"><x-compatibility-message :message="$message" /></li>
            @endforeach
        </ul>
        <p class="small text-secondary mb-0">Kết quả kỹ thuật: <strong>{{ $analysis['status'] }}</strong>. @if(! $report['selection_complete'])Chưa chọn đủ nhóm; chi phí hiện tại chưa đại diện cả bộ. @endif @if(! $report['checks_complete'])Một số điều kiện chưa đủ dữ liệu để đánh giá. @endif <a href="#report">Xem báo cáo tương thích ↑</a></p>
    </div>
    @if($technicalMessages)
        <details class="assistant-technical mt-3">
            <summary>Xem thêm {{ count($technicalMessages) }} lưu ý kỹ thuật của cấu hình đã kiểm tra</summary>
            <ul class="mt-3">@foreach($technicalMessages as $message)<li class="mb-2"><x-compatibility-message :message="$message" /></li>@endforeach</ul>
        </details>
    @endif
    @if ($analysis['narrative'])
        <div class="alert alert-info">
            <strong>Những điểm cần ưu tiên trong cấu hình của bạn</strong>
            <ul class="mb-0 mt-2">
                @foreach (explode("\n", $analysis['narrative']) as $message)
                    <li><x-compatibility-message :message="$message" /></li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($analysis['mode'] === 'fallback')
        <p>Dịch vụ trợ lý chưa cung cấp kết quả hợp lệ. Phân tích tại chỗ vẫn hoạt động.</p>
    @endif
    @include('builder.recommendations')
</section>
