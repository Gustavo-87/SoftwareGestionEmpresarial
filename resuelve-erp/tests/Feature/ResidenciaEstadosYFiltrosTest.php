<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Persona;
use App\Models\UnidadPrivada;
use App\Models\User;
use App\Models\VinculoUnidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Estados administrativos, filtros y correcciones de vigencia
 * (HU-RYP-01, Sprint 20) — bloque 3. La desactivación no elimina registros ni
 * concluye vínculos, y las correcciones de fechas son justificadas y auditadas.
 */
class ResidenciaEstadosYFiltrosTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_desactivar_y_reactivar_unidad_conserva_los_vinculos_sin_concluirlos(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $vinculo = $this->vincular($gestor, $unidad, $this->persona($organizacion->id, 'Laura Gómez'), 'residente', '2026-01-01');
        $membresiasAntes = MembresiaCopropiedad::count();
        $rolesAntes = DB::table('model_has_roles')->count();

        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.desactivar', $unidad))
            ->assertRedirect(route('management.unidades.index'));

        $desactivada = $unidad->fresh();
        $this->assertSame('desactivada', $desactivada->estado);
        $this->assertNotNull($desactivada->desactivada_at);
        // Sin eliminación y sin conclusión automática de vínculos.
        $this->assertSame(1, UnidadPrivada::count());
        $this->assertSame('activo', $vinculo->fresh()->estado);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.unidad.desactivar']);
        $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidad))
            ->assertOk()
            ->assertSee('Unidad desactivada')
            ->assertSee('no se concluyen automáticamente');

        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.reactivar', $unidad))
            ->assertRedirect(route('management.unidades.index'));
        $this->assertSame('activa', $unidad->fresh()->estado);
        $this->assertNull($unidad->fresh()->desactivada_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.unidad.reactivar']);

        // La desactivación de residencia no toca membresías ni permisos.
        $this->assertSame($membresiasAntes, MembresiaCopropiedad::count());
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
    }

    public function test_desactivar_y_reactivar_persona_conserva_su_historia(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');
        $vinculo = $this->vincular($gestor, $unidad, $persona, 'propietario', '2026-01-01');

        $this->actingAsContextual($gestor)
            ->patch(route('management.personas.desactivar', $persona))
            ->assertRedirect(route('management.personas.index'));

        $desactivada = $persona->fresh();
        $this->assertSame('desactivada', $desactivada->estado);
        $this->assertNotNull($desactivada->desactivada_at);
        $this->assertSame(1, Persona::count());
        $this->assertSame('activo', $vinculo->fresh()->estado);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.persona.desactivar']);
        $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidad))
            ->assertOk()
            ->assertSee('Persona inactiva');

        $this->actingAsContextual($gestor)->patch(route('management.personas.reactivar', $persona))->assertRedirect();
        $this->assertSame('activa', $persona->fresh()->estado);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.persona.reactivar']);

        // Aislamiento: la persona de otra Organización no existe para este contexto.
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $ajena = $this->persona($otraOrganizacion->id, 'Persona foránea');
        $this->actingAsContextual($gestor)->patch(route('management.personas.desactivar', $ajena))->assertNotFound();
    }

    public function test_no_se_vinculan_unidades_ni_personas_inactivas(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');

        $this->actingAsContextual($gestor)->patch(route('management.personas.desactivar', $persona));
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
        ])->assertSessionHasErrors('persona_id');
        $this->assertSame(0, VinculoUnidad::count());

        $this->actingAsContextual($gestor)->patch(route('management.personas.reactivar', $persona));
        $this->actingAsContextual($gestor)->patch(route('management.unidades.desactivar', $unidad));
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
        ])->assertSessionHasErrors('vinculo');
        $this->assertSame(0, VinculoUnidad::count());
    }

    public function test_filtros_de_unidades_y_busqueda_paginada_de_personas(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $this->unidad($organizacion->id, $copropiedad->id, 'B-201');
        $desactivada = $this->unidad($organizacion->id, $copropiedad->id, 'C-301');
        $this->actingAsContextual($gestor)->patch(route('management.unidades.desactivar', $desactivada));

        $this->actingAsContextual($gestor)->get(route('management.unidades.index', ['q' => 'B-20']))
            ->assertOk()->assertSee('B-201')->assertDontSee('C-301');
        $this->actingAsContextual($gestor)->get(route('management.unidades.index', ['estado' => 'desactivada']))
            ->assertOk()->assertSee('C-301')->assertDontSee('B-201');

        // Selector de personas: búsqueda, paginación y solo personas activas.
        foreach (range(1, 25) as $numero) {
            $this->persona($organizacion->id, 'Persona '.str_pad((string) $numero, 2, '0', STR_PAD_LEFT));
        }
        $inactiva = $this->persona($organizacion->id, 'Persona Oculta');
        $this->actingAsContextual($gestor)->patch(route('management.personas.desactivar', $inactiva));

        $respuesta = $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidad))->assertOk();
        $respuesta->assertSee('Persona 01')->assertDontSee('Persona 25')->assertDontSee('Persona Oculta');

        $this->actingAsContextual($gestor)->get(route('management.unidades.show', [$unidad, 'q_persona' => 'Persona 25']))
            ->assertOk()->assertSee('Persona 25')->assertDontSee('Persona 01');

        // Página 2 del selector.
        $this->actingAsContextual($gestor)->get(route('management.unidades.show', [$unidad, 'page' => 2]))
            ->assertOk()->assertSee('Persona 25')->assertDontSee('Persona 01');
    }

    public function test_correccion_justificada_de_vigencias_audita_antes_y_despues(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');
        $vinculo = $this->vincular($gestor, $unidad, $persona, 'residente', '2026-01-01');

        // Sin motivo no hay corrección.
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.corregir', [$unidad, $vinculo]), [
                'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-12-31',
            ])->assertSessionHasErrors('motivo');

        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.corregir', [$unidad, $vinculo]), [
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => '2026-12-31',
                'motivo' => 'Corrección de captura de la fecha final del contrato.',
            ])->assertRedirect(route('management.unidades.show', $unidad));

        $corregido = $vinculo->fresh();
        $this->assertSame('2026-12-31', $corregido->vigente_hasta->format('Y-m-d'));
        $auditoria = AuditLog::query()->where('action', 'residencia.vinculo.corregir')->firstOrFail();
        $this->assertSame('2026-01-01', $auditoria->metadata['antes']['vigente_desde']);
        $this->assertNull($auditoria->metadata['antes']['vigente_hasta']);
        $this->assertSame('2026-12-31', $auditoria->metadata['despues']['vigente_hasta']);
        $this->assertStringContainsString('Corrección de captura', $auditoria->metadata['motivo']);

        // La corrección reevalúa solapamientos y solo cambia fechas.
        $otro = $this->vincular($gestor, $unidad, $persona, 'propietario', '2026-01-01');
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.corregir', [$unidad, $otro]), [
                'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-05-31', 'motivo' => 'Ajuste de periodo.',
            ])->assertRedirect();
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.corregir', [$unidad, $otro]), [
                'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-08-31', 'motivo' => 'Ajuste de periodo.',
            ])->assertRedirect();
        $this->assertSame('propietario', $otro->fresh()->tipo_vinculo->value);

        // Un vínculo concluido no puede quedar sin fecha final.
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.concluir', [$unidad, $otro]), ['vigente_hasta' => '2026-08-31']);
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.corregir', [$unidad, $otro->fresh()]), [
                'vigente_desde' => '2026-01-01', 'motivo' => 'Corrección inválida.',
            ])->assertSessionHasErrors('vigente_hasta');
    }

    /** @return array{0:\App\Models\Organizacion,1:\App\Models\Copropiedad,2:User,3:UnidadPrivada} */
    private function escenario(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['residentes.gestionar']);
        $unidad = $this->unidad($organizacion->id, $copropiedad->id, 'A-101');

        return [$organizacion, $copropiedad, $gestor, $unidad];
    }

    private function persona(int $organizacionId, string $nombre): Persona
    {
        $persona = new Persona();
        $persona->forceFill(['organizacion_id' => $organizacionId, 'tipo_persona' => 'natural', 'nombre_razon_social' => $nombre])->save();

        return $persona;
    }

    private function unidad(int $organizacionId, int $copropiedadId, string $codigo): UnidadPrivada
    {
        $unidad = new UnidadPrivada();
        $unidad->forceFill(['organizacion_id' => $organizacionId, 'copropiedad_id' => $copropiedadId, 'codigo' => $codigo])->save();

        return $unidad;
    }

    private function vincular(User $gestor, UnidadPrivada $unidad, Persona $persona, string $tipo, string $desde): VinculoUnidad
    {
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => $tipo,
            'vigente_desde' => $desde,
        ])->assertRedirect(route('management.unidades.show', $unidad));

        return VinculoUnidad::query()->where('tipo_vinculo', $tipo)->latest('id')->firstOrFail();
    }
}
