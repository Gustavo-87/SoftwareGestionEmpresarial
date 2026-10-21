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
            ->assertRedirect(route('panel'));
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
        ])->assertRedirect(route('panel'));

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
        ])->assertRedirect(route('panel'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_outer_spaces_in_the_password(): void
    {
        $user = User::factory()->create(['password' => ' 12345 ']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => ' 12345 ',
        ])->assertRedirect(route('panel'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => '12345']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_tercer_intento_fallido_activa_el_bloqueo_temporal(): void
    {
        User::factory()->create(['email' => 'limit@example.com', 'password' => 'clave-correcta']);
        $incorrectas = ['email' => 'limit@example.com', 'password' => 'incorrecta'];

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), $incorrectas)
                ->assertRedirect()
                ->assertSessionHasErrors('email');
        }

        // Bloqueo temporal: ni siquiera la contraseña correcta ingresa.
        $respuesta = $this->post(route('login.store'), [
            'email' => 'limit@example.com',
            'password' => 'clave-correcta',
        ]);

        $respuesta->assertRedirect()->assertSessionHasErrors('email');
        $this->assertStringContainsString('Podrás intentarlo de nuevo en', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_limit_is_isolated_by_normalized_email_and_ip(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('login.store'), [
                    'email' => '  LIMIT@EXAMPLE.COM ',
                    'password' => 'incorrecta',
                ]);
        }

        // Otra cuenta desde la misma IP sigue disponible (el control por IP es
        // más amplio para no bloquear a una red por un único error).
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login.store'), [
                'email' => 'otro@example.com',
                'password' => 'incorrecta',
            ])->assertRedirect();

        // La cuenta bloqueada lo sigue estando desde otra IP.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('login.store'), [
                'email' => 'limit@example.com',
                'password' => 'incorrecta',
            ])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Podrás intentarlo de nuevo en', session('errors')->first('email'));
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'limpieza@example.com',
            'password' => 'clave-correcta',
        ]);

        foreach (range(1, 2) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrecta',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'clave-correcta',
        ])->assertRedirect();
        $this->assertAuthenticated();
        $this->post(route('logout'));

        // El ingreso correcto limpió los intentos: empieza de cero.
        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrecta',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Podrás intentarlo de nuevo en', session('errors')->first('email'));
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
