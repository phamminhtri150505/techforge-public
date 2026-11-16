@extends('layouts.app')
@section('title', 'Tin chờ duyệt')
@section('content')
    <header class="my-3 mb-4">
        <p class="eyebrow">QUẢN LÝ BÀI VIẾT</p>
        <h1 class="h2">Tin chờ duyệt</h1>
        <p class="text-secondary">Xem phần giới thiệu từ nguồn, mở bài gốc để đối chiếu và soạn bản nháp cho các tin bạn chọn.</p>
        <p class="text-secondary small">{{ config('news.scheduled') ? 'Đã bật lịch gom tin mỗi 4 giờ.' : 'Lịch gom tin đang tắt.' }}</p>
        <a href="{{ route('admin.index') }}">Quay lại quản trị</a>
    </header>
    <section class="summary-card p-4 mb-4" id="ban-tong-hop" aria-labelledby="news-digest-title">
        <h2 class="h4" id="news-digest-title">Bản tổng hợp của bạn</h2>
        <p class="text-secondary">Mỗi lượt có tin mới sẽ gửi một bản ngắn vào đây. Bản tổng hợp chọn tối đa ba tin mỗi nguồn và rút gọn phần giới thiệu có sẵn.</p>
        @if ($digests === null)
            <p class="mb-0">Bản tổng hợp sẽ hiện sau khi cập nhật cơ sở dữ liệu.</p>
        @elseif ($digests->isEmpty())
            <p class="mb-0">Bạn chưa có bản tổng hợp mới.</p>
        @else
            @foreach ($digests as $digest)
                <details class="mt-3" @if ($loop->first) open @endif>
                    <summary class="fw-semibold">{{ $digest->created_at->format('d/m/Y H:i') }} · {{ $digest->item_count }} tin mới</summary>
                    <ul class="mt-3 mb-0">
                        @foreach ($digest->entries as $entry)
                            <li class="mb-3">
                                <strong>{{ $entry['title'] }}</strong>
                                <div class="small text-secondary">{{ $entry['source'] }}</div>
                                <p class="mb-1">{{ $entry['summary'] ?? 'Nguồn chỉ cung cấp tiêu đề.' }}</p>
                                <a href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer">Mở bài gốc</a>
                                <a class="ms-3" href="{{ route('admin.articles.create', ['news' => $entry['id']]) }}">Soạn bản nháp</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        @endif
    </section>
    @if ($items === null)
        <div class="summary-card p-4">Hàng chờ sẽ hiện sau khi cập nhật cơ sở dữ liệu.</div>
    @elseif ($items->isEmpty())
        <div class="summary-card p-4">Hiện chưa có tin mới chờ duyệt.</div>
    @else
        @foreach ($items as $item)
            <article class="summary-card p-4 mb-3">
                <p class="text-secondary small mb-2">{{ $item->source }} @if($item->source_published_at) · {{ $item->source_published_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }} @endif</p>
                <h2 class="h5 text-break">{{ $item->title }}</h2>
                @if ($item->preview)
                    <p class="text-break">{{ $item->preview }}</p>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-dark" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">Mở bài gốc</a>
                    <a class="btn btn-forge" href="{{ route('admin.articles.create', ['news' => $item->id]) }}">Soạn bản nháp</a>
                </div>
            </article>
        @endforeach
        {{ $items->links() }}
    @endif
@endsection
