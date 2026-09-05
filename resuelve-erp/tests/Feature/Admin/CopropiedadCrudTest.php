<?php

namespace Tests\Feature\Admin;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopropiedadCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organizacion $organizacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_administrador_sistema' => true]);
        $this->organizacion = Organizacion::factory()->create(['estado' => 'activa']);
    }

    public function test_admin_puede_listar_copropiedades(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/copropiedades')
            ->assertOk()
            ->assertSee('Copropiedades');
    }

    public function test_usuario_no_admin_no_puede_listar(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)
            ->get('/admin/copropiedades')
            ->assertForbidden();
    }

    public function test_admin_puede_ver_formulario_creacion(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/copropiedades/create')
            ->assertOk();
    }

    public function test_admin_puede_crear_copropiedad(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $this->organizacion->id,
                'nombre' => 'Conjunto Test',
                'ciudad' => 'Bogotá',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('copropiedades', [
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Conjunto Test',
            'estado' => 'activa',
        ]);
    }

    public function test_nombre_es_obligatorio(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $this->organizacion->id,
                'nombre' => '',
            ])
            ->assertSessionHasErrors('nombre');
    }

    public function test_organizacion_padre_es_obligatoria(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => '',
                'nombre' => 'Sin Org',
            ])
            ->assertSessionHasErrors('organizacion_id');
    }

    public function test_no_puede_crear_en_organizacion_desactivada(): void
    {
        $orgInactiva = Organizacion::factory()->create(['estado' => 'inactiva']);

        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $orgInactiva->id,
                'nombre' => 'Cop en Org Inactiva',
            ])
            ->assertSessionHasErrors('nombre');
    }

    public function test_nit_debe_ser_unico_por_organizacion(): void
    {
        Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop 1',
            'nit' => '800123456-1',
            'estado' => 'activa',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $this->organizacion->id,
                'nombre' => 'Cop 2',
                'nit' => '800123456-1',
            ])
            ->assertSessionHasErrors('nombre');
    }

    public function test_admin_puede_ver_detalle(): void
    {
        $cop = Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop Detalle',
            'estado' => 'activa',
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/copropiedades/{$cop->id}")
            ->assertOk()
            ->assertSee('Cop Detalle');
    }

    public function test_admin_puede_editar(): void
    {
        $cop = Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop Original',
            'estado' => 'activa',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/copropiedades/{$cop->id}", ['nombre' => 'Cop Editada'])
            ->assertRedirect();

        $this->assertDatabaseHas('copropiedades', [
            'id' => $cop->id,
            'nombre' => 'Cop Editada',
        ]);
    }

    public function test_admin_puede_desactivar(): void
    {
        $cop = Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop Desactivar',
            'estado' => 'activa',
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/copropiedades/{$cop->id}/desactivar", ['motivo' => 'Prueba'])
            ->assertRedirect();

        $cop->refresh();
        $this->assertEquals('inactiva', $cop->estado);
        $this->assertNotNull($cop->desactivada_at);
    }

    public function test_no_puede_desactivar_con_membresias_activas(): void
    {
        $cop = Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop Con Membresías',
            'estado' => 'activa',
        ]);

        \App\Models\MembresiaCopropiedad::create([
            'usuario_id' => User::factory()->create()->id,
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $cop->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/copropiedades/{$cop->id}/desactivar", ['motivo' => 'Prueba'])
            ->assertSessionHasErrors('motivo');
    }

    public function test_admin_puede_reactivar(): void
    {
        $cop = Copropiedad::create([
            'organizacion_id' => $this->organizacion->id,
            'nombre' => 'Cop Reactivar',
            'estado' => 'inactiva',
            'desactivada_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/copropiedades/{$cop->id}/reactivar")
            ->assertRedirect();

        $cop->refresh();
        $this->assertEquals('activa', $cop->estado);
    }

    public function test_creacion_genera_configuracion_automatica(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $this->organizacion->id,
                'nombre' => 'Cop Con Config',
            ]);

        $cop = Copropiedad::where('nombre', 'Cop Con Config')->first();
        $this->assertDatabaseHas('configuraciones_copropiedad', [
            'copropiedad_id' => $cop->id,
        ]);
    }

    public function test_creacion_genera_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/copropiedades', [
                'organizacion_id' => $this->organizacion->id,
                'nombre' => 'Cop Audit',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'copropiedad.store',
        ]);
    }
}
