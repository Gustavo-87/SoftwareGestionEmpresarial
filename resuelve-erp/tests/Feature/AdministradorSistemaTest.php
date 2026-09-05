<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministradorSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_sistema_puede_acceder_a_admin(): void
    {
        $admin = User::factory()->create(['es_administrador_sistema' => true]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Administración del sistema');
    }

    public function test_usuario_normal_recibe_403(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_copropiedad_sin_autoridad_sistema_recibe_403(): void
    {
        $adminCopropiedad = User::factory()->create([
            'role' => 'admin',
            'es_administrador_sistema' => false,
        ]);

        $response = $this->actingAs($adminCopropiedad)->get('/admin');

        $response->assertForbidden();
    }

    public function test_no_autenticado_redirige_a_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/iniciar-sesion');
    }

    public function test_users_role_admin_no_concede_acceso_administrativo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->esAdministradorSistema());
        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_campo_default_false(): void
    {
        $usuario = User::factory()->create();
        $usuario->refresh();

        $this->assertFalse($usuario->es_administrador_sistema);
        $this->assertFalse($usuario->esAdministradorSistema());
    }

    public function test_gate_administrar_sistema(): void
    {
        $admin = User::factory()->create(['es_administrador_sistema' => true]);
        $usuario = User::factory()->create();

        $this->assertTrue($admin->can('administrar-sistema'));
        $this->assertFalse($usuario->can('administrar-sistema'));
    }

    public function test_comando_promover_admin_sistema(): void
    {
        $usuario = User::factory()->create(['email' => 'nuevo@test.com']);

        $this->artisan('resuelve:promover-admin-sistema', ['email' => 'nuevo@test.com'])
            ->assertSuccessful()
            ->expectsOutputToContain('promovido');

        $usuario->refresh();
        $this->assertTrue($usuario->es_administrador_sistema);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $usuario->id,
            'action' => 'system_admin.promoted',
        ]);
    }

    public function test_comando_evita_duplicidad(): void
    {
        $usuario = User::factory()->create([
            'email' => 'ya@test.com',
            'es_administrador_sistema' => true,
        ]);

        $this->artisan('resuelve:promover-admin-sistema', ['email' => 'ya@test.com'])
            ->assertSuccessful()
            ->expectsOutputToContain('ya es administrador');
    }

    public function test_comando_rechaza_usuario_inexistente(): void
    {
        $this->artisan('resuelve:promover-admin-sistema', ['email' => 'noexiste@test.com'])
            ->assertFailed()
            ->expectsOutputToContain('No existe');
    }

    public function test_acceso_no_depende_de_copropiedad_activa(): void
    {
        $admin = User::factory()->create(['es_administrador_sistema' => true]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $this->assertNull(session('copropiedad_activa_id'));
    }

    public function test_traduccion_auditoria_promocion(): void
    {
        $usuario = User::factory()->create();

        AuditLog::create([
            'user_id' => $usuario->id,
            'action' => 'system_admin.promoted',
            'auditable_type' => 'user',
            'auditable_id' => $usuario->id,
            'metadata' => ['email' => $usuario->email, 'metodo' => 'comando artisan'],
        ]);

        $log = AuditLog::first();
        $this->assertSame('Promovió administrador del sistema', $log->human_action);
    }
}
