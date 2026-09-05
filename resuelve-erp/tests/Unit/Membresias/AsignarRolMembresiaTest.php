<?php

namespace Tests\Unit\Membresias;

use App\Application\Membresias\AsignarRolMembresia;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class AsignarRolMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private AsignarRolMembresia $asignarRol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asignarRol = new AsignarRolMembresia();
    }

    public function test_asignar_rol_exitoso(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);
        $rol = Rol::factory()->create([
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);

        $this->asignarRol->ejecutar(
            $membresia,
            $rol->id,
            now()->format('Y-m-d'),
        );

        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rol->id,
        ]);
    }

    public function test_asignar_rol_duplicado_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);
        $rol = Rol::factory()->create([
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);

        $membresia->roles()->attach($rol->id, [
            'organizacion_id' => $membresia->organizacion_id,
            'copropiedad_id' => $membresia->copropiedad_id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->asignarRol->ejecutar(
            $membresia,
            $rol->id,
            now()->format('Y-m-d'),
        );
    }

    public function test_asignar_rol_inexistente_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->asignarRol->ejecutar(
            $membresia,
            99999,
            now()->format('Y-m-d'),
        );
    }

    public function test_asignar_rol_registra_asignado_por(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);
        $rol = Rol::factory()->create([
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);
        $asignador = User::factory()->create();

        $this->asignarRol->ejecutar(
            $membresia,
            $rol->id,
            now()->format('Y-m-d'),
            null,
            $asignador->id,
        );

        $pivot = $membresia->roles()->where('rol_id', $rol->id)->first();
        $this->assertEquals($asignador->id, $pivot->pivot->asignado_por);
    }
}
