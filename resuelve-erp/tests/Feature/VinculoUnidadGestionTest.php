<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
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
 * Vínculos residenciales Persona ↔ Unidad (HU-RYP-01, Sprint 20) — bloque 2:
 * registro de personas, asociación con vigencias, historial, autorización,
 * aislamiento contextual y separación de membresías/permisos de acceso.
 */
class VinculoUnidadGestionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_registra_persona_y_asocia_vinculo_con_vigencias(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $usuariosAntes = User::count();
        $membresiasAntes = MembresiaCopropiedad::count();
        $rolesAntes = DB::table('model_has_roles')->count();

        // Registro de persona: pertenece a la Organización y NO crea cuenta de usuario.
        $this->actingAsContextual($gestor)->post(route('management.personas.store'), [
            'tipo_persona' => 'natural',
            'nombre_razon_social' => 'Laura Gómez',
            'identificacion' => 'CC-1020',
            'organizacion_id' => 999,
        ])->assertRedirect();
        $this->assertDatabaseHas('personas', [
            'nombre_razon_social' => 'Laura Gómez',
            'organizacion_id' => $organizacion->id,
            'identificacion' => 'CC-1020',
            'usuario_id' => null,
            'estado' => 'activa',
        ]);
        $this->assertSame($usuariosAntes, User::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.persona.crear']);

        $persona = Persona::query()->firstOrFail();
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-12-31',
            'observacion' => 'Contrato de arrendamiento',
        ])->assertRedirect(route('management.unidades.show', $unidad));

        $vinculo = VinculoUnidad::query()->firstOrFail();
        $this->assertDatabaseHas('vinculos_unidad', [
            'id' => $vinculo->id,
            'persona_id' => $persona->id,
            'unidad_privada_id' => $unidad->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'tipo_vinculo' => 'residente',
            'estado' => 'activo',
        ]);
        $this->assertSame('2026-01-01', $vinculo->vigente_desde->format('Y-m-d'));
        $this->assertSame('2026-12-31', $vinculo->vigente_hasta->format('Y-m-d'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.vinculo.crear']);

        $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidad))
            ->assertOk()
            ->assertSee('Vínculos vigentes')
            ->assertSee('Laura Gómez')
            ->assertSee('Contrato de arrendamiento');

        // Separación de dominio: los vínculos residenciales no otorgan acceso.
        $this->assertSame($membresiasAntes, MembresiaCopropiedad::count());
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
    }

    public function test_rechaza_solapamientos_indebidos_y_permite_vinculos_historicos_legitimos(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');

        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'propietario',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-06-30',
        ])->assertRedirect(route('management.unidades.show', $unidad));

        // Solapamiento del mismo tipo → rechazado.
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'propietario',
            'vigente_desde' => '2026-06-01',
            'vigente_hasta' => null,
        ])->assertSessionHasErrors('vigente_desde');
        $this->assertSame(1, VinculoUnidad::count());

        // Mismo periodo pero tipo distinto → permitido (propietario y residente).
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-06-30',
        ])->assertRedirect(route('management.unidades.show', $unidad));
        $this->assertSame(2, VinculoUnidad::count());

        // Periodo histórico sin solapamiento, mismo tipo → permitido.
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'propietario',
            'vigente_desde' => '2026-07-01',
        ])->assertRedirect(route('management.unidades.show', $unidad));
        $this->assertSame(3, VinculoUnidad::count());
    }

    public function test_concluir_el_vinculo_conserva_el_historial(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');
        $vinculo = $this->vincular($gestor, $unidad, $persona, 'propietario', '2026-01-01');

        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.concluir', [$unidad, $vinculo]), ['vigente_hasta' => '2026-09-30'])
            ->assertRedirect(route('management.unidades.show', $unidad));

        $concluido = $vinculo->fresh();
        $this->assertSame('concluido', $concluido->estado);
        $this->assertSame('2026-09-30', $concluido->vigente_hasta->format('Y-m-d'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.vinculo.concluir']);

        // El registro se conserva (sin eliminación) y aparece en el historial.
        $this->assertSame(1, VinculoUnidad::count());
        $respuesta = $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidad))->assertOk();
        $respuesta->assertSee('Historial de vínculos')->assertSee('Concluido')->assertSee('Laura Gómez');

        // Concluir de nuevo no reescribe la historia y la fecha anterior al
        // inicio no se acepta.
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.concluir', [$unidad, $vinculo]), ['vigente_hasta' => '2026-12-31'])
            ->assertSessionHasErrors('vigente_hasta');
        $this->assertSame('2026-09-30', $vinculo->fresh()->vigente_hasta->format('Y-m-d'));

        $otro = $this->vincular($gestor, $unidad, $persona, 'residente', '2026-01-01');
        $this->actingAsContextual($gestor)
            ->patch(route('management.unidades.vinculos.concluir', [$unidad, $otro]), ['vigente_hasta' => '2025-12-31'])
            ->assertSessionHasErrors('vigente_hasta');
    }

    public function test_aislamiento_entre_organizaciones_y_copropiedades(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();

        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $personaAjena = $this->persona($otraOrganizacion->id, 'Persona foránea');
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $personaAjena->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
        ])->assertNotFound();
        $this->assertSame(0, VinculoUnidad::count());

        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $unidadAjena = $this->unidad($organizacion->id, $otraCopropiedad->id, 'B-201');
        $this->actingAsContextual($gestor)->get(route('management.unidades.show', $unidadAjena))->assertNotFound();
        $this->actingAsContextual($gestor)->post(route('management.unidades.vinculos.store', $unidadAjena), [
            'persona_id' => $this->persona($organizacion->id, 'Propia')->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-01-01',
        ])->assertNotFound();
    }

    public function test_sin_residentes_gestionar_no_se_gestiona(): void
    {
        [$organizacion, $copropiedad, $gestor, $unidad] = $this->escenario();
        $persona = $this->persona($organizacion->id, 'Laura Gómez');
        $vinculo = $this->vincular($gestor, $unidad, $persona, 'propietario', '2026-01-01');

        $sinPermiso = User::factory()->create();
        $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedad, 'apoyo', ['pqrs.listar']);

        $this->actingAsContextual($sinPermiso)->get(route('management.unidades.show', $unidad))->assertForbidden();
        $this->actingAsContextual($sinPermiso)->post(route('management.personas.store'), [
            'tipo_persona' => 'natural',
            'nombre_razon_social' => 'Sin permiso',
        ])->assertForbidden();
        $this->actingAsContextual($sinPermiso)->post(route('management.unidades.vinculos.store', $unidad), [
            'persona_id' => $persona->id,
            'tipo_vinculo' => 'residente',
            'vigente_desde' => '2026-02-01',
        ])->assertForbidden();
        $this->actingAsContextual($sinPermiso)
            ->patch(route('management.unidades.vinculos.concluir', [$unidad, $vinculo]), [])
            ->assertForbidden();

        $this->assertSame(1, VinculoUnidad::count());
        $this->assertSame('activo', $vinculo->fresh()->estado);
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
