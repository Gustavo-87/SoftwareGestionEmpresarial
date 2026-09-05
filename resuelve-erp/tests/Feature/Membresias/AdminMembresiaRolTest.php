<?php

namespace Tests\Feature\Membresias;

use App\Models\User;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMembresiaRolTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organizacion $organizacion;
    private Copropiedad $copropiedad;
    private MembresiaCopropiedad $membresia;
    private Rol $rol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'es_administrador_sistema' => true,
        ]);

        $this->organizacion = Organizacion::factory()->create();
        $this->copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
        ]);

        $this->membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'estado' => 'activa',
        ]);

        $this->rol = Rol::factory()->create([
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);
    }

    public function test_asignar_rol_a_membresia(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.roles.store', $this->membresia), [
                'rol_id' => $this->rol->id,
                'vigente_desde' => now()->format('Y-m-d'),
                'vigente_hasta' => null,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $this->membresia->id,
            'rol_id' => $this->rol->id,
        ]);
    }

    public function test_revocar_rol_de_membresia(): void
    {
        $this->membresia->roles()->attach($this->rol->id, [
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.membresias.roles.destroy', [$this->membresia, $this->rol->id]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $this->membresia->id,
            'rol_id' => $this->rol->id,
        ]);
    }

    public function test_asignar_rol_membresia_inexistente_falla(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.roles.store', ['membresia' => 99999]), [
                'rol_id' => $this->rol->id,
                'vigente_desde' => now()->format('Y-m-d'),
            ]);

        $response->assertNotFound();
    }

    public function test_asignar_rol_duplicado_falla(): void
    {
        $this->membresia->roles()->attach($this->rol->id, [
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.roles.store', $this->membresia), [
                'rol_id' => $this->rol->id,
                'vigente_desde' => now()->format('Y-m-d'),
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_asignar_rol_inexistente_falla(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.roles.store', $this->membresia), [
                'rol_id' => 99999,
                'vigente_desde' => now()->format('Y-m-d'),
            ]);

        $response->assertSessionHasErrors();
    }
}
