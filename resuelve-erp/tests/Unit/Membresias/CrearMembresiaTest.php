<?php

namespace Tests\Unit\Membresias;

use App\Application\Membresias\CrearMembresia;
use App\Models\User;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class CrearMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private CrearMembresia $crearMembresia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearMembresia = new CrearMembresia();
    }

    public function test_crear_membresia_exitosa(): void
    {
        $usuario = User::factory()->create();
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $organizacion->id,
        ]);

        $membresia = $this->crearMembresia->ejecutar(
            $usuario->id,
            $organizacion->id,
            $copropiedad->id,
            now()->format('Y-m-d'),
            now()->addYear()->format('Y-m-d'),
        );

        $this->assertNotNull($membresia);
        $this->assertEquals($usuario->id, $membresia->usuario_id);
        $this->assertEquals('activa', $membresia->estado);
    }

    public function test_crear_membresia_duplicada_falla(): void
    {
        $usuario = User::factory()->create();
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $organizacion->id,
        ]);

        MembresiaCopropiedad::factory()->create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->crearMembresia->ejecutar(
            $usuario->id,
            $organizacion->id,
            $copropiedad->id,
            now()->format('Y-m-d'),
        );
    }

    public function test_crear_membresia_registra_creada_por(): void
    {
        $usuario = User::factory()->create();
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $organizacion->id,
        ]);
        $creador = User::factory()->create();

        $membresia = $this->crearMembresia->ejecutar(
            $usuario->id,
            $organizacion->id,
            $copropiedad->id,
            now()->format('Y-m-d'),
            null,
            $creador->id,
        );

        $this->assertEquals($creador->id, $membresia->creada_por);
    }

    public function test_crear_membresia_copropiedad_no_pertenece_organizacion_falla(): void
    {
        $usuario = User::factory()->create();
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->crearMembresia->ejecutar(
            $usuario->id,
            $organizacion->id,
            $copropiedad->id,
            now()->format('Y-m-d'),
        );
    }

    public function test_crear_membresia_fechas_invalidas_falla(): void
    {
        $usuario = User::factory()->create();
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $organizacion->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->crearMembresia->ejecutar(
            $usuario->id,
            $organizacion->id,
            $copropiedad->id,
            now()->format('Y-m-d'),
            now()->subYear()->format('Y-m-d'),
        );
    }
}
