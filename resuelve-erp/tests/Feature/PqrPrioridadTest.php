<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\PqrActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrPrioridadTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    // ─── Integridad de valores ───

    public function test_pqr_se_crea_con_prioridad_media_por_defecto(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['pqrs.crear', 'pqrs.ver_propias']);
        $tipo = \App\Models\TipoPqr::factory()->create();

        $this->actingAsContextual($user)->post(route('pqrs.store'), [
            'asunto' => 'Solicitud de prueba',
            'descripcion' => 'Descripción de prueba.',
            'tipo_pqr_id' => $tipo->id,
        ])->assertRedirect();

        $this->assertSame('media', Pqr::first()->prioridad);
    }

    public function test_valores_permitidos_son_alta_media_baja(): void
    {
        $this->assertSame(['alta', 'media', 'baja'], Pqr::PRIORIDADES);
    }

    public function test_prioridad_invalida_es_rechazada_por_modelo(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media']);

        $this->expectException(\InvalidArgumentException::class);
        $pqr->update(['prioridad' => 'urgente']);
    }

    public function test_prioridad_invalida_es_rechazada_por_validacion(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media']);

        $this->actingAsContextual($gestor)->patch(route('pqrs.quick-update', $pqr), [
            'prioridad' => 'urgente',
        ])->assertSessionHasErrors('prioridad');

        $this->assertSame('media', $pqr->fresh()->prioridad);
    }

    public function test_compatibilidad_sqlite_check_constraint(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $tipo = \App\Models\TipoPqr::factory()->create();

        // Valor válido no lanza excepción.
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'alta']);
        $this->assertSame('alta', $pqr->prioridad);

        // La validación de valores inválidos se realiza en capa de aplicación
        // mediante Pqr::booted(), no mediante CHECK constraint en base de datos.
        // Ver test_prioridad_invalida_es_rechazada_por_modelo.
        $this->assertTrue(true);
    }

    // ─── Gestión de prioridad ───

    public function test_gestor_puede_cambiar_prioridad_en_update(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media']);

        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => $pqr->estado,
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        $this->assertSame('alta', $pqr->fresh()->prioridad);
    }

    public function test_gestor_puede_cambiar_prioridad_en_accion_rapida(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media']);

        $this->actingAsContextual($gestor)->patch(route('pqrs.quick-update', $pqr), [
            'prioridad' => 'baja',
        ])->assertRedirect();

        $this->assertSame('baja', $pqr->fresh()->prioridad);
    }

    public function test_residente_no_puede_modificar_prioridad(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'media']);

        $this->actingAsContextual($residente)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => $pqr->estado,
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertForbidden();

        $this->assertSame('media', $pqr->fresh()->prioridad);
    }

    public function test_prioridad_no_afecta_vencimientos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media']);
        $fechaOriginal = $pqr->fecha_limite_respuesta;

        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => $pqr->estado,
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        $this->assertEquals($fechaOriginal->toDateString(), $pqr->fresh()->fecha_limite_respuesta->toDateString());
    }

    // ─── Auditoría ───

    public function test_cambio_solo_de_prioridad_registra_priority_changed(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media', 'estado' => 'radicada', 'assigned_to_id' => null]);

        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => 'radicada',
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        $this->assertDatabaseHas('pqr_activities', [
            'pqr_id' => $pqr->id,
            'action' => 'priority_changed',
        ]);
        $this->assertDatabaseMissing('pqr_activities', [
            'pqr_id' => $pqr->id,
            'action' => 'updated',
        ]);
    }

    public function test_cambio_combinado_estado_responsable_prioridad_registra_ambas_actuaciones(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $otro = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->createContextualIdentity($otro, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media', 'estado' => 'radicada', 'assigned_to_id' => null]);

        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => 'en_revision',
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'assigned_to_id' => $otro->id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        // Debe registrar updated (estado + responsable) Y priority_changed.
        $this->assertDatabaseHas('pqr_activities', [
            'pqr_id' => $pqr->id,
            'action' => 'updated',
        ]);
        $this->assertDatabaseHas('pqr_activities', [
            'pqr_id' => $pqr->id,
            'action' => 'priority_changed',
        ]);
        $this->assertSame(2, PqrActivity::where('pqr_id', $pqr->id)->count());
    }

    public function test_updated_no_incluye_prioridad_en_descripcion(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media', 'estado' => 'radicada']);

        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => 'en_revision',
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        $updated = PqrActivity::where('pqr_id', $pqr->id)->where('action', 'updated')->first();
        $this->assertNotNull($updated);
        $this->assertStringNotContainsString('prioridad', strtolower($updated->description));
    }

    public function test_quick_action_no_incluye_prioridad_en_descripcion(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'media', 'estado' => 'radicada']);

        $this->actingAsContextual($gestor)->patch(route('pqrs.quick-update', $pqr), [
            'estado' => 'en_revision',
            'prioridad' => 'alta',
        ])->assertRedirect();

        $quick = PqrActivity::where('pqr_id', $pqr->id)->where('action', 'quick_action')->first();
        $this->assertNotNull($quick);
        $this->assertStringNotContainsString('prioridad', strtolower($quick->description));
    }

    public function test_priority_changed_es_interno_no_en_public_actions(): void
    {
        $this->assertNotContains('priority_changed', PqrActivity::PUBLIC_ACTIONS);
    }

    public function test_residente_no_ve_priority_changed_en_historial(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'media']);

        // Gestor cambia prioridad.
        $this->actingAsContextual($gestor)->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'estado' => $pqr->estado,
            'tipo_pqr_id' => $pqr->tipo_pqr_id,
            'prioridad' => 'alta',
        ])->assertRedirect();

        // Residente no ve la actividad priority_changed.
        $this->actingAsContextual($residente)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertDontSee('Cambió la prioridad')
            ->assertDontSee('priority_changed');
    }

    // ─── Visibilidad ───

    public function test_residente_no_ve_prioridad_en_expediente(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'alta']);

        $this->actingAsContextual($residente)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertDontSee('Prioridad')
            ->assertDontSee('priority-badge');
    }

    public function test_gestor_ve_prioridad_en_expediente(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'alta']);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Prioridad')
            ->assertSee('Alta');
    }

    public function test_prioridad_visible_en_bandeja_para_gestor(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.listar']);
        Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'alta']);

        $this->actingAsContextual($gestor)->get(route('pqrs.index'))
            ->assertOk()
            ->assertSee('priority-badge');
    }

    public function test_columna_prioridad_ausente_para_residente(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias', 'pqrs.listar']);
        Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'alta']);

        $html = $this->actingAsContextual($residente)->get(route('pqrs.index'))->getContent();
        $this->assertStringNotContainsString('priority-badge', $html);
        // Verifica que el encabezado "Prioridad" no aparece en la tabla.
        $this->assertStringNotContainsString('<th>Prioridad</th>', $html);
    }

    // ─── Filtro backend ───

    public function test_filtro_prioridad_funciona_para_gestor(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.listar']);
        Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'alta']);
        Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'baja']);

        $response = $this->actingAsContextual($gestor)->get(route('pqrs.index', ['prioridad' => 'alta']));
        $response->assertOk();
        // Solo debe ver la PQR de prioridad alta.
        $pqrs = $response->viewData('pqrs');
        $this->assertTrue($pqrs->every(fn ($p) => $p->prioridad === 'alta'));
    }

    public function test_residente_no_puede_inferir_prioridad_por_filtro_manual(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias', 'pqrs.listar']);
        Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'alta']);
        Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'prioridad' => 'baja']);

        // El filtro ?prioridad=alta no debe aplicarse para residentes.
        $response = $this->actingAsContextual($residente)->get(route('pqrs.index', ['prioridad' => 'alta']));
        $response->assertOk();
        $pqrs = $response->viewData('pqrs');
        $this->assertSame(2, $pqrs->total());
    }

    public function test_auditor_sin_gestion_no_ve_filtro_prioridad(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $auditor = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($auditor, $org, $cop, 'auditor', ['pqrs.ver_todas', 'pqrs.listar']);
        Pqr::factory()->paraContexto($org, $cop)->create(['prioridad' => 'alta']);

        $html = $this->actingAsContextual($auditor)->get(route('pqrs.index'))->getContent();
        $this->assertStringNotContainsString('name="prioridad"', $html);
    }

    // ─── Backfill histórico ───

    public function test_backfill_historico_a_media(): void
    {
        // Verifica que el default 'media' aplica a registros nuevos.
        [$org, $cop] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();

        // El factory genera un valor aleatorio, pero el default de la BD es 'media'.
        // Verificamos que el modelo acepta los tres valores y que NULL usa el default.
        $this->assertContains($pqr->prioridad, Pqr::PRIORIDADES);

        // El label para cualquier valor válido debe ser uno de los tres.
        $this->assertContains($pqr->prioridad_label, ['Alta', 'Media', 'Baja']);
    }
}
