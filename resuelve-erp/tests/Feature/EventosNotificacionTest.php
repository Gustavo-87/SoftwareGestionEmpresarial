<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\AplicarAccionRapidaPqrs;
use App\Application\Pqrs\PresentarPqrs;
use App\Events\PqrEstadoActualizado;
use App\Events\PqrRadicada;
use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-NOT-03 — Eventos de dominio con listeners para la emisión de
 * Notificaciones (Sprint 23).
 */
class EventosNotificacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_los_eventos_se_despachan_al_radicar_y_al_cambiar_estado(): void
    {
        [$contexto, $usuario, $tipo] = $this->escenario();
        Event::fake();

        $pqr = app(PresentarPqrs::class)->ejecutar($contexto, $usuario, $this->datos($tipo), []);

        Event::assertDispatched(PqrRadicada::class);
        Event::assertNotDispatched(PqrEstadoActualizado::class);

        app(AplicarAccionRapidaPqrs::class)->ejecutar($contexto, $usuario, $pqr, ['estado' => 'en_revision']);

        Event::assertDispatched(PqrEstadoActualizado::class);
    }

    public function test_los_listeners_conservan_campana_y_correo(): void
    {
        [$contexto, $usuario, $tipo] = $this->escenario();
        Queue::fake();

        // Sin Event::fake: los listeners ejecutan la emisión real.
        $pqr = app(PresentarPqrs::class)->ejecutar($contexto, $usuario, $this->datos($tipo), []);

        // Campana (canal database) persistida y correo dispachado como job.
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(EnviarCorreoNotificacionPqrs::class, 1);

        app(AplicarAccionRapidaPqrs::class)->ejecutar($contexto, $usuario, $pqr, ['estado' => 'en_revision']);

        $this->assertDatabaseCount('notifications', 2);
        Queue::assertPushed(EnviarCorreoNotificacionPqrs::class, 2);
    }

    public function test_un_rollback_no_dispara_el_evento_ni_sus_efectos(): void
    {
        [$contexto, $usuario, $tipo] = $this->escenario();
        Event::fake();
        Queue::fake();

        // Contexto institucional inconsistente (la Copropiedad pertenece a otra
        // Organización): la transacción de la radicación se revierte.
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra Organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Ajena',
            'estado' => 'activa',
        ]);
        $inconsistente = new ContextoOperativo(
            $contexto->usuario,
            $contexto->organizacion,
            $otraCopropiedad,
            $contexto->membresiaCopropiedad,
            $contexto->roles,
            $contexto->permisos,
            'prueba-rollback',
        );

        try {
            app(PresentarPqrs::class)->ejecutar($inconsistente, $usuario, $this->datos($tipo), []);
            $this->fail('Se esperaba la reversión de la transacción.');
        } catch (RuntimeException) {
            // Reversión intencional: la emisión solo ocurre tras el commit.
        }

        Event::assertNotDispatched(PqrRadicada::class);
        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('pqrs', 0);
    }

    /** @return array{0: ContextoOperativo, 1: User, 2: TipoPqr} */
    private function escenario(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'gestor_eventos', [
            'notificaciones.consultar',
            'pqrs.crear',
            'pqrs.gestionar',
        ]);
        $tipo = TipoPqr::create(['nombre' => 'Petición']);

        return [$contexto, $usuario, $tipo];
    }

    private function datos(TipoPqr $tipo): array
    {
        return [
            'asunto' => 'Solicitud de prueba',
            'descripcion' => 'Detalle de la solicitud.',
            'tipo_pqr_id' => $tipo->id,
        ];
    }
}
