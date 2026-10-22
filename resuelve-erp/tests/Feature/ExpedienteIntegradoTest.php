<?php

namespace Tests\Feature;

use App\Application\Pqrs\ConsultaExpedienteIntegrado;
use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoDocumentoEvidencia;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Expediente integrado, PDF y visibilidad del residente (HU-REP-03 y
 * HU-REP-05, Sprint 19), junto con los indicadores del panel (HU-REP-04).
 */
class ExpedienteIntegradoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_el_residente_solo_consulta_estado_fechas_y_resultado_publico(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $mantenimiento] = $this->escenario();
        $mantenimiento->update([
            'resultado_publico' => 'Fuga reparada',
            'resultado_interno' => 'Se sustituyó la válvula del sótano',
            'estado' => 'finalizado',
            'finalizado_at' => now(),
        ]);

        $expediente = app(ConsultaExpedienteIntegrado::class)->para($residente, $this->contexto($residente), $pqr);
        $datos = $expediente['mantenimientos'][0]['datos'];

        $this->assertSame('Fuga reparada', $datos['resultado_publico']);
        $this->assertArrayNotHasKey('resultado_interno', $datos);
        $this->assertArrayNotHasKey('titulo', $datos);
        $this->assertArrayNotHasKey('responsable', $datos);
        $this->assertFalse($expediente['mantenimientos'][0]['puede_gestionar']);

        $this->actingAsContextual($residente)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Fuga reparada')
            ->assertDontSee('Se sustituyó la válvula del sótano');
    }

    public function test_los_documentos_restringidos_no_filtran_metadatos(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $mantenimiento] = $this->escenario();

        $documento = Documento::factory()->paraContexto($organizacion, $copropiedad)->create([
            'titulo' => 'Informe interno de mantenimiento',
            'nivel_acceso' => NivelAccesoDocumentoEnum::ADMINISTRATIVO,
        ]);
        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => EstadoDocumentoVersionEnum::APROBADA,
            'nombre_original' => 'interno.pdf',
        ]);
        MantenimientoDocumentoEvidencia::create([
            'mantenimiento_id' => $mantenimiento->id,
            'documento_id' => $documento->id,
            'documento_version_id' => $version->id,
        ]);

        $expediente = app(ConsultaExpedienteIntegrado::class)->para($residente, $this->contexto($residente), $pqr);
        $this->assertTrue($expediente['mantenimientos'][0]['evidencias_restringidas']);
        $this->assertSame([], $expediente['mantenimientos'][0]['evidencias']);

        $respuesta = $this->actingAsContextual($residente)->get(route('pqrs.show', $pqr))->assertOk();
        $this->assertStringNotContainsString('Informe interno de mantenimiento', $respuesta->content());
        $this->assertStringNotContainsString('interno.pdf', $respuesta->content());
        $this->assertStringContainsString('Evidencia documental disponible solo para personal autorizado', $respuesta->content());

        // El personal con acceso sí ve los metadatos autorizados.
        $expedienteGestor = app(ConsultaExpedienteIntegrado::class)->para($gestor, $this->contexto($gestor), $pqr);
        $this->assertFalse($expedienteGestor['mantenimientos'][0]['evidencias_restringidas']);
        $this->assertSame('Informe interno de mantenimiento', $expedienteGestor['mantenimientos'][0]['evidencias'][0]['documento']);
    }

    public function test_el_pdf_del_expediente_exige_informes_exportar_y_audita(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $mantenimiento] = $this->escenario();

        // El residente sin informes.exportar no exporta.
        $this->actingAsContextual($residente)
            ->get(route('pqrs.expediente.pdf', $pqr))
            ->assertForbidden();

        $this->actingAsContextual($gestor)
            ->get(route('pqrs.expediente.pdf', $pqr))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'informe.expediente.pdf', 'auditable_id' => $pqr->id]);
    }

    public function test_los_indicadores_de_integracion_se_muestran_en_el_panel_compacto(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $mantenimiento] = $this->escenario();
        $mantenimiento->update(['pqr_id' => $pqr->id]);

        $respuesta = $this->actingAsContextual($gestor)->get(route('panel'))
            ->assertOk()
            ->assertSee('con mantenimiento vinculado')
            ->assertSee('vinculados');

        // El panel conserva sus bloques compactos (sin nuevas secciones extensas).
        $this->assertStringContainsString('modulo-integracion', $respuesta->content());
    }

    /** @return array{0:\App\Models\Organizacion,1:\App\Models\Copropiedad,2:User,3:User,4:Pqr,5:Mantenimiento} */
    private function escenario(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();

        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.gestionar', 'informes.exportar',
            'mantenimiento.ver_todas', 'mantenimiento.gestionar',
            'documentos.consultar', 'documentos.gestionar',
        ]);

        $residente = User::factory()->create();
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear', 'mantenimiento.ver_propias',
        ]);

        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $residente->id]);
        $mantenimiento = Mantenimiento::create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'pqr_id' => $pqr->id,
            'solicitante_id' => $residente->id,
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería del sótano',
            'estado' => 'en_proceso',
        ]);

        return [$organizacion, $copropiedad, $gestor, $residente, $pqr, $mantenimiento];
    }

    private function contexto(User $user)
    {
        $contexto = app(\App\Application\Contexto\ContextResolver::class)->resolverExplicito(
            \App\Models\Organizacion::query()->value('id'),
            \App\Models\Copropiedad::query()->value('id'),
            $user->id,
        );
        // Las policies resuelven el contexto desde el contenedor: se fija el
        // mismo contexto que manejaría una petición HTTP del usuario.
        app()->instance(\App\Application\Contexto\ContextoOperativo::class, $contexto);

        return $contexto;
    }
}
