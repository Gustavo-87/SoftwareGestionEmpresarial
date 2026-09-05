<?php

namespace App\Application\Notificaciones;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AbrirNotificacionContextual
{
    public function __construct(
        private readonly ConsultaNotificacionesContextuales $notificaciones,
        private readonly ConsultaPqrsContextuales $pqrs,
        private readonly AutorizacionContextual $autorizacion,
    ) {}

    public function ejecutar(ContextoOperativo $contexto, User $usuario, string $notificacionId): Pqr
    {
        $notificacion = $this->notificaciones->resolver($contexto, $usuario, $notificacionId);
        $datos = $notificacion->data;

        if (($datos['resource_type'] ?? null) !== 'pqr' || ! is_numeric($datos['resource_id'] ?? null)) {
            throw (new ModelNotFoundException())->setModel(Pqr::class);
        }

        $pqr = $this->pqrs->resolver($contexto, (int) $datos['resource_id']);
        if (! $this->autorizacion->puedeVerPqr($contexto, $pqr)) {
            throw (new ModelNotFoundException())->setModel(Pqr::class, [$datos['resource_id']]);
        }

        $notificacion->markAsRead();

        return $pqr;
    }
}
