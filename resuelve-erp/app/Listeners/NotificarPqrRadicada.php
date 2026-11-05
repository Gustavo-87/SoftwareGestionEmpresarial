<?php

namespace App\Listeners;

use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Events\PqrRadicada;

/**
 * Emite la notificación de radicación (HU-NOT-03, Sprint 23).
 *
 * Síncrono y por descubrimiento automático: conserva el momento, los
 * destinatarios y los canales (campana `database` y correo vía job) de la
 * invocación directa anterior, sin duplicar reglas.
 */
final class NotificarPqrRadicada
{
    public function __construct(private readonly EmitirNotificacionPqrs $notificaciones) {}

    public function handle(PqrRadicada $evento): void
    {
        $this->notificaciones->ejecutar($evento->contexto, $evento->pqr, 'pqr_creada');
    }
}
