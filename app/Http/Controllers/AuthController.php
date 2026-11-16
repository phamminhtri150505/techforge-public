<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.form', ['register' => false]);
    }

    public function registerForm()
    {
        return view('auth.form', ['register' => true]);
    }

    public function register(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        $data = $request->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:10|max:128',
            'password_confirmation' => 'required|same:password',
        ], [
            'email.email' => 'Email không đúng định dạng. Vui lòng kiểm tra lại.',
            'password_confirmation.required' => 'Vui lòng nhập lại mật khẩu.',
            'password_confirmation.same' => 'Mật khẩu nhập lại chưa khớp.',
        ]);
        // is_admin không nằm trong dữ liệu cho phép đăng ký.
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('builder.index'));
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string'], [
            'email.email' => 'Sai thông tin đăng nhập. Email không đúng định dạng.',
        ]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('builder.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('builder.index');
    }
}
