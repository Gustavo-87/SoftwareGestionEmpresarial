<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrsComunicacionesVisualTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_gestor_autorizado_ve_respuesta_oficial_con_marca_visual(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'estado' => 'respondida']);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Respuesta oficial enviada.', 'is_draft' => false, 'sent_at' => now()]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Respuestas oficiales')
            ->assertSee('Respuesta oficial')
            ->assertSee('Respuesta oficial enviada.')
            ->assertSee('reply-card official', false)
            ->assertSee('official-badge', false);
    }

    public function test_gestor_autorizado_ve_borrador_propio_con_marca_visual(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Borrador propio del gestor.', 'is_draft' => true]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Borradores propios')
            ->assertSee('Borrador interno')
            ->assertSee('Borrador propio del gestor.')
            ->assertSee('reply-card draft', false)
            ->assertSee('draft-badge', false)
            ->assertSee('No enviado')
            ->assertSee('Editar borrador')
            ->assertSee('Enviar borrador')
            ->assertSee('Eliminar borrador');
    }

    public function test_borrador_ajeno_es_invisible_para_gestor(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $otroGestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->createContextualIdentity($otroGestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);
        $pqr->replies()->create(['user_id' => $otroGestor->id, 'body' => 'Borrador de otro gestor.', 'is_draft' => true]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertDontSee('Borrador de otro gestor.');
    }

    public function test_residente_ve_respuesta_oficial_pero_no_borradores_ni_comentarios(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($residente, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'estado' => 'respondida']);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Respuesta oficial visible.', 'is_draft' => false, 'sent_at' => now()]);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Borrador oculto.', 'is_draft' => true]);
        $pqr->internalComments()->create(['user_id' => $gestor->id, 'body' => 'Comentario interno oculto.']);

        $this->actingAsContextual($residente)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Respuesta oficial visible.')
            ->assertSee('Respuesta oficial')
            ->assertDontSee('Borrador oculto.')
            ->assertDontSee('Borradores propios')
            ->assertDontSee('Borrador interno')
            ->assertDontSee('Comentario interno oculto.')
            ->assertDontSee('Comentarios internos')
            ->assertDontSee('Solo equipo autorizado');
    }

    public function test_auditor_ve_respuesta_oficial_pero_no_borradores_ni_comentarios(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $auditor = User::factory()->create(['role' => 'auditor']);
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($auditor, $org, $cop, 'auditor', ['pqrs.ver_todas']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'estado' => 'respondida']);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Respuesta oficial para auditor.', 'is_draft' => false, 'sent_at' => now()]);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Borrador oculto para auditor.', 'is_draft' => true]);
        $pqr->internalComments()->create(['user_id' => $gestor->id, 'body' => 'Comentario oculto para auditor.']);

        $this->actingAsContextual($auditor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Respuesta oficial para auditor.')
            ->assertDontSee('Borrador oculto para auditor.')
            ->assertDontSee('Comentario oculto para auditor.')
            ->assertDontSee('Comentarios internos');
    }

    public function test_gestor_ve_comentarios_internos_con_separacion_visual(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);
        $pqr->internalComments()->create(['user_id' => $gestor->id, 'body' => 'Nota administrativa del equipo.']);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Comentarios internos')
            ->assertSee('Solo equipo autorizado')
            ->assertSee('Notas administrativas del equipo')
            ->assertSee('Nota administrativa del equipo.')
            ->assertSee('internal-comments', false)
            ->assertSee('Agregar comentario interno');
    }

    public function test_respuesta_oficial_no_muestra_controles_de_edicion(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id, 'estado' => 'respondida']);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Respuesta inmutable.', 'is_draft' => false, 'sent_at' => now()]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Respuesta inmutable.')
            ->assertDontSee('Editar borrador')
            ->assertDontSee('Enviar borrador')
            ->assertDontSee('Eliminar borrador');
    }

    public function test_estado_vacio_cuando_no_hay_respuestas(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Aún no se han enviado respuestas oficiales al radicador.')
            ->assertSee('0 enviadas');
    }

    public function test_estado_vacio_cuando_no_hay_comentarios_internos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('No hay comentarios internos.');
    }

    public function test_formulario_respuesta_tiene_accesibilidad_completa(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $response = $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('aria-describedby', $html);
        $this->assertStringContainsString('data-submit-label', $html);
    }

    public function test_secciones_comunicacion_tienen_aria_labels(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);
        $pqr->replies()->create(['user_id' => $gestor->id, 'body' => 'Borrador con accesibilidad.', 'is_draft' => true]);
        $pqr->internalComments()->create(['user_id' => $gestor->id, 'body' => 'Comentario con accesibilidad.']);

        $html = $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))->getContent();
        $this->assertStringContainsString('aria-labelledby="respuestas-oficiales-title"', $html);
        $this->assertStringContainsString('aria-labelledby="borradores-title"', $html);
        $this->assertStringContainsString('aria-labelledby="comentarios-title"', $html);
        $this->assertStringContainsString('aria-label="Borrador propio, no enviado"', $html);
        $this->assertStringContainsString('aria-label="Comentarios internos del equipo"', $html);
    }

    public function test_editor_respuesta_muestra_limites_de_adjuntos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Máximo 5 archivos')
            ->assertSee('10 MB cada uno')
            ->assertSee('JPG, PNG, WebP, PDF');
    }

    public function test_editor_respuesta_muestra_resumen_de_errores_con_foco(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $response = $this->actingAsContextual($gestor)->post(route('pqrs.replies.store', $pqr), [
            'action' => 'send',
            'send_reply_key' => (string) \Illuminate\Support\Str::uuid(),
            'body' => str_repeat('x', 10001),
        ]);

        $response->assertRedirect();
        $html = $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))->getContent();
        $this->assertStringContainsString('reply-error-summary', $html);
        $this->assertStringContainsString('data-first-error', $html);
        $this->assertStringContainsString('No fue posible procesar la respuesta', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }

    public function test_editor_respuesta_muestra_errores_de_adjuntos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $residente->id]);

        $files = array_map(
            fn () => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100),
            range(1, 6)
        );

        $response = $this->actingAsContextual($gestor)->post(route('pqrs.replies.store', $pqr), [
            'action' => 'draft',
            'create_draft_key' => (string) \Illuminate\Support\Str::uuid(),
            'body' => 'Texto válido.',
            'attachments' => $files,
        ]);

        $response->assertRedirect();
        $html = $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))->getContent();
        $this->assertStringContainsString('reply-error-summary', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }
}
