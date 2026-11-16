@extends('layouts.app')
@section('title', 'Cấu hình đã lưu')
@section('body_class', 'saved-configurations-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/saved-configurations.css') }}?v=20261104">
@endpush
@push('scripts')
    <script src="{{ asset('js/saved-configurations.js') }}?v=20261104" defer></script>
@endpush
@section('content')
    <p class="eyebrow mt-3">CẤU HÌNH CỦA BẠN</p>
    <h1 class="h2 mb-3">Cấu hình đã lưu</h1>
    <p class="text-secondary saved-intro">Bạn có thể lưu cả cấu hình chưa chọn đủ linh kiện. Khi mở lại, giá và linh kiện sẽ được kiểm tra theo danh sách hiện có.</p>
    <p class="text-secondary saved-intro saved-privacy">Bản lưu chỉ mình bạn xem được. Nếu bật chia sẻ, ai có liên kết cũng xem được tên cấu hình và linh kiện; thông tin tài khoản của bạn vẫn được giữ riêng. Không ghi thông tin cá nhân trong tên cấu hình.</p>
    @forelse($configurations as $configuration)
        <article class="component-card saved-configuration" aria-labelledby="saved-title-{{ $configuration->id }}">
            <div class="saved-description">
                <span class="saved-visibility">{{ $configuration->share_token ? 'Đang chia sẻ' : 'Chỉ mình bạn' }}</span>
                <h2 class="h5" id="saved-title-{{ $configuration->id }}">{{ $configuration->name }}</h2>
                <p class="text-secondary small mb-0">{{ count($configuration->parts) }}/8 nhóm linh kiện đã lưu · Lưu lúc <time datetime="{{ $configuration->created_at->toIso8601String() }}">{{ $configuration->created_at->format('d/m/Y H:i') }}</time></p>
            </div>
            <div class="saved-actions" aria-label="Thao tác với bản lưu">
                @include('configurations.action', [
                    'action' => route('configurations.load', $configuration), 'method' => 'POST',
                    'label' => 'Mở để chỉnh sửa', 'confirmLabel' => 'Mở bản lưu này', 'danger' => false,
                    'explanation' => 'Các linh kiện đang chọn và dấu “Tôi đã có” sẽ được thay bằng bản lưu này. Nhu cầu và giỏ hàng hiện tại vẫn được giữ. Giá và linh kiện sẽ được kiểm tra lại trước khi mở.',
                ])
                @include('configurations.action', [
                    'action' => route('configurations.destroy', $configuration), 'method' => 'DELETE',
                    'label' => 'Xóa bản lưu', 'confirmLabel' => 'Xác nhận xóa', 'danger' => true,
                    'explanation' => 'Bản lưu này sẽ bị xóa và không thể khôi phục. Nếu đang chia sẻ, liên kết cũ cũng sẽ ngừng truy cập. Cấu hình đang chọn và giỏ hàng không bị xóa.',
                ])
                @if($configuration->share_token)
                    @include('configurations.action', [
                        'action' => route('configurations.unshare', $configuration), 'method' => 'DELETE',
                        'label' => 'Thu hồi liên kết', 'confirmLabel' => 'Xác nhận thu hồi', 'danger' => false,
                        'explanation' => 'Liên kết hiện tại sẽ không xem được nữa. Bản lưu của bạn vẫn còn; những bản sao người nhận đã tạo sẽ không bị xóa.',
                    ])
                @else
                    @include('configurations.action', [
                        'action' => route('configurations.share', $configuration), 'method' => 'POST',
                        'label' => 'Bật chia sẻ', 'confirmLabel' => 'Đồng ý bật chia sẻ', 'danger' => false,
                        'explanation' => 'Ai có liên kết cũng xem được tên cấu hình và danh sách linh kiện. Thông tin tài khoản không được hiển thị. Bạn có thể thu hồi liên kết bất cứ lúc nào.',
                    ])
                @endif
            </div>
            @if($configuration->share_token)
                <details class="saved-share-link">
                    <summary>Xem liên kết chia sẻ</summary>
                    <div class="mt-3">
                        <label for="share-{{ $configuration->id }}" class="form-label small">Liên kết chia sẻ — sao chép để gửi</label>
                        <input id="share-{{ $configuration->id }}" class="form-control" readonly value="{{ route('configurations.shared', $configuration->share_token) }}">
                        <a class="d-inline-block mt-2" href="{{ route('configurations.shared', $configuration->share_token) }}" rel="noreferrer">Xem trang chia sẻ →</a>
                        <p class="small text-secondary mt-2 mb-0">Chỉ gửi liên kết cho người bạn muốn chia sẻ.</p>
                    </div>
                </details>
            @endif
        </article>
    @empty
        <div class="scope-card">Bạn chưa lưu cấu hình nào. <a href="{{ route('builder.index') }}">Bắt đầu chọn linh kiện</a>.</div>
    @endforelse
    {{ $configurations->links() }}
@endsection
