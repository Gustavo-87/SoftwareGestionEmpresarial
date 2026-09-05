<?php

namespace App\Application\Notificaciones;

use App\Application\Contexto\ContextoOperativo;
use App\Models\User;

final class MarcarNotificacionesContextualesComoLeidas
{
    public function __construct(private readonly ConsultaNotificacionesContextuales $notificaciones) {}

    public function ejecutar(ContextoOperativo $contexto, User $usuario): int
    {
        return $this->notificaciones->para($contexto, $usuario)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
