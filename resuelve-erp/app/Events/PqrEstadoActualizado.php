<?php

namespace App\Events;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;

/**
 * Evento de dominio: el estado de una PQR fue actualizado (HU-NOT-03,
 * Sprint 23).
 *
 * Se despacha de forma síncrona tras el commit, en el mismo punto donde antes
 * se invocaba la emisión directa de notificaciones.
 */
final class PqrEstadoActualizado
{
    public function __construct(
        public readonly ContextoOperativo $contexto,
        public readonly Pqr $pqr,
    ) {}
}
