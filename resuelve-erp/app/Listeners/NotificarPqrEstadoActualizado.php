<?php

namespace App\Listeners;

use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Events\PqrEstadoActualizado;

/**
 * Emite la notificación de cambio de estado (HU-NOT-03, Sprint 23).
 *
 * Síncrono y por descubrimiento automático: conserva el string funcional
 * `pqr_estado_actualizado`, el momento, los destinatarios y los canales de la
 * invocación directa anterior, sin duplicar reglas.
 */
final class NotificarPqrEstadoActualizado
{
    public function __construct(private readonly EmitirNotificacionPqrs $notificaciones) {}

    public function handle(PqrEstadoActualizado $evento): void
    {
        $this->notificaciones->ejecutar($evento->contexto, $evento->pqr, 'pqr_estado_actualizado');
    }
}
