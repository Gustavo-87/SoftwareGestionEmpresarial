<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use App\Models\PqrReply;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class VisibilidadBorradoresPqrs
{
    public function __construct(private readonly AutorizacionContextual $autorizacion) {}

    public function restringir(Builder|Relation $query, ContextoOperativo $contexto, Pqr $pqr): Builder|Relation
    {
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            return $query->where('is_draft', false);
        }
        if ($this->gestionaBorradoresAjenos($contexto)) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->where('is_draft', false)->orWhere('user_id', $contexto->usuario?->id));
    }

    public function estado(ContextoOperativo $contexto, Pqr $pqr, PqrReply $reply): string
    {
        if (! $reply->is_draft) {
            return 'visible';
        }
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            return $reply->user_id === $contexto->usuario?->id ? 'forbidden' : 'hidden';
        }
        if ($reply->user_id === $contexto->usuario?->id || $this->gestionaBorradoresAjenos($contexto)) {
            return 'visible';
        }

        return 'hidden';
    }

    public function estadoMutacion(ContextoOperativo $contexto, Pqr $pqr, PqrReply $reply): string
    {
        $visibilidad = $this->estado($contexto, $pqr, $reply);
        if ($visibilidad !== 'visible') {
            return $visibilidad;
        }

        return $reply->user_id === $contexto->usuario?->id ? 'allowed' : 'forbidden';
    }

    private function gestionaBorradoresAjenos(ContextoOperativo $contexto): bool
    {
        return $this->autorizacion->tieneRol($contexto, 'admin') || $this->autorizacion->tieneRol($contexto, 'gestor');
    }
}
