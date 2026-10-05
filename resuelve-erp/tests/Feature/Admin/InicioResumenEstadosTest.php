<?php

namespace Tests\Feature\Admin;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resumen de plataforma en Inicio: las tarjetas desglosan los estados reales
 * de cada modelo (activa/inactiva, activo/inactivo, activa/suspendida/finalizada).
 */
class InicioResumenEstadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_tarjetas_de_inicio_desglosan_los_estados_reales(): void
    {
        $activa = Organizacion::create(['nombre' => 'Org activa', 'estado' => 'activa']);
        Organizacion::create(['nombre' => 'Org segunda activa', 'estado' => 'activa']);
        Organizacion::create(['nombre' => 'Org inactiva', 'estado' => 'inactiva']);

        $copropiedad = Copropiedad::create(['organizacion_id' => $activa->id, 'nombre' => 'Cop activa', 'estado' => 'activa']);
        Copropiedad::create(['organizacion_id' => $activa->id, 'nombre' => 'Cop inactiva', 'estado' => 'inactiva']);

        $plataforma = User::factory()->create(['es_administrador_sistema' => true, 'estado' => 'activo']);
        $miembroActivo = User::factory()->create(['estado' => 'activo']);
        $miembroSuspendido = User::factory()->create(['estado' => 'activo']);
        $miembroFinalizado = User::factory()->create(['estado' => 'inactivo']);

        foreach ([
            ['activa', $miembroActivo],
            ['suspendida', $miembroSuspendido],
            ['finalizada', $miembroFinalizado],
        ] as [$estado, $usuario]) {
            MembresiaCopropiedad::create([
                'usuario_id' => $usuario->id, 'organizacion_id' => $activa->id,
                'copropiedad_id' => $copropiedad->id, 'estado' => $estado, 'vigente_desde' => now()->subDay(),
            ]);
        }

        $respuesta = $this->actingAs($plataforma)->get(route('admin.index'))->assertOk();

        $respuesta->assertViewHas('organizacionesActivas', 2);
        $respuesta->assertViewHas('organizacionesInactivas', 1);
        $respuesta->assertViewHas('copropiedadesActivas', 1);
        $respuesta->assertViewHas('copropiedadesInactivas', 1);
        $respuesta->assertViewHas('usuariosActivos', 3);
        $respuesta->assertViewHas('usuariosInactivos', 1);
        $respuesta->assertViewHas('membresiasActivas', 1);
        $respuesta->assertViewHas('membresiasSuspendidas', 1);
        $respuesta->assertViewHas('membresiasFinalizadas', 1);

        $respuesta->assertSee('2 activas · 1 inactiva');
        $respuesta->assertSee('1 activa · 1 suspendida · 1 finalizada');
        $respuesta->assertSee('3 activos · 1 inactivo');
    }
}
