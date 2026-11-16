@extends('layouts.app')
@section('title', $register ? 'Đăng ký' : 'Đăng nhập')
@if ($errors->hasAny($register ? ['name', 'email', 'password', 'password_confirmation'] : ['email', 'password']))
    @section('custom_notices', 'true')
@endif
@section('content')
    <div class="mx-auto summary-card my-4" style="max-width:480px">
        <p class="eyebrow">KHÔNG GIAN CỦA BẠN</p>
        <h1 class="h3 mb-3">{{ $register ? 'Tạo tài khoản TECHFORGE' : 'Chào mừng bạn trở lại' }}</h1>
        <p class="text-secondary">Lưu cấu hình và xem đơn hàng mô phỏng của riêng bạn.</p>
        <form method="post" action="{{ route($register ? 'register.store' : 'login.store') }}" novalidate>@csrf
            @if ($register)
                <label class="form-label" for="name">Họ tên</label>
                <input @class(['form-control', 'is-invalid' => $errors->has('name'), 'mb-3' => ! $errors->has('name')])
                    id="name" name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" maxlength="100" autocomplete="name"
                    @error('name') aria-invalid="true" aria-describedby="register-name-error" @enderror required>
                @error('name')
                    <div class="invalid-feedback mb-3" id="register-name-error">{{ $message }}</div>
                @enderror
            @endif
            <label class="form-label" for="email">Email</label>
            <input @class(['form-control', 'is-invalid' => $errors->has('email'), 'mb-3' => ! $errors->has('email')])
                id="email" type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="{{ $register ? 'email' : 'username' }}"
                @error('email') aria-invalid="true" aria-describedby="{{ $register ? 'register' : 'login' }}-email-error" @enderror required>
            @error('email')
                <div class="invalid-feedback mb-3" id="{{ $register ? 'register' : 'login' }}-email-error">{{ $message }}</div>
            @enderror
            <label class="form-label" for="password">Mật khẩu{{ $register ? ' (ít nhất 10 ký tự)' : '' }}</label><input
                @class(['form-control', 'is-invalid' => $errors->has('password'), 'mb-3' => ! $errors->has('password')])
                id="password" type="password" name="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}"
                @error('password') aria-invalid="true" aria-describedby="{{ $register ? 'register' : 'login' }}-password-error" @enderror required>
            @error('password')
                <div class="invalid-feedback mb-3" id="{{ $register ? 'register' : 'login' }}-password-error">{{ $message }}</div>
            @enderror
            @if ($register)
                <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label><input
                    @class(['form-control', 'is-invalid' => $errors->has('password_confirmation'), 'mb-3' => ! $errors->has('password_confirmation')])
                    id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                    @error('password_confirmation') aria-invalid="true" aria-describedby="register-confirmation-error" @enderror required>
                @error('password_confirmation')
                    <div class="invalid-feedback mb-3" id="register-confirmation-error">{{ $message }}</div>
                @enderror
            @endif
            <button class="btn btn-forge w-100">{{ $register ? 'Đăng ký' : 'Đăng nhập' }}</button>
        </form>
        <a class="d-block mt-3"
            href="{{ route($register ? 'login' : 'register') }}">{{ $register ? 'Đã có tài khoản? Đăng nhập' : 'Chưa có tài khoản? Đăng ký' }}</a>
    </div>
@endsection
