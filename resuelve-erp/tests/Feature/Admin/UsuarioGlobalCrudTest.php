<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioGlobalCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_administrador_sistema' => true]);
    }

    public function test_admin_puede_listar_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/usuarios-globales')
            ->assertOk()
            ->assertSee('Usuarios Globales');
    }

    public function test_usuario_no_admin_no_puede_listar(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)
            ->get('/admin/usuarios-globales')
            ->assertForbidden();
    }

    public function test_admin_puede_ver_formulario_creacion(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/usuarios-globales/create')
            ->assertOk();
    }

    public function test_admin_puede_crear_usuario(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@test.com',
            'estado' => 'activo',
            'role' => 'residente',
        ]);
    }

    public function test_usuario_creado_tiene_estado_activo(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Test Estado',
                'email' => 'estado@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $user = User::where('email', 'estado@test.com')->first();
        $this->assertEquals('activo', $user->estado);
        $this->assertNull($user->desactivado_at);
    }

    public function test_crear_admin_sistema(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Admin Test',
                'email' => 'admin@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'es_administrador_sistema' => '1',
            ]);

        $user = User::where('email', 'admin@test.com')->first();
        $this->assertTrue($user->es_administrador_sistema);
    }

    public function test_email_debe_ser_unico(): void
    {
        User::factory()->create(['email' => 'duplicado@test.com']);

        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Duplicado',
                'email' => 'duplicado@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_minimo_8_caracteres(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Corto',
                'email' => 'corto@test.com',
                'password' => '1234567',
                'password_confirmation' => '1234567',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_admin_puede_ver_detalle(): void
    {
        $user = User::factory()->create(['name' => 'Detalle Test']);

        $this->actingAs($this->admin)
            ->get("/admin/usuarios-globales/{$user->id}")
            ->assertOk()
            ->assertSee('Detalle Test');
    }

    public function test_admin_puede_editar(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $this->actingAs($this->admin)
            ->put("/admin/usuarios-globales/{$user->id}", [
                'name' => 'Editado',
                'email' => $user->email,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Editado',
        ]);
    }

    public function test_admin_puede_desactivar(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $this->actingAs($this->admin)
            ->patch("/admin/usuarios-globales/{$user->id}/desactivar")
            ->assertRedirect();

        $user->refresh();
        $this->assertEquals('inactivo', $user->estado);
        $this->assertNotNull($user->desactivado_at);
    }

    public function test_no_puede_desactivar_su_propia_cuenta(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/usuarios-globales/{$this->admin->id}/desactivar")
            ->assertSessionHasErrors('estado');
    }

    public function test_ultimo_admin_no_puede_ser_desactivado(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/usuarios-globales/{$this->admin->id}/desactivar")
            ->assertSessionHasErrors('estado');
    }

    public function test_admin_puede_reactivar(): void
    {
        $user = User::factory()->create([
            'estado' => 'inactivo',
            'desactivado_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/usuarios-globales/{$user->id}/reactivar")
            ->assertRedirect();

        $user->refresh();
        $this->assertEquals('activo', $user->estado);
        $this->assertNull($user->desactivado_at);
    }

    public function test_desactivar_suspende_membresias(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $org = \App\Models\Organizacion::factory()->create(['estado' => 'activa']);
        $cop = \App\Models\Copropiedad::factory()->create([
            'organizacion_id' => $org->id,
            'estado' => 'activa',
        ]);

        \App\Models\MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $org->id,
            'copropiedad_id' => $cop->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/usuarios-globales/{$user->id}/desactivar");

        $this->assertDatabaseHas('membresias_copropiedad', [
            'usuario_id' => $user->id,
            'estado' => 'suspendida',
        ]);
    }

    public function test_creacion_genera_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios-globales', [
                'name' => 'Audit User',
                'email' => 'audit@test.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'usuario_global.store',
        ]);
    }
}
