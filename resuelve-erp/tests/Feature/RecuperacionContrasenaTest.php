<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

/**
 * Recuperación de contraseña (ampliación del Sprint 18): solicitud y envío del
 * enlace, formulario, caducidad, uso único, limitación de solicitudes y
 * mensajes que no revelan si la cuenta existe.
 */
class RecuperacionContrasenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_solicitud_es_neutra_exista_o_no_la_cuenta(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'existe@example.com']);

        $existente = $this->post(route('password.email'), ['email' => 'existe@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $mensajeExistente = session('success');

        $inexistente = $this->post(route('password.email'), ['email' => 'nadie@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $mensajeInexistente = session('success');

        $this->assertSame($mensajeExistente, $mensajeInexistente);
        $this->assertStringContainsString('Si existe una cuenta con ese correo', (string) $mensajeExistente);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_el_flujo_completo_envia_el_enlace_y_cambia_la_contrasena(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'fluido@example.com', 'password' => 'clave-antigua']);

        $this->post(route('password.email'), ['email' => 'fluido@example.com'])->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'fluido@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->password));
        $this->post(route('login.store'), ['email' => 'fluido@example.com', 'password' => 'NuevaClave123'])
            ->assertRedirect(route('panel'));
    }

    public function test_el_enlace_caduca_y_no_puede_usarse(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'caduca@example.com', 'password' => 'clave-antigua']);

        $this->post(route('password.email'), ['email' => 'caduca@example.com']);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'caduca@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('clave-antigua', $user->fresh()->password));
    }

    public function test_el_enlace_no_puede_utilizarse_dos_veces(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'unico@example.com', 'password' => 'clave-antigua']);

        $this->post(route('password.email'), ['email' => 'unico@example.com']);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'unico@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertSessionHasNoErrors();

        // Segundo uso del mismo enlace: queda inutilizable.
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'unico@example.com',
            'password' => 'OtraClave123',
            'password_confirmation' => 'OtraClave123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->password));
    }

    public function test_la_solicitud_de_recuperacion_se_limita_sin_revelar_la_cuenta(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'limitada@example.com']);

        // Entre solicitudes se supera el throttle interno del broker (60 s),
        // de modo que los tres envíos se realizan y queda el límite propio.
        foreach (range(1, 3) as $attempt) {
            $this->post(route('password.email'), ['email' => 'limitada@example.com'])
                ->assertSessionHasNoErrors();
            $this->travel(2)->minutes();
        }
        $mensajeTrasLimite = session('success');

        $this->post(route('password.email'), ['email' => 'limitada@example.com'])
            ->assertSessionHasNoErrors();

        // Mismo mensaje que en una solicitud normal y sin envíos adicionales.
        $this->assertSame($mensajeTrasLimite, session('success'));
        Notification::assertSentToTimes($user, ResetPassword::class, 3);
    }

    public function test_la_solicitud_de_restablecimiento_no_revela_si_la_cuenta_existe(): void
    {
        $respuesta = $this->post(route('password.update'), [
            'token' => 'token-inventado',
            'email' => 'nadie@example.com',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertStringContainsString('El enlace no es válido o ha caducado', session('errors')->first('email'));
    }
}
