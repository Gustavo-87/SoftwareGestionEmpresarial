<?php

namespace Tests\Feature;

use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Application\Notificaciones\ReconciliarNotificacionRespuestaPqrs;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation as OperationType;
use App\Enums\PqrCommunicationResultCode;
use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Models\MembresiaCopropiedad;
use App\Models\Pqr;
use App\Models\PqrCommunicationOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrVerifiedNotificationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private const LEGACY_JOB_FIXTURE = 'O:37:"App\\Jobs\\EnviarCorreoNotificacionPqrs":5:{s:14:"organizacionId";i:101;s:13:"copropiedadId";i:102;s:5:"pqrId";i:103;s:9:"usuarioId";i:104;s:5:"event";s:22:"pqr_estado_actualizado";}';

    public function test_direct_send_and_replay_write_reply_activity_notification_and_job_once(): void
    {
        [$manager, $resident, $pqr] = $this->scenario(validRecipient: true);
        $key = (string) Str::uuid();
        $payload = ['body' => 'Respuesta verificable', 'action' => 'send', 'send_reply_key' => $key];

        $this->actingAsContextual($manager)->post(route('pqrs.replies.store', $pqr), $payload)
            ->assertSessionHas('success', 'Respuesta oficial registrada. Aviso programado para envío.');
        $this->post(route('pqrs.replies.store', $pqr), $payload)
            ->assertSessionHas('success', 'Respuesta oficial registrada. Aviso programado para envío.');

        $operation = PqrCommunicationOperation::where('idempotency_key', $key)->sole();
        $this->assertSame(PqrCommunicationNotificationStatus::Completed, $operation->notification_status);
        $this->assertSame(1, $pqr->replies()->count());
        $this->assertSame(1, DB::table('pqr_activities')->where('pqr_id', $pqr->id)->count());
        $this->assertSame(1, $resident->notifications()->count());
        $this->assertSame($operation->id, $resident->notifications()->sole()->data['operation_id']);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertStringContainsString('operationId', DB::table('jobs')->value('payload'));
    }

    public function test_send_draft_replay_is_a_no_op_after_completed_reconciliation(): void
    {
        [$manager, $resident, $pqr] = $this->scenario(validRecipient: true);
        $draft = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'Borrador', 'is_draft' => true]);
        $key = (string) Str::uuid();
        $payload = ['idempotency_key' => $key];

        $this->actingAsContextual($manager)->post(route('pqrs.replies.send', [$pqr, $draft]), $payload)
            ->assertSessionHas('success', 'Respuesta oficial registrada. Aviso programado para envío.');
        $this->post(route('pqrs.replies.send', [$pqr, $draft->id]), $payload)
            ->assertSessionHas('success', 'Respuesta oficial registrada. Aviso programado para envío.');

        $this->assertSame(1, $resident->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, PqrCommunicationOperation::where('idempotency_key', $key)->count());
    }

    public function test_missing_revoked_and_out_of_context_recipient_are_no_recipient_without_artifacts(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $org, $cop, 'manager_no_recipient', ['pqrs.ver_todas', 'pqrs.gestionar']);
        foreach (['missing', 'revoked', 'external'] as $case) {
            $resident = User::factory()->create();
            $membership = $case === 'missing'
                ? null
                : $this->createContextualIdentity($resident, $org, $cop, "resident_{$case}", ['notificaciones.consultar'])[0];
            if ($case === 'revoked') {
                $membership->update(['estado' => 'inactiva']);
            }
            if ($case === 'external') {
                [$otherOrg, $otherCop] = $this->createInstitutionalContext();
                $membership->update(['estado' => 'inactiva']);
                $this->createContextualIdentity($resident, $otherOrg, $otherCop, "resident_{$case}_other", ['notificaciones.consultar']);
            }
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'estado' => 'radicada']);
            $key = (string) Str::uuid();
            $this->actingAsContextual($manager)->post(route('pqrs.replies.store', $pqr), ['body' => $case, 'action' => 'send', 'send_reply_key' => $key])
                ->assertRedirect()
                ->assertSessionHas('success', 'Respuesta oficial registrada. No hay un destinatario habilitado para recibir el aviso.');
            $this->assertSame(PqrCommunicationNotificationStatus::NoRecipient, PqrCommunicationOperation::where('idempotency_key', $key)->sole()->notification_status);
        }

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_enqueue_failure_rolls_back_artifacts_and_leaves_pending_with_exact_flash(): void
    {
        [$manager, , $pqr] = $this->scenario(validRecipient: true);
        Queue::shouldReceive('connection')->once()->with('database')->andThrow(new RuntimeException('queue unavailable'));
        $key = (string) Str::uuid();

        $this->actingAsContextual($manager)->post(route('pqrs.replies.store', $pqr), ['body' => 'Pendiente', 'action' => 'send', 'send_reply_key' => $key])
            ->assertSessionHas('success', 'Respuesta oficial registrada. El aviso queda pendiente de reintento.');

        $this->assertSame(PqrCommunicationNotificationStatus::Pending, PqrCommunicationOperation::where('idempotency_key', $key)->sole()->notification_status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('pqr_replies', 1);
        $this->assertDatabaseCount('pqr_activities', 1);
    }

    public function test_failure_during_job_insert_also_rolls_back_notification_and_keeps_pending(): void
    {
        [$manager, , $pqr] = $this->scenario(validRecipient: true);
        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->once()->andThrow(new RuntimeException('insert failed'));
        Queue::shouldReceive('connection')->once()->with('database')->andReturn($queue);
        $key = (string) Str::uuid();

        $this->actingAsContextual($manager)->post(route('pqrs.replies.store', $pqr), ['body' => 'Pendiente', 'action' => 'send', 'send_reply_key' => $key])
            ->assertSessionHas('success', 'Respuesta oficial registrada. El aviso queda pendiente de reintento.');

        $this->assertSame(PqrCommunicationNotificationStatus::Pending, PqrCommunicationOperation::where('idempotency_key', $key)->sole()->notification_status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_command_retries_pending_and_ignores_seventeen_historical_jobs_without_ledger(): void
    {
        [, $resident, $pqr, , $operation] = $this->scenarioWithPendingOperation();
        foreach (range(1, 17) as $index) {
            Queue::connection('database')->push(new EnviarCorreoNotificacionPqrs(1, 1, $index, $resident->id, 'pqr_estado_actualizado'));
        }
        $historicalPayloads = DB::table('jobs')->orderBy('id')->pluck('payload')->all();

        $this->artisan('pqrs:reconcile-response-notifications')
            ->expectsOutput('processed=1 completed=1 no_recipient=0 pending=0')
            ->assertSuccessful();

        $this->assertSame(PqrCommunicationNotificationStatus::Completed, $operation->fresh()->notification_status);
        $this->assertSame(1, $resident->notifications()->count());
        $this->assertSame(18, DB::table('jobs')->count());
        $this->assertSame($historicalPayloads, DB::table('jobs')->orderBy('id')->limit(17)->pluck('payload')->all());
        $this->assertSame($pqr->id, $resident->notifications()->sole()->data['resource_id']);
    }

    public function test_failed_job_returns_to_pending_and_retry_does_not_duplicate_database_notification(): void
    {
        [, $resident, , , $operation] = $this->scenarioWithPendingOperation();
        app(ReconciliarNotificacionRespuestaPqrs::class)->ejecutar($operation);
        $this->assertSame(1, $resident->notifications()->count());
        DB::table('jobs')->delete();
        $job = new EnviarCorreoNotificacionPqrs(1, 1, 1, $resident->id, 'pqr_respuesta_enviada', $operation->id);
        $job->failed(new RuntimeException('mail failed'));
        $this->assertSame(PqrCommunicationNotificationStatus::Pending, $operation->fresh()->notification_status);

        $this->artisan('pqrs:reconcile-response-notifications')->assertSuccessful();
        $this->assertSame(1, $resident->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_job_revalidates_revocation_marks_no_recipient_and_sends_no_mail(): void
    {
        [, , , $membership, $operation] = $this->scenarioWithPendingOperation();
        app(ReconciliarNotificacionRespuestaPqrs::class)->ejecutar($operation);
        $membership->update(['estado' => 'inactiva']);
        Mail::fake();
        $job = new EnviarCorreoNotificacionPqrs(
            $operation->pqr->organizacion_id, $operation->pqr->copropiedad_id,
            $operation->pqr_id, $operation->pqr->user_id, 'pqr_respuesta_enviada', $operation->id,
        );

        $job->handle(app(EmitirNotificacionPqrs::class), app(ReconciliarNotificacionRespuestaPqrs::class));

        Mail::assertNothingSent();
        $this->assertSame(PqrCommunicationNotificationStatus::NoRecipient, $operation->fresh()->notification_status);
        $data = DB::table('notifications')->value('data');
        $this->assertStringNotContainsString($operation->pqr->descripcion, $data);
    }

    public function test_anonymized_real_legacy_payload_deserializes_and_keeps_historical_flow_without_touching_ledger(): void
    {
        [, , , , $operation] = $this->scenarioWithPendingOperation();
        $job = unserialize(self::LEGACY_JOB_FIXTURE, ['allowed_classes' => [EnviarCorreoNotificacionPqrs::class]]);
        $this->assertInstanceOf(EnviarCorreoNotificacionPqrs::class, $job);
        $this->assertSame('pqr_estado_actualizado:pqr:103:104:mail', $job->uniqueId());

        $job->handle(app(EmitirNotificacionPqrs::class));
        $job->failed(new RuntimeException('legacy failure'));

        $this->assertSame(PqrCommunicationNotificationStatus::Pending, $operation->fresh()->notification_status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @return array{User,User,Pqr,?MembresiaCopropiedad} */
    private function scenario(bool $validRecipient): array
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $resident = User::factory()->create();
        $this->createContextualIdentity($manager, $org, $cop, 'manager_b3', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $membership = $validRecipient
            ? $this->createContextualIdentity($resident, $org, $cop, 'resident_b3', ['notificaciones.consultar'])[0]
            : null;
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'estado' => 'radicada']);

        return [$manager, $resident, $pqr, $membership];
    }

    /** @return array{User,User,Pqr,MembresiaCopropiedad,PqrCommunicationOperation} */
    private function scenarioWithPendingOperation(): array
    {
        [$manager, $resident, $pqr, $membership] = $this->scenario(validRecipient: true);
        $reply = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'Oficial', 'is_draft' => false, 'sent_at' => now()]);
        $repository = app(PqrCommunicationOperationRepository::class);
        $claim = $repository->claim(
            $pqr, $manager, OperationType::SendReply, (string) Str::uuid(), hash('sha256', 'b3'), null,
            PqrCommunicationNotificationStatus::Pending,
        );
        $operation = $repository->complete($claim->operation, PqrCommunicationResultCode::ReplySent, $reply->id);

        return [$manager, $resident, $pqr, $membership, $operation];
    }
}
