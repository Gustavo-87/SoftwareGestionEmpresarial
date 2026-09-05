<?php

namespace Tests\Feature\Admin;

use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizacionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_administrador_sistema' => true]);
    }

    public function test_admin_puede_listar_organizaciones(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/organizaciones')
            ->assertOk()
            ->assertSee('Organizaciones');
    }

    public function test_usuario_no_admin_no_puede_listar(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)
            ->get('/admin/organizaciones')
            ->assertForbidden();
    }

    public function test_admin_puede_ver_formulario_creacion(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/organizaciones/create')
            ->assertOk();
    }

    public function test_admin_puede_crear_organizacion(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/organizaciones', [
                'nombre' => 'Organización Test',
                'identificacion_tributaria' => '900123456-7',
                'email' => 'test@org.com',
                'telefono' => '+573001234567',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizaciones', [
            'nombre' => 'Organización Test',
            'estado' => 'activa',
        ]);

        $org = Organizacion::where('nombre', 'Organización Test')->first();
        $this->assertDatabaseHas('configuraciones_organizacion', [
            'organizacion_id' => $org->id,
        ]);
    }

    public function test_nombre_es_obligatorio(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/organizaciones', ['nombre' => ''])
            ->assertSessionHasErrors('nombre');
    }

    public function test_nit_debe_ser_unico(): void
    {
        Organizacion::create(['nombre' => 'Org 1', 'identificacion_tributaria' => '900111111-1', 'estado' => 'activa']);

        $this->actingAs($this->admin)
            ->post('/admin/organizaciones', [
                'nombre' => 'Org 2',
                'identificacion_tributaria' => '900111111-1',
            ])
            ->assertSessionHasErrors('identificacion_tributaria');
    }

    public function test_admin_puede_ver_detalle(): void
    {
        $org = Organizacion::create(['nombre' => 'Org Detalle', 'estado' => 'activa']);

        $this->actingAs($this->admin)
            ->get("/admin/organizaciones/{$org->id}")
            ->assertOk()
            ->assertSee('Org Detalle');
    }

    public function test_admin_puede_editar(): void
    {
        $org = Organizacion::create(['nombre' => 'Org Original', 'estado' => 'activa']);

        $this->actingAs($this->admin)
            ->put("/admin/organizaciones/{$org->id}", ['nombre' => 'Org Editada'])
            ->assertRedirect();

        $this->assertDatabaseHas('organizaciones', [
            'id' => $org->id,
            'nombre' => 'Org Editada',
        ]);
    }

    public function test_admin_puede_desactivar(): void
    {
        $org = Organizacion::create(['nombre' => 'Org Desactivar', 'estado' => 'activa']);

        $this->actingAs($this->admin)
            ->patch("/admin/organizaciones/{$org->id}/desactivar", ['motivo' => 'Prueba'])
            ->assertRedirect();

        $org->refresh();
        $this->assertEquals('inactiva', $org->estado);
        $this->assertNotNull($org->desactivada_at);
    }

    public function test_admin_puede_reactivar(): void
    {
        $org = Organizacion::create(['nombre' => 'Org Reactivar', 'estado' => 'inactiva', 'desactivada_at' => now()]);

        $this->actingAs($this->admin)
            ->patch("/admin/organizaciones/{$org->id}/reactivar")
            ->assertRedirect();

        $org->refresh();
        $this->assertEquals('activa', $org->estado);
        $this->assertNull($org->desactivada_at);
    }

    public function test_no_puede_desactivar_con_copropiedades_activas(): void
    {
        $org = Organizacion::create(['nombre' => 'Org Con Cop', 'estado' => 'activa']);
        \App\Models\Copropiedad::create([
            'organizacion_id' => $org->id,
            'nombre' => 'Cop Test',
            'estado' => 'activa',
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/organizaciones/{$org->id}/desactivar", ['motivo' => 'Prueba'])
            ->assertSessionHasErrors('motivo');
    }

    public function test_creacion_genera_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/organizaciones', ['nombre' => 'Org Audit']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'organizacion.store',
        ]);
    }
}
