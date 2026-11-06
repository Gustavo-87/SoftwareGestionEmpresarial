<?php

namespace Tests\Feature;

use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrActivity;
use App\Models\PqrReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-PQRS-04 — Anulación trazable de respuestas oficiales (Sprint 25).
 */
class AnulacionRespuestaTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_anulacion_valida_conserva_contenido_y_registra_trazabilidad(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $respuesta] = $this->escenario();
        Queue::fake();

        $antes = ['body' => $respuesta->body, 'attachments' => $respuesta->attachments, 'sent_at' => $respuesta->sent_at?->toDateTimeString()];

        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'motivo_anulacion' => 'Datos erróneos en la respuesta',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect(route('pqrs.show', $pqr))->assertSessionHas('success');

        $respuesta->refresh();
        // Estado de anulación con trazabilidad completa.
        $this->assertTrue($respuesta->estaAnulada());
        $this->assertSame($gestor->id, $respuesta->anulada_por_user_id);
        $this->assertSame('Datos erróneos en la respuesta', $respuesta->motivo_anulacion);
        // Contenido, adjuntos, envío, PQR y autor originales intactos.
        $this->assertSame($antes['body'], $respuesta->body);
        $this->assertSame($antes['attachments'], $respuesta->attachments);
        $this->assertSame($antes['sent_at'], $respuesta->sent_at?->toDateTimeString());
        $this->assertSame($pqr->id, $respuesta->pqr_id);
        $this->assertSame($gestor->id, $respuesta->user_id);
        // La PQR conserva su estado.
        $this->assertSame('respondida', $pqr->fresh()->estado);

        // Ledger de operaciones (idempotencia del ciclo de respuestas).
        $this->assertDatabaseHas('pqr_communication_operations', [
            'pqr_reply_id' => $respuesta->id, 'operation' => 'annul_reply', 'result_code' => 'reply_annulled',
        ]);
        // Actuación pública en el expediente.
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'annulled_reply']);
        $this->assertContains('annulled_reply', PqrActivity::PUBLIC_ACTIONS);
        // Auditoría explícita con el motivo.
        $auditoria = AuditLog::query()->where('action', 'pqr.respuesta.anular')->sole();
        $this->assertSame($gestor->id, $auditoria->user_id);
        $this->assertSame('Datos erróneos en la respuesta', $auditoria->metadata['motivo_anulacion']);
        // Comunicación al residente (campana + correo) reutilizando la emisión existente.
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(EnviarCorreoNotificacionPqrs::class, 1);
    }

    public function test_motivo_faltante_responde_422(): void
    {
        [, , $gestor, , $pqr, $respuesta] = $this->escenario();

        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('motivo_anulacion');

        $this->assertFalse($respuesta->fresh()->estaAnulada());
    }

    public function test_permisos_y_aislamiento_contextual(): void
    {
        [$organizacion, $copropiedad, $gestor, $residente, $pqr, $respuesta] = $this->escenario();

        // PQR de otra Copropiedad: 404 (sin IDOR).
        [$otraOrganizacion, $otra] = $this->createInstitutionalContext();
        $ajena = Pqr::factory()->paraContexto($otraOrganizacion, $otra)->create(['user_id' => $residente->id]);
        $this->actuando($gestor)->post(route('pqrs.replies.annul', [$ajena, $respuesta]), [
            'motivo_anulacion' => 'X', 'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();

        // El residente radicador, sin gestión efectiva, no puede anular: 403.
        $this->actuando($residente)->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
            'motivo_anulacion' => 'X', 'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertFalse($respuesta->fresh()->estaAnulada());
    }

    public function test_respuesta_anulada_no_se_edita_elimina_ni_reenvia(): void
    {
        [, , $gestor, , $pqr, $respuesta] = $this->escenario();

        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'motivo_anulacion' => 'Motivo', 'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $this->assertTrue($respuesta->fresh()->estaAnulada());

        // No se edita, no se elimina y no se reenvía.
        $this->actuando($gestor)->put(route('pqrs.replies.update', [$pqr, $respuesta]), [
            'body' => 'Texto nuevo', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('reply');
        $this->actuando($gestor)->delete(route('pqrs.replies.destroy', [$pqr, $respuesta]), [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('reply');
        $this->actuando($gestor)->post(route('pqrs.replies.send', [$pqr, $respuesta]), [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('reply');

        // El contenido permanece intacto y la respuesta sigue en su sitio.
        $this->assertSame('Contenido oficial', $respuesta->fresh()->body);
        $this->assertDatabaseCount('pqr_replies', 1);
    }

    public function test_doble_solicitud_es_idempotente_y_no_reanula(): void
    {
        [, , $gestor, , $pqr, $respuesta] = $this->escenario();
        $clave = (string) Str::uuid();

        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'motivo_anulacion' => 'Motivo', 'idempotency_key' => $clave,
            ])->assertRedirect(route('pqrs.show', $pqr))->assertSessionHas('success');

        // Repetición con la misma clave: idempotente (misma operación).
        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'motivo_anulacion' => 'Motivo', 'idempotency_key' => $clave,
            ]);
        $this->assertDatabaseCount('pqr_communication_operations', 1);

        // Con otra clave sobre una respuesta ya anulada: rechazada sin efectos.
        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->post(route('pqrs.replies.annul', [$pqr, $respuesta]), [
                'motivo_anulacion' => 'Otro motivo', 'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('reply');
        $this->assertDatabaseCount('pqr_communication_operations', 1);
        $this->assertSame('Motivo', $respuesta->fresh()->motivo_anulacion);
    }

    public function test_retirar_un_borrador_no_es_anular(): void
    {
        [$organizacion, $copropiedad, $gestor, , $pqr] = $this->escenario();
        $borrador = PqrReply::create([
            'pqr_id' => $pqr->id, 'user_id' => $gestor->id,
            'body' => 'Borrador interno', 'is_draft' => true,
        ]);

        $this->actuando($gestor)->from(route('pqrs.show', $pqr))
            ->delete(route('pqrs.replies.destroy', [$pqr, $borrador]), [
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $this->assertNull($borrador->fresh()->anulada_at);
        $this->assertDatabaseMissing('pqr_communication_operations', ['operation' => 'annul_reply']);
        $this->assertDatabaseMissing('pqr_activities', ['action' => 'annulled_reply']);
        $this->assertNull(AuditLog::query()->where('action', 'pqr.respuesta.anular')->first());
    }

    private function actuando(User $user): static
    {
        auth()->forgetGuards();
        $this->app->forgetScopedInstances();

        return $this->actingAs($user);
    }

    /** @return array{0: Organizacion, 1: Copropiedad, 2: User, 3: User, 4: Pqr, 5: PqrReply} */
    private function escenario(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear', 'pqrs.gestionar',
        ]);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear', 'notificaciones.consultar',
        ]);

        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $residente->id,
            'estado' => 'respondida',
            'fecha_limite_respuesta' => today()->addDay(),
        ]);
        $respuesta = PqrReply::create([
            'pqr_id' => $pqr->id,
            'user_id' => $gestor->id,
            'body' => 'Contenido oficial',
            'is_draft' => false,
            'sent_at' => now(),
            'attachments' => [[
                'name' => 'acta.pdf', 'path' => "pqrs/{$pqr->id}/communications/clave/00", 'storage_key' => 'clave',
                'position' => 0, 'size' => 123, 'mime' => 'application/pdf', 'sha256' => str_repeat('a', 64),
            ]],
        ]);

        return [$organizacion, $copropiedad, $gestor, $residente, $pqr, $respuesta];
    }
}
