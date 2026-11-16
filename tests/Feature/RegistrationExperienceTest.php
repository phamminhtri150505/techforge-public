<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_places_errors_at_the_fields_and_repopulates_only_safe_input(): void
    {
        $register = route('register');
        $this->get($register)->assertOk()->assertSee('novalidate', false);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->from($register)->post($register)->assertRedirect($register)
            ->assertSessionHasErrors(['name', 'email', 'password', 'password_confirmation']);

        $this->from($register)->post($register, [
            'name' => 'Tên kiểm thử',
            'email' => 'PERSON@EXAMPLE.TEST',
            'password' => 'too-short',
            'password_confirmation' => 'another-entry',
        ])->assertRedirect($register)
            ->assertSessionHasErrors(['password', 'password_confirmation' => 'Mật khẩu nhập lại chưa khớp.'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->get($register)->assertOk()
            ->assertSee('value="Tên kiểm thử"', false)
            ->assertSee('value="person@example.test"', false)
            ->assertSee('aria-describedby="register-password-error"', false)
            ->assertSee('aria-describedby="register-confirmation-error"', false)
            ->assertSeeText('Mật khẩu nhập lại chưa khớp.');

        $this->from($register)->post($register, ['email' => 'ASD'])->assertRedirect($register)
            ->assertSessionHasErrors(['email' => 'Email không đúng định dạng. Vui lòng kiểm tra lại.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_keeps_csrf_and_rate_limiting(): void
    {
        $register = route('register');
        $token = Str::random(40);
        $this->withSession(['_token' => $token])->withCookie(config('session.cookie'), session()->getId());
        $this->app->instance('env', 'local');

        $this->from($register)->post($register)->assertStatus(419);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from($register)->post($register, ['_token' => $token])->assertSessionHasErrors('email');
        }
        $this->from($register)->post($register, ['_token' => $token])->assertStatus(303)
            ->assertRedirect($register)->assertSessionHas('rate_limit_notice', true);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }
}
