{{-- Mở/đóng phần hỏi lại không gửi yêu cầu. Chỉ nút xác nhận bên trong mới gửi form có CSRF. --}}
<details class="saved-action @if($danger) saved-action-danger @endif">
    <summary class="btn {{ $danger ? 'btn-outline-danger' : 'btn-outline-dark' }} btn-sm">{{ $label }}</summary>
    <div class="saved-action-confirmation">
        <p>{{ $explanation }}</p>
        <form method="post" action="{{ $action }}">
            @csrf
            @if($method !== 'POST') @method($method) @endif
            <div class="d-flex flex-wrap gap-2">
                <button class="btn {{ $danger ? 'btn-danger' : 'btn-forge' }} btn-sm">{{ $confirmLabel }}</button>
                <button class="btn btn-outline-dark btn-sm" type="reset" data-cancel-saved-action>Hủy</button>
            </div>
        </form>
    </div>
</details>
