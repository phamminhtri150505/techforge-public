@extends('layouts.app')
@section('title', $article->exists ? 'Sửa bài viết' : 'Thêm bài viết')
@section('content')
    @php
        $editing = $article->exists;
        $storedRequirements = $article->requirements === null
            ? '' : json_encode((object) $article->requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stored = $article->attributesToArray() + ['requirements_json' => $storedRequirements];
        $draft = function ($field, $default = '') use ($stored) {
            $value = old($field, $stored[$field] ?? $default);

            return is_scalar($value) ? $value : '';
        };
        $publishStatus = (string) $draft('is_published', '0');
    @endphp
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <header class="my-3 mb-4">
                <p class="eyebrow">QUẢN LÝ BÀI VIẾT</p>
                <h1 class="h2">{{ $editing ? 'Sửa bài viết' : 'Thêm bài viết' }}</h1>
                @if ($editing)
                    <p class="text-secondary mb-1">{{ $article->title }}</p>
                    @if ($article->is_published)
                        <a class="small" href="{{ route('articles.show', $article->slug) }}">Xem bài công khai</a>
                    @endif
                @else
                    <p class="text-secondary">Soạn nội dung và lưu bản nháp để chỉnh tiếp. Chọn Công khai khi bài đã sẵn sàng.</p>
                @endif
            </header>

            @if ($newsItem)
                <aside class="summary-card p-4 mb-4">
                    <h2 class="h5">Tin để tham khảo</h2>
                    <a href="{{ $newsItem->url }}" target="_blank" rel="noopener noreferrer">{{ $newsItem->title }}</a>
                    <p class="form-text mb-2">{{ $newsItem->source }}</p>
                    @if ($newsItem->preview)
                        <p>{{ $newsItem->preview }}</p>
                    @endif
                    <p class="form-text mb-0">Đối chiếu bài gốc, viết phần tóm tắt của bạn rồi lưu bản nháp để duyệt.</p>
                </aside>
            @endif
            <form method="post" action="{{ $editing ? route('admin.articles.update', $article) : route('admin.articles.store') }}">
                @csrf
                @if ($newsItem)
                    <input type="hidden" name="news_item_id" value="{{ $newsItem->id }}">
                @endif
                @if ($editing)
                    @method('PUT')
                @endif
                <section class="summary-card p-4 mb-4" aria-labelledby="article-content-title">
                    <h2 class="h5 mb-3" id="article-content-title">1. Nội dung</h2>
                    <div class="mb-3">
                        <label class="form-label" for="title">Tiêu đề</label>
                        <input class="form-control" id="title" name="title" value="{{ $draft('title') }}" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="excerpt">Tóm tắt</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3" maxlength="1000" aria-describedby="excerpt-help" required>{{ $draft('excerpt') }}</textarea>
                        <p class="form-text mb-0" id="excerpt-help">Viết vài câu để người đọc biết bài nói về điều gì.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="content">Nội dung bài viết</label>
                        <textarea class="form-control" id="content" name="content" rows="12" maxlength="100000" aria-describedby="content-help" required>{{ $draft('content') }}</textarea>
                        <p class="form-text mb-0" id="content-help">Bạn có thể viết văn bản thường hoặc dùng Markdown để chia mục, tạo danh sách và chèn liên kết.</p>
                    </div>
                    <details>
                        <summary class="fw-semibold">Cách trình bày bằng Markdown</summary>
                        <ul class="mt-2 mb-0">
                            <li>Dùng <code>## Tên mục</code> và <code>### Mục nhỏ</code>. Để một dòng trống giữa các đoạn.</li>
                            <li>Dùng <code>- Nội dung</code> cho danh sách hoặc <code>1. Nội dung</code> cho các bước.</li>
                            <li>Dùng <code>[Tên nguồn](https://…)</code> để dẫn đến nguồn.</li>
                            <li>Dùng <code>![Mô tả](/images/ten-anh.jpg)</code> cho ảnh đã có trong thư mục ảnh của website và được phép sử dụng.</li>
                            <li>HTML nhập trực tiếp sẽ hiện dưới dạng chữ.</li>
                        </ul>
                    </details>
                </section>

                <section class="summary-card p-4 mb-4" aria-labelledby="article-publish-title">
                    <h2 class="h5 mb-3" id="article-publish-title">2. Xuất bản</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="slug">Đường dẫn bài viết</label>
                            <input class="form-control" id="slug" name="slug" value="{{ $draft('slug') }}" maxlength="200" aria-describedby="slug-help" required>
                            <p class="form-text mb-0" id="slug-help">Dùng chữ không dấu, số, dấu gạch ngang hoặc gạch dưới. Mỗi bài có một đường dẫn riêng, ví dụ <code>cach-chon-ram</code>.</p>
                            @if ($editing && $article->is_published)
                                <p class="form-text mb-0">Nếu đổi đường dẫn, liên kết cũ tới bài sẽ ngừng hoạt động và ảnh bìa riêng có thể thay đổi.</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="category">Chuyên mục</label>
                            <select id="category" name="category" class="form-select">
                                @foreach (\App\Models\Article::CATEGORIES as $category)
                                    <option value="{{ $category }}" @selected($draft('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="is_published">Trạng thái</label>
                            <select id="is_published" name="is_published" class="form-select" aria-describedby="status-help">
                                <option value="0" @selected($publishStatus !== '1')>Bản nháp</option>
                                <option value="1" @selected($publishStatus === '1')>Công khai</option>
                            </select>
                            <p class="form-text mb-0" id="status-help">Bản nháp chỉ xem được trong khu vực quản trị.</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cover_style">Kiểu hình minh họa có sẵn</label>
                            <select id="cover_style" name="cover_style" class="form-select">
                                @foreach (\App\Models\Article::COVER_STYLES as $value => $label)
                                    <option value="{{ $value }}" @selected($draft('cover_style', 'circuit') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="featured_rank">Mức ưu tiên bài nổi bật</label>
                            <input class="form-control" type="number" id="featured_rank" name="featured_rank" min="0" max="1000" value="{{ $draft('featured_rank', 0) }}" aria-describedby="rank-help" required>
                            <p class="form-text mb-0" id="rank-help">Để 0 nếu bài không cần ưu tiên.</p>
                        </div>
                    </div>
                </section>

                <section class="summary-card p-4 mb-4" aria-labelledby="article-source-title">
                    <h2 class="h5 mb-3" id="article-source-title">3. Nguồn</h2>
                    @if (! $editing && ! $newsItem)
                        <div class="mb-3">
                            <label class="form-label" for="tinhte_url">Liên kết bài Tinhte.vn (tùy chọn)</label>
                            <input class="form-control" type="url" id="tinhte_url" name="tinhte_url" value="{{ $draft('tinhte_url') }}" maxlength="900" aria-describedby="tinhte-source-help">
                            <p class="form-text mb-0" id="tinhte-source-help">Dán liên kết để ghi nguồn cho nội dung bạn tự biên soạn. Lưu bài dưới dạng bản nháp để duyệt.</p>
                        </div>
                    @endif
                    <label class="form-label" for="data_source">Nguồn thông tin</label>
                    <textarea class="form-control" id="data_source" name="data_source" rows="3" maxlength="1000" aria-describedby="source-help" @required($editing) @readonly($newsItem)>{{ $newsItem ? $newsItem->attribution() : $draft('data_source') }}</textarea>
                    <p class="form-text mb-0" id="source-help">Ghi nguồn đã tham khảo. Nếu bài dùng dữ liệu minh họa, ghi rõ tại đây. @if (! $editing && ! $newsItem)Nếu đã dán liên kết Tinhte.vn ở trên, ô này dùng để bổ sung ghi chú.@endif</p>
                </section>

                <section class="summary-card p-4 mb-4" aria-labelledby="article-builder-title">
                    <h2 class="h5 mb-3" id="article-builder-title">4. Yêu cầu gửi sang PC Builder</h2>
                    <p class="text-secondary">Phần này tùy chọn. Chỉ dùng khi bài viết gợi ý nhu cầu để người đọc chuyển sang PC Builder.</p>
                    @if ($editing)
                        <p class="form-text">Đổi yêu cầu ở đây sẽ đổi thông tin người đọc có thể gửi sang PC Builder. Xóa toàn bộ nội dung ô JSON để bỏ yêu cầu đã lưu.</p>
                    @endif
                    <details @if(filled(old('requirements_json')) || $errors->hasAny(['requirements', 'requirements_json', 'requirements.*'])) open @endif>
                        <summary class="fw-semibold">Nhập yêu cầu bằng JSON</summary>
                        <div class="mt-3">
                            <label class="form-label" for="requirements_json">Yêu cầu theo mẫu</label>
                            <textarea class="form-control font-monospace" id="requirements_json" name="requirements_json" rows="4" maxlength="5000" aria-describedby="requirements-help">{{ $draft('requirements_json') }}</textarea>
                            <div class="form-text" id="requirements-help">
                                <p class="mb-1">Mẫu: <code class="text-break">{"budget":23000000,"purpose":"office","min_ram_gb":32,"min_storage_gb":1000}</code></p>
                                <p class="mb-0"><code>purpose</code> là mục đích sử dụng. Chọn <code>office</code> (văn phòng), <code>gaming</code> (chơi game) hoặc <code>creative</code> (sáng tạo nội dung). Ngân sách tính bằng đồng, dung lượng tính bằng GB.</p>
                            </div>
                        </div>
                    </details>
                </section>

                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-forge" type="submit">{{ $editing ? 'Lưu thay đổi' : 'Lưu bài viết' }}</button>
                    <a class="btn btn-link" href="{{ route('admin.index') }}">Quay lại quản trị</a>
                </div>
            </form>
        </div>
    </div>
@endsection
