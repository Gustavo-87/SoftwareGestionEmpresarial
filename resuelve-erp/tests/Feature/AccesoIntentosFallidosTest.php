<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protección contra intentos fallidos de inicio de sesión (ampliación del
 * Sprint 18): tres intentos fallidos y bloqueo temporal de 15 minutos, con
 * control combinado por cuenta e IP, sin limitar los ingresos correctos.
 */
class AccesoIntentosFallidosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_bloqueo_expira_a_los_quince_minutos(): void
    {
        User::factory()->create(['email' => 'bloqueo@example.com', 'password' => 'clave-correcta']);
        $incorrectas = ['email' => 'bloqueo@example.com', 'password' => 'incorrecta'];

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), $incorrectas)->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => 'bloqueo@example.com',
            'password' => 'clave-correcta',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(15)->minutes();

        $this->post(route('login.store'), [
            'email' => 'bloqueo@example.com',
            'password' => 'clave-correcta',
        ])->assertRedirect(route('panel'));
        $this->assertAuthenticated();
    }

    public function test_el_mensaje_de_bloqueo_es_identico_exista_o_no_la_cuenta(): void
    {
        User::factory()->create(['email' => 'existe@example.com', 'password' => 'clave-correcta']);

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), ['email' => 'existe@example.com', 'password' => 'incorrecta']);
        }
        $mensajeExistente = $this->post(route('login.store'), ['email' => 'existe@example.com', 'password' => 'clave-correcta'])
            ->assertSessionHasErrors('email');
        $textoExistente = session('errors')->first('email');

        $this->travel(15)->minutes();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), ['email' => 'nadie@example.com', 'password' => 'incorrecta']);
        }
        $this->post(route('login.store'), ['email' => 'nadie@example.com', 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');
        $textoInexistente = session('errors')->first('email');

        $this->assertSame($textoExistente, $textoInexistente);
        $this->assertStringContainsString('Podrás intentarlo de nuevo en', $textoExistente);
    }

    public function test_el_limite_por_ip_frena_los_ataques_distribuidos_sin_bloquear_la_red_completa(): void
    {
        $user = User::factory()->create(['email' => 'valido@example.com', 'password' => 'clave-correcta']);

        // Veinte fallos desde una misma IP contra cuentas distintas.
        foreach (range(1, 10) as $numero) {
            foreach (range(1, 2) as $attempt) {
                $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
                    ->post(route('login.store'), ['email' => "cuenta{$numero}@example.com", 'password' => 'incorrecta']);
            }
        }

        // La IP alcanzó su límite: ni siquiera una cuenta válida entra desde ella.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post(route('login.store'), ['email' => 'valido@example.com', 'password' => 'clave-correcta'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Desde otra IP la misma cuenta ingresa: la red no queda bloqueada.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21'])
            ->post(route('login.store'), ['email' => 'valido@example.com', 'password' => 'clave-correcta'])
            ->assertRedirect(route('panel'));
    }

    public function test_un_error_aislado_no_bloquea_a_los_demas_usuarios_de_la_red(): void
    {
        $user = User::factory()->create(['email' => 'vecino@example.com', 'password' => 'clave-correcta']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->post(route('login.store'), ['email' => 'otro@example.com', 'password' => 'incorrecta'])
            ->assertRedirect();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->post(route('login.store'), ['email' => 'vecino@example.com', 'password' => 'clave-correcta'])
            ->assertRedirect(route('panel'));
    }

    public function test_el_control_no_se_evita_con_solicitudes_directas(): void
    {
        User::factory()->create(['email' => 'directo@example.com', 'password' => 'clave-correcta']);

        // Solicitudes HTTP directas al endpoint (sin interfaz) acumulan igual.
        foreach (range(1, 3) as $attempt) {
            $this->call('POST', route('login.store'), [
                '_token' => 'irrelevante-en-pruebas',
                'email' => 'directo@example.com',
                'password' => 'incorrecta',
            ])->assertRedirect();
        }

        $this->call('POST', route('login.store'), [
            '_token' => 'irrelevante-en-pruebas',
            'email' => 'directo@example.com',
            'password' => 'clave-correcta',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_los_ingresos_correctos_no_acumulan_intentos(): void
    {
        User::factory()->create(['email' => 'frecuente@example.com', 'password' => 'clave-correcta']);

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.store'), ['email' => 'frecuente@example.com', 'password' => 'clave-correcta'])
                ->assertRedirect(route('panel'));
            $this->post(route('logout'));
        }

        $this->post(route('login.store'), ['email' => 'frecuente@example.com', 'password' => 'clave-correcta'])
            ->assertRedirect(route('panel'));
        $this->assertAuthenticated();
    }
}
