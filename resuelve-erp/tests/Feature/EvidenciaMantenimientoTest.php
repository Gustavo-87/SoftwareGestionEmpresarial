<?php

namespace Tests\Feature;

use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Evidencia documental de mantenimientos (HU-DOC-03, Sprint 19): versión
 * exacta, validación de estados, autorización sobre ambos recursos y
 * ausencia de ampliación de visibilidad.
 */
class EvidenciaMantenimientoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_la_evidencia_fija_la_version_exacta_y_no_altera_el_ciclo_documental(): void
    {
        [$organizacion, $copropiedad, $gestor, $mantenimiento] = $this->escenario();
        $version = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::APROBADA);

        $this->actingAsContextual($gestor)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $version->id])
            ->assertRedirect();

        $evidencia = \App\Models\MantenimientoDocumentoEvidencia::query()->firstOrFail();
        $this->assertSame($version->id, (int) $evidencia->documento_version_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mantenimiento.evidencia.vincular', 'auditable_id' => $evidencia->id]);

        // El ciclo documental no cambia: la versión conserva su estado.
        $this->assertSame('aprobada', $version->fresh()->estado->value);
    }

    public function test_solo_versiones_aprobadas_de_documentos_activos_sirven_como_evidencia(): void
    {
        [$organizacion, $copropiedad, $gestor, $mantenimiento] = $this->escenario();

        $borrador = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::BORRADOR);
        $this->actingAsContextual($gestor)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $borrador->id])
            ->assertSessionHasErrors('documento_version_id');

        $archivado = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::APROBADA);
        $archivado->documento->update(['estado' => 'archivado']);
        $this->actingAsContextual($gestor)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $archivado->id])
            ->assertSessionHasErrors('documento_id');

        $this->assertSame(0, \App\Models\MantenimientoDocumentoEvidencia::query()->count());
    }

    public function test_vincular_exige_autorizacion_sobre_ambos_recursos_y_pertenencia_al_contexto(): void
    {
        [$organizacion, $copropiedad, $gestor, $mantenimiento] = $this->escenario();

        // Sin acceso administrativo al documento no se vincula: gestiona el
        // mantenimiento, pero el documento escapa a su autorización efectiva.
        $gestorSinAdministrativo = User::factory()->create();
        $this->createContextualIdentity($gestorSinAdministrativo, $organizacion, $copropiedad, 'gestor', [
            'documentos.consultar', 'mantenimiento.ver_todas', 'mantenimiento.gestionar',
        ]);
        $restringido = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::APROBADA, NivelAccesoDocumentoEnum::ADMINISTRATIVO);
        $this->actingAsContextual($gestorSinAdministrativo)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $restringido->id])
            ->assertForbidden();

        // Documento de otro contexto: prohibido.
        $otra = \App\Models\Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $ajeno = $this->version($organizacion, $otra, EstadoDocumentoVersionEnum::APROBADA);
        $this->actingAsContextual($gestor)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $ajeno->id])
            ->assertForbidden();

        // Sin gestión del mantenimiento tampoco procede.
        $soloConsulta = User::factory()->create();
        $this->createContextualIdentity($soloConsulta, $organizacion, $copropiedad, 'apoyo', ['documentos.consultar', 'mantenimiento.ver_todas']);
        $propio = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::APROBADA);
        $this->actingAsContextual($soloConsulta)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), ['documento_version_id' => $propio->id])
            ->assertForbidden();
    }

    public function test_vincular_es_idempotente_y_el_retiro_solo_procede_antes_de_finalizar(): void
    {
        [$organizacion, $copropiedad, $gestor, $mantenimiento] = $this->escenario();
        $version = $this->version($organizacion, $copropiedad, EstadoDocumentoVersionEnum::APROBADA);

        $payload = ['documento_version_id' => $version->id];
        $this->actingAsContextual($gestor)->post(route('mantenimiento.evidencia.store', $mantenimiento), $payload);
        $this->actingAsContextual($gestor)->post(route('mantenimiento.evidencia.store', $mantenimiento), $payload);
        $this->assertSame(1, \App\Models\MantenimientoDocumentoEvidencia::query()->count());

        $evidencia = \App\Models\MantenimientoDocumentoEvidencia::query()->firstOrFail();
        $this->actingAsContextual($gestor)
            ->delete(route('mantenimiento.evidencia.destroy', [$mantenimiento, $evidencia]))
            ->assertRedirect();
        $this->assertSame(0, \App\Models\MantenimientoDocumentoEvidencia::query()->count());

        // Tras finalizar, el conjunto de evidencias es inmutable.
        $mantenimiento->update(['estado' => 'finalizado']);
        $this->actingAsContextual($gestor)
            ->post(route('mantenimiento.evidencia.store', $mantenimiento), $payload)
            ->assertSessionHasErrors('evidencia');
    }

    /** @return array{0:\App\Models\Organizacion,1:\App\Models\Copropiedad,2:User,3:Mantenimiento} */
    private function escenario(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'documentos.consultar', 'documentos.gestionar',
            'mantenimiento.ver_todas', 'mantenimiento.gestionar',
        ]);
        $mantenimiento = Mantenimiento::create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'solicitante_id' => $gestor->id,
            'titulo' => 'Revisión eléctrica',
            'descripcion' => 'Revisión general',
            'estado' => 'pendiente',
        ]);

        return [$organizacion, $copropiedad, $gestor, $mantenimiento];
    }

    private function version($organizacion, $copropiedad, EstadoDocumentoVersionEnum $estado, NivelAccesoDocumentoEnum $nivel = NivelAccesoDocumentoEnum::COMUNIDAD): DocumentoVersion
    {
        $documento = Documento::factory()->paraContexto($organizacion, $copropiedad)->create(['nivel_acceso' => $nivel]);

        return DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => $estado,
        ]);
    }
}
