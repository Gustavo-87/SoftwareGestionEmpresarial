<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Proceso integrado PQRS → mantenimiento (HU-PQRS-03 y HU-MAN-03/04,
 * Sprint 19), incluida la idempotencia aprobada por el Product Owner.
 */
class IntegracionModulosTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_generar_mantenimiento_desde_una_pqrs_vincula_audita_y_registra_actividad(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();

        $this->actingAsContextual($gestor)
            ->post(route('pqrs.mantenimientos.store', $pqr), [
                'titulo' => 'Reparación de fugas',
                'descripcion' => 'Revisar tubería del sótano',
                'fecha_programada' => now()->addDay()->format('Y-m-d'),
                'idempotency_key' => 'clave-generacion-1',
            ])
            ->assertRedirect();

        $mantenimiento = Mantenimiento::query()->firstOrFail();
        $this->assertSame($pqr->id, (int) $mantenimiento->pqr_id);
        $this->assertSame('pendiente', $mantenimiento->estado);
        $this->assertSame($pqr->user_id, (int) $mantenimiento->solicitante_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mantenimiento.desde_pqr', 'auditable_id' => $mantenimiento->id]);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'mantenimiento_generado']);
    }

    public function test_la_operacion_es_idempotente_ante_doble_clic_reenvio_y_concurrencia(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $payload = [
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería del sótano',
            'idempotency_key' => 'clave-idempotente',
        ];

        // Doble clic / reenvío con la misma clave: una sola creación.
        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), $payload);
        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), $payload);
        $this->assertSame(1, Mantenimiento::query()->count());

        // Acción explícita nueva (clave nueva): trabajo legítimo adicional.
        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), [
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Segunda intervención programada',
            'idempotency_key' => 'clave-idempotente-2',
            'confirmar_duplicado' => 1,
        ]);
        $this->assertSame(2, Mantenimiento::query()->count());
    }

    public function test_reutilizar_la_clave_con_otra_pqrs_no_devuelve_ni_modifica_un_recurso_incorrecto(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $pqrDos = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $gestor->id]);

        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), [
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería del sótano',
            'fecha_programada' => now()->addDay()->format('Y-m-d'),
            'idempotency_key' => 'clave-compartida',
        ]);
        $original = Mantenimiento::query()->firstOrFail();

        // La misma clave contra otra PQRS no debe devolver el recurso original
        // ni crear uno incorrecto.
        $this->actingAsContextual($gestor)
            ->post(route('pqrs.mantenimientos.store', $pqrDos), [
                'titulo' => 'Reparación de fugas',
                'descripcion' => 'Revisar tubería del sótano',
                'fecha_programada' => now()->addDay()->format('Y-m-d'),
                'idempotency_key' => 'clave-compartida',
            ])
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Mantenimiento::query()->count());
        $this->assertSame($pqr->id, (int) $original->fresh()->pqr_id);
        $this->assertSame(0, Mantenimiento::query()->where('pqr_id', $pqrDos->id)->count());
    }

    public function test_reutilizar_la_clave_con_datos_incompatibles_rechaza_sin_alterar_el_registro(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), [
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería del sótano',
            'fecha_programada' => '2026-11-01',
            'idempotency_key' => 'clave-datos',
        ]);
        $original = Mantenimiento::query()->firstOrFail();

        foreach ([['titulo' => 'Otro título'], ['descripcion' => 'Otra descripción'], ['fecha_programada' => '2026-12-15']] as $cambio) {
            $this->actingAsContextual($gestor)
                ->post(route('pqrs.mantenimientos.store', $pqr), array_merge([
                    'titulo' => 'Reparación de fugas',
                    'descripcion' => 'Revisar tubería del sótano',
                    'fecha_programada' => '2026-11-01',
                    'idempotency_key' => 'clave-datos',
                ], $cambio))
                ->assertSessionHasErrors('idempotency_key');
        }

        $this->assertSame(1, Mantenimiento::query()->count());
        $this->assertSame('Reparación de fugas', $original->fresh()->titulo);
        $this->assertSame('2026-11-01', $original->fresh()->fecha_programada->format('Y-m-d'));
    }

    public function test_duplicado_accidental_exige_confirmacion_explicita(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $payload = fn (string $clave, bool $confirmar = false) => [
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería',
            'idempotency_key' => $clave,
            'confirmar_duplicado' => $confirmar ? 1 : 0,
        ];

        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), $payload('clave-a'));
        $this->actingAsContextual($gestor)
            ->post(route('pqrs.mantenimientos.store', $pqr), $payload('clave-b'))
            ->assertSessionHasErrors('titulo');
        $this->assertSame(1, Mantenimiento::query()->count());

        $this->actingAsContextual($gestor)->post(route('pqrs.mantenimientos.store', $pqr), $payload('clave-c', true));
        $this->assertSame(2, Mantenimiento::query()->count());
    }

    public function test_generar_exige_autorizacion_efectiva_sobre_ambos_recursos(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();

        // pqrs.listar por sí solo no concede acceso suficiente (ajuste aprobado).
        $soloListar = User::factory()->create();
        $this->createContextualIdentity($soloListar, $organizacion, $copropiedad, 'auditor', ['pqrs.listar']);
        $this->actingAsContextual($soloListar)
            ->post(route('pqrs.mantenimientos.store', $pqr), ['titulo' => 'X', 'descripcion' => 'Y', 'idempotency_key' => 'clave-403-1'])
            ->assertForbidden();

        // Sin permiso de gestión de la PQRS tampoco procede.
        $sinGestionPqrs = User::factory()->create();
        $this->createContextualIdentity($sinGestionPqrs, $organizacion, $copropiedad, 'apoyo', ['mantenimiento.crear']);
        $this->actingAsContextual($sinGestionPqrs)
            ->post(route('pqrs.mantenimientos.store', $pqr), ['titulo' => 'X', 'descripcion' => 'Y', 'idempotency_key' => 'clave-403-2'])
            ->assertForbidden();

        // Cruce de contexto: la PQRS de otra Copropiedad es invisible (el
        // binding de ruta es contextual y oculta el recurso con 404).
        $otra = \App\Models\Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $pqrAjena = Pqr::factory()->paraContexto($organizacion, $otra)->create();
        $this->actingAsContextual($gestor)
            ->post(route('pqrs.mantenimientos.store', $pqrAjena), ['titulo' => 'X', 'descripcion' => 'Y', 'idempotency_key' => 'clave-403-3'])
            ->assertNotFound();
    }

    public function test_los_vinculos_se_consultan_desde_ambos_modulos_y_se_administran_antes_de_finalizar(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $mantenimiento = $this->crearMantenimiento($gestor, $organizacion, $copropiedad, null);

        // Vinculación posterior (mantenimiento independiente).
        $this->actingAsContextual($gestor)
            ->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => $pqr->id])
            ->assertRedirect();
        $this->assertSame($pqr->id, (int) $mantenimiento->fresh()->pqr_id);

        // Idempotente: repetir el vínculo no cambia nada.
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => $pqr->id]);
        $this->assertSame($pqr->id, (int) $mantenimiento->fresh()->pqr_id);

        // Consulta bidireccional.
        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('Reparación de fugas');
        $this->actingAsContextual($gestor)->get(route('mantenimiento.show', $mantenimiento))->assertOk()->assertSee('PQRS de origen');

        // Retiro del vínculo antes de finalizar.
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => '']);
        $this->assertNull($mantenimiento->fresh()->pqr_id);
    }

    public function test_el_vinculo_no_se_administra_tras_finalizar_y_requiere_gestion_en_ambos_recursos(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $mantenimiento = $this->crearMantenimiento($gestor, $organizacion, $copropiedad, null);

        // pqrs.listar no es suficiente para vincular (ajuste aprobado).
        $soloListar = User::factory()->create();
        $this->createContextualIdentity($soloListar, $organizacion, $copropiedad, 'auditor', ['pqrs.listar', 'mantenimiento.gestionar']);
        $this->actingAsContextual($soloListar)
            ->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => $pqr->id])
            ->assertForbidden();

        $this->actingAsContextual($gestor)->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => $pqr->id]);
        $this->prepararFinalizacion($gestor, $organizacion, $copropiedad, $mantenimiento);
        $this->actingAsContextual($gestor)
            ->patch(route('mantenimiento.resultado.update', $mantenimiento), ['resultado_publico' => 'Fuga reparada']);
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.finalizar', $mantenimiento))->assertRedirect();

        $this->actingAsContextual($gestor)
            ->patch(route('mantenimiento.pqr.update', $mantenimiento), ['pqr_id' => ''])
            ->assertSessionHasErrors('pqr_id');
        $this->assertSame($pqr->id, (int) $mantenimiento->fresh()->pqr_id);
    }

    public function test_el_resultado_separa_lo_publico_de_lo_interno_y_se_registra_antes_de_finalizar(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $mantenimiento = $this->crearMantenimiento($gestor, $organizacion, $copropiedad, $pqr->id);
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.update', $mantenimiento), ['estado' => 'en_proceso']);

        $this->actingAsContextual($gestor)
            ->patch(route('mantenimiento.resultado.update', $mantenimiento), ['resultado_publico' => '', 'resultado_interno' => ''])
            ->assertSessionHasErrors('resultado_publico');

        $this->actingAsContextual($gestor)->patch(route('mantenimiento.resultado.update', $mantenimiento), [
            'resultado_publico' => 'Fuga reparada',
            'resultado_interno' => 'Se sustituyó la válvula del sótano',
        ]);

        $actualizado = $mantenimiento->fresh();
        $this->assertSame('Fuga reparada', $actualizado->resultado_publico);
        $this->assertSame('Se sustituyó la válvula del sótano', $actualizado->resultado_interno);
        $this->assertNotNull($actualizado->resultado_registrado_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mantenimiento.resultado', 'auditable_id' => $mantenimiento->id]);
    }

    public function test_finalizar_exige_evidencia_valida_y_no_cierra_la_pqrs(): void
    {
        [$organizacion, $copropiedad, $gestor, $pqr] = $this->flujoBase();
        $mantenimiento = $this->crearMantenimiento($gestor, $organizacion, $copropiedad, $pqr->id);
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.update', $mantenimiento), ['estado' => 'en_proceso']);
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.resultado.update', $mantenimiento), ['resultado_publico' => 'Fuga reparada']);

        // Sin evidencia válida no se finaliza.
        $this->actingAsContextual($gestor)
            ->patch(route('mantenimiento.finalizar', $mantenimiento))
            ->assertSessionHasErrors('evidencia');
        $this->assertSame('en_proceso', $mantenimiento->fresh()->estado);

        $this->prepararFinalizacion($gestor, $organizacion, $copropiedad, $mantenimiento);
        $estadoPqrsAntes = $pqr->fresh()->estado;
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.finalizar', $mantenimiento))->assertRedirect();

        $finalizado = $mantenimiento->fresh();
        $this->assertSame('finalizado', $finalizado->estado);
        $this->assertNotNull($finalizado->finalizado_at);
        // Regla 8: la PQRS no se cierra automáticamente.
        $this->assertSame($estadoPqrsAntes, $pqr->fresh()->estado);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mantenimiento.finalizar', 'auditable_id' => $mantenimiento->id]);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'mantenimiento_finalizado']);

        // Repetir la finalización es inofensivo (idempotente).
        $this->actingAsContextual($gestor)->patch(route('mantenimiento.finalizar', $mantenimiento))->assertRedirect();
        $this->assertSame('finalizado', $mantenimiento->fresh()->estado);
    }

    /** @return array{0:\App\Models\Organizacion,1:\App\Models\Copropiedad,2:User,3:Pqr} */
    private function flujoBase(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.gestionar',
            'mantenimiento.crear', 'mantenimiento.ver_todas', 'mantenimiento.gestionar',
        ]);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $gestor->id]);

        return [$organizacion, $copropiedad, $gestor, $pqr];
    }

    private function crearMantenimiento(User $gestor, $organizacion, $copropiedad, ?int $pqrId): Mantenimiento
    {
        return Mantenimiento::create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'pqr_id' => $pqrId,
            'solicitante_id' => $gestor->id,
            'titulo' => 'Reparación de fugas',
            'descripcion' => 'Revisar tubería del sótano',
            'fecha_programada' => now()->addDay(),
            'estado' => 'pendiente',
        ]);
    }

    private function prepararFinalizacion(User $gestor, $organizacion, $copropiedad, Mantenimiento $mantenimiento): void
    {
        $documento = \App\Models\Documento::factory()->paraContexto($organizacion, $copropiedad)->create();
        $version = \App\Models\DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => \App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum::APROBADA,
        ]);
        \App\Models\MantenimientoDocumentoEvidencia::create([
            'mantenimiento_id' => $mantenimiento->id,
            'documento_id' => $documento->id,
            'documento_version_id' => $version->id,
            'vinculado_por_user_id' => $gestor->id,
        ]);
        if ($mantenimiento->estado !== 'en_proceso') {
            $mantenimiento->update(['estado' => 'en_proceso']);
        }
    }
}
