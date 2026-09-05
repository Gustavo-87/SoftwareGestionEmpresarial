<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Mail\PqrEventMail;
use App\Models\Pqr;
use App\Models\User;
use App\Notifications\PqrEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class EmisionNotificacionesPqrsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_emite_los_cinco_eventos_aprobados_a_sus_destinatarios(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $residente = User::factory()->create();
        $responsable = User::factory()->create();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente_eventos', ['notificaciones.consultar']);
        $this->createContextualIdentity($responsable, $organizacion, $copropiedad, 'responsable_eventos', ['notificaciones.consultar']);
        [, , $contexto] = $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor_eventos', ['notificaciones.consultar', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $residente->id,
            'assigned_to_id' => $responsable->id,
            'fecha_limite_respuesta' => today()->addDay(),
        ]);
        Notification::fake();
        Queue::fake();

        foreach (['pqr_creada', 'pqr_estado_actualizado', 'pqr_asignada', 'pqr_respuesta_enviada', 'pqr_recordatorio_vencimiento'] as $evento) {
            app(EmitirNotificacionPqrs::class)->ejecutar($contexto, $pqr, $evento);
        }

        $emitidas = Notification::sent($residente, PqrEventNotification::class)
            ->concat(Notification::sent($responsable, PqrEventNotification::class))
            ->concat(Notification::sent($gestor, PqrEventNotification::class));
        $this->assertSame([
            'pqr_asignada' => 1,
            'pqr_creada' => 1,
            'pqr_estado_actualizado' => 1,
            'pqr_recordatorio_vencimiento' => 2,
            'pqr_respuesta_enviada' => 1,
        ], $emitidas->countBy('event')->sortKeys()->all());
    }

    public function test_persiste_database_antes_de_despachar_el_job(): void
    {
        [$contexto, $pqr, $residente] = $this->escenarioResidente();
        Queue::fake();

        app(EmitirNotificacionPqrs::class)->ejecutar($contexto, $pqr, 'pqr_estado_actualizado');

        Queue::assertPushed(EnviarCorreoNotificacionPqrs::class, function ($job) use ($residente): bool {
            $notificacion = $residente->notifications()->first();

            return $notificacion !== null && $notificacion->data['event'] === 'pqr_estado_actualizado';
        });
    }

    public function test_una_operacion_revertida_no_emite_database_ni_job(): void
    {
        [$contexto, $pqr] = $this->escenarioResidente();
        Queue::fake();

        try {
            DB::transaction(function () use ($pqr): void {
                $pqr->update(['estado' => 'en_revision']);
                throw new RuntimeException('rollback intencional');
            });
        } catch (RuntimeException) {
            // La emisión se ejecuta únicamente después de una transacción exitosa.
        }

        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNothingPushed();
        $this->assertSame('radicada', $pqr->fresh()->estado);
    }

    public function test_job_envia_con_contexto_vigente_y_cancela_con_membresia_revocada(): void
    {
        [$contexto, $pqr, $residente, $membresia] = $this->escenarioResidente(true);
        Mail::fake();
        $job = new EnviarCorreoNotificacionPqrs($contexto->organizacion->id, $contexto->copropiedad->id, $pqr->id, $residente->id, 'pqr_estado_actualizado');

        $job->handle(app(EmitirNotificacionPqrs::class));
        Mail::assertSent(PqrEventMail::class, fn ($correo) => $correo->hasTo($residente->email));

        Mail::fake();
        $membresia->update(['estado' => 'inactiva']);
        $job->handle(app(EmitirNotificacionPqrs::class));
        Mail::assertNothingSent();
    }

    public function test_job_define_reintentos_progresivos_e_idempotencia_por_canal(): void
    {
        $primero = new EnviarCorreoNotificacionPqrs(1, 2, 3, 4, 'pqr_asignada');
        $reintento = new EnviarCorreoNotificacionPqrs(1, 2, 3, 4, 'pqr_asignada');

        $this->assertSame(3, $primero->tries);
        $this->assertSame([60, 300, 900], $primero->backoff());
        $this->assertSame('pqr_asignada:pqr:3:4:mail', $primero->uniqueId());
        $this->assertSame($primero->uniqueId(), $reintento->uniqueId());
    }

    public function test_deduplica_un_destinatario_que_es_residente_y_responsable(): void
    {
        [$contexto, $pqr, $residente] = $this->escenarioResidente();
        $pqr->update(['assigned_to_id' => $residente->id]);
        Notification::fake();
        Queue::fake();

        app(EmitirNotificacionPqrs::class)->ejecutar($contexto, $pqr, 'pqr_recordatorio_vencimiento');

        Notification::assertSentToTimes($residente, PqrEventNotification::class, 1);
        Queue::assertPushed(EnviarCorreoNotificacionPqrs::class, 1);
    }

    public function test_fallo_definitivo_del_job_se_registra_sin_datos_sensibles(): void
    {
        Log::shouldReceive('error')->once()->with(
            'Falló definitivamente el envío de correo de una notificación PQRS.',
            \Mockery::on(fn (array $datos) => $datos['event'] === 'pqr_asignada' && ! array_key_exists('email', $datos)),
        );

        (new EnviarCorreoNotificacionPqrs(1, 2, 3, 4, 'pqr_asignada'))->failed(new RuntimeException('fallo'));
    }

    public function test_solo_el_emisor_central_instancia_pqr_event_notification(): void
    {
        $hallazgos = collect(File::allFiles(app_path()))
            ->filter(fn ($archivo) => preg_match('/new\s+PqrEventNotification\s*\(/', $archivo->getContents()) === 1)
            ->map(fn ($archivo) => $archivo->getRealPath())
            ->values()
            ->all();

        $this->assertSame([app_path('Application/Notificaciones/EmitirNotificacionPqrs.php')], $hallazgos);
    }

    /** @return array{\App\Application\Contexto\ContextoOperativo, Pqr, User, ?\App\Models\MembresiaCopropiedad} */
    private function escenarioResidente(bool $incluirMembresia = false): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $residente = User::factory()->create();
        [$membresia] = $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente_emision', ['notificaciones.consultar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $residente->id,
            'estado' => 'radicada',
            'fecha_limite_respuesta' => today()->addDay(),
        ]);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id);

        return [$contexto, $pqr, $residente, $incluirMembresia ? $membresia : null];
    }
}
