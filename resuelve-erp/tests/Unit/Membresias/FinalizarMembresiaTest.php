<?php

namespace Tests\Unit\Membresias;

use App\Application\Membresias\FinalizarMembresia;
use App\Models\MembresiaCopropiedad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class FinalizarMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private FinalizarMembresia $finalizarMembresia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finalizarMembresia = new FinalizarMembresia();
    }

    public function test_finalizar_membresia_exitosa(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $resultado = $this->finalizarMembresia->ejecutar($membresia, 'Motivo de finalización');

        $this->assertEquals('finalizada', $resultado->estado);
        $this->assertNotNull($resultado->motivo_terminacion);
    }

    public function test_finalizar_membresia_suspendida_exitosa(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'suspendida',
        ]);

        $resultado = $this->finalizarMembresia->ejecutar($membresia, 'Finalizar suspendida');

        $this->assertEquals('finalizada', $resultado->estado);
    }

    public function test_finalizar_membresia_ya_finalizada_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'finalizada',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->finalizarMembresia->ejecutar($membresia, 'Motivo');
    }

    public function test_finalizar_registra_motivo(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->finalizarMembresia->ejecutar($membresia, 'Motivo específico');

        $this->assertEquals('Motivo específico', $membresia->fresh()->motivo_terminacion);
    }

    public function test_finalizar_sin_motivo_falla(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'estado' => 'activa',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->finalizarMembresia->ejecutar($membresia, '');
    }
}
