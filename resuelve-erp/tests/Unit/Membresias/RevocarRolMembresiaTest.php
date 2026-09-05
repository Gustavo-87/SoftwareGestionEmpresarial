<?php

namespace Tests\Unit\Membresias;

use App\Application\Membresias\RevocarRolMembresia;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class RevocarRolMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private RevocarRolMembresia $revocarRol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->revocarRol = new RevocarRolMembresia();
    }

    public function test_revocar_rol_exitoso(): void
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

        $this->revocarRol->ejecutar($membresia, $rol->id);

        $this->assertDatabaseMissing('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rol->id,
        ]);
    }

    public function test_revocar_rol_inexistente_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->revocarRol->ejecutar($membresia, 99999);
    }
}
