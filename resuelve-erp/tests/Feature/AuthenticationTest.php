<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pqrs')->assertRedirect(route('login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => '12345']);
        $this->get(route('login'));
        $sessionId = session()->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('pqrs.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_login_with_an_uppercase_email(): void
    {
        $user = User::factory()->create([
            'email' => 'residente@example.com',
            'password' => '12345',
        ]);

        $this->post(route('login.store'), [
            'email' => 'RESIDENTE@EXAMPLE.COM',
            'password' => '12345',
        ])->assertRedirect(route('pqrs.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_outer_spaces_in_the_email(): void
    {
        $user = User::factory()->create([
            'email' => 'residente@example.com',
            'password' => '12345',
        ]);

        $this->post(route('login.store'), [
            'email' => '  residente@example.com  ',
            'password' => '12345',
        ])->assertRedirect(route('pqrs.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_outer_spaces_in_the_password(): void
    {
        $user = User::factory()->create(['password' => ' 12345 ']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => ' 12345 ',
        ])->assertRedirect(route('pqrs.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => '12345']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_allows_attempts_within_the_limit_and_then_returns_429(): void
    {
        $credentials = ['email' => 'limit@example.com', 'password' => 'incorrecta'];

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), $credentials)
                ->assertRedirect()
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), $credentials)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_login_limit_is_isolated_by_normalized_email_and_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('login.store'), [
                    'email' => '  LIMIT@EXAMPLE.COM ',
                    'password' => 'incorrecta',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login.store'), [
                'email' => 'otro@example.com',
                'password' => 'incorrecta',
            ])->assertRedirect();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('login.store'), [
                'email' => 'limit@example.com',
                'password' => 'incorrecta',
            ])->assertRedirect();
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'limpieza@example.com',
            'password' => 'clave-correcta',
        ]);

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrecta',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'clave-correcta',
        ])->assertRedirect(route('pqrs.index'));
        $this->post(route('logout'));

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrecta',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertTooManyRequests();
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_their_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ]);

            $response->assertRedirect(route('login'))->assertSessionHasNoErrors();

            return Hash::check('NuevaClave123', $user->fresh()->password);
        });
    }
}
