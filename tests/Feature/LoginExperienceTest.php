<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_errors_are_linked_to_the_fields_and_password_is_not_retained(): void
    {
        $login = route('login');
        $this->get($login)->assertOk()
            ->assertSee('novalidate', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false);
        $this->withCookie(config('session.cookie'), session()->getId());

        $this->from($login)->post($login)->assertRedirect($login)->assertSessionHasErrors(['email', 'password']);
        $this->get($login)->assertOk()
            ->assertSee('aria-describedby="login-email-error"', false)
            ->assertSee('aria-describedby="login-password-error"', false);

        $member = User::factory()->create();
        $wrongPassword = Str::random(24);
        $this->from($login)->post($login, ['email' => 'ASD', 'password' => $wrongPassword])
            ->assertRedirect($login)
            ->assertSessionHasErrors(['email' => 'Sai thông tin đăng nhập. Email không đúng định dạng.'])
            ->assertSessionMissing('_old_input.password');
        $this->get($login)->assertOk()->assertSeeText('Sai thông tin đăng nhập. Email không đúng định dạng.');
        foreach ([$member->email, 'missing@example.test'] as $email) {
            $this->from($login)->post($login, ['email' => $email, 'password' => $wrongPassword])
                ->assertRedirect($login)
                ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
                ->assertSessionMissing('_old_input.password');
        }
        $this->get($login)->assertOk()
            ->assertSeeText('Email hoặc mật khẩu không đúng.')
            ->assertDontSee($wrongPassword);
        $this->assertGuest();
    }

    public function test_login_regenerates_session_and_returns_to_the_intended_page(): void
    {
        $password = Str::random(24);
        $member = User::factory()->create(['password' => $password]);
        $login = route('login');
        $intended = route('configurations.index');
        $this->get($intended)->assertRedirect($login);
        $previousToken = session()->token();

        $this->post($login, ['email' => $member->email, 'password' => $password])->assertRedirect($intended);
        $this->assertAuthenticatedAs($member);
        $this->assertNotSame($previousToken, session()->token());
        $this->get($intended)->assertOk();
        $this->get($login)->assertRedirect(route('home'));
    }

    public function test_login_requires_a_valid_form_token_and_limits_repeated_attempts(): void
    {
        $login = route('login');
        $token = Str::random(40);
        $data = ['email' => 'missing@example.test', 'password' => Str::random(24)];
        $this->withSession(['_token' => $token])->withCookie(config('session.cookie'), session()->getId());
        // Buộc chạy lớp kiểm tra mã biểu mẫu trong cơ sở dữ liệu thử đã được TestCase bảo vệ.
        $this->app->instance('env', 'local');
        $this->from($login)->post($login, $data)->assertStatus(419);
        $data['_token'] = $token;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from($login)->post($login, $data)->assertSessionHasErrors('email');
        }
        $this->from($login)->post($login, $data)->assertStatus(303)->assertRedirect($login)
            ->assertSessionHas('rate_limit_notice', true)
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }
}
