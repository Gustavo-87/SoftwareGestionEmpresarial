<?php

namespace App\Application\Notificaciones;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Mail\PqrEventMail;
use App\Models\Pqr;
use App\Models\User;
use App\Notifications\PqrEventNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;

final class EmitirNotificacionPqrs
{
    private const EVENTOS = [
        'pqr_creada',
        'pqr_estado_actualizado',
        'pqr_asignada',
        'pqr_respuesta_enviada',
        'pqr_recordatorio_vencimiento',
    ];

    public function __construct(
        private readonly ConsultaPqrsContextuales $pqrs,
        private readonly ResolverDestinatariosNotificacionPqrs $destinatarios,
        private readonly ContextResolver $contextos,
    ) {}

    public function ejecutar(ContextoOperativo $contexto, Pqr $pqr, string $event): void
    {
        $this->validarEvento($event);
        $pqr = $this->pqrs->resolver($contexto, $pqr->id);
        [$title, $message] = $this->contenido($pqr, $event);

        $this->destinatarios->resolver($contexto, $pqr, $event)->each(function (User $usuario) use ($contexto, $pqr, $event, $title, $message): void {
            $usuario->notify(new PqrEventNotification($pqr, $event, $title, $message));
            EnviarCorreoNotificacionPqrs::dispatch(
                $contexto->organizacion->id,
                $contexto->copropiedad->id,
                $pqr->id,
                $usuario->id,
                $event,
            );
        });
    }

    public function paraOperacion(Pqr $pqr, string $event, int $operationId): PqrEventNotification
    {
        $this->validarEvento($event);
        [$title, $message] = $this->contenido($pqr, $event);

        return new PqrEventNotification($pqr, $event, $title, $message, $operationId);
    }

    public function enviarCorreo(int $organizacionId, int $copropiedadId, int $pqrId, int $usuarioId, string $event): bool
    {
        $this->validarEvento($event);
        try {
            $contexto = $this->contextos->resolverExplicito($organizacionId, $copropiedadId, $usuarioId);
            $pqr = $this->pqrs->resolver($contexto, $pqrId);
        } catch (ModelNotFoundException|RuntimeException) {
            return false;
        }
        $usuario = $contexto->usuario;

        if (! $usuario || ! $this->destinatarios->resolver($contexto, $pqr, $event)->contains('id', $usuario->id)) {
            return false;
        }

        [$title, $message] = $this->contenido($pqr, $event);
        Mail::to($usuario)->send(new PqrEventMail($title, $message, route('pqrs.show', $pqr)));

        return true;
    }

    private function validarEvento(string $event): void
    {
        if (! in_array($event, self::EVENTOS, true)) {
            throw new InvalidArgumentException("El evento de notificación {$event} no está aprobado.");
        }
    }

    /** @return array{string, string} */
    private function contenido(Pqr $pqr, string $event): array
    {
        $numero = 'PQR-'.str_pad((string) $pqr->id, 4, '0', STR_PAD_LEFT);

        return match ($event) {
            'pqr_creada' => ['Nueva solicitud radicada', "Se creó la {$numero}: {$pqr->asunto}"],
            'pqr_estado_actualizado' => ['Estado de solicitud actualizado', "La {$numero} ahora está {$pqr->estado_label}."],
            'pqr_asignada' => ['Solicitud asignada', "Te asignaron la {$numero}."],
            'pqr_respuesta_enviada' => ['Tu solicitud fue respondida', "La solicitud {$numero} tiene una nueva respuesta."],
            'pqr_recordatorio_vencimiento' => ['Solicitud próxima a vencer', "La {$numero} vence el {$pqr->fecha_limite_respuesta->format('d/m/Y')}."],
        };
    }
}
