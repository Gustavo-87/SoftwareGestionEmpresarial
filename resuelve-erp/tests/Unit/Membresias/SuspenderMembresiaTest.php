<?php

namespace Tests\Unit\Membresias;

use App\Application\Membresias\SuspenderMembresia;
use App\Models\MembresiaCopropiedad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class SuspenderMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private SuspenderMembresia $suspenderMembresia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suspenderMembresia = new SuspenderMembresia();
    }

    public function test_suspender_membresia_exitosa(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $resultado = $this->suspenderMembresia->ejecutar($membresia, 'Motivo de prueba');

        $this->assertEquals('suspendida', $resultado->estado);
        $this->assertNotNull($resultado->motivo_terminacion);
    }

    public function test_suspender_membresia_no_activa_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'finalizada',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->suspenderMembresia->ejecutar($membresia, 'Motivo');
    }

    public function test_suspender_registra_motivo(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->suspenderMembresia->ejecutar($membresia, 'Motivo específico');

        $this->assertEquals('Motivo específico', $membresia->fresh()->motivo_terminacion);
    }

    public function test_suspender_sin_motivo_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->suspenderMembresia->ejecutar($membresia, '');
    }
}
