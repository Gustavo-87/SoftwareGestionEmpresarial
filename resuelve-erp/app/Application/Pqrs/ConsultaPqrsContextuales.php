<?php

namespace App\Application\Pqrs;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class ConsultaPqrsContextuales
{
    public function para(ContextoOperativo $contexto): Builder
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de PQRS es inconsistente.');
        }

        return Pqr::query()
            ->tap(fn (Builder $query) => $this->restringir($query, $contexto));
    }

    public function restringir(Builder $query, ContextoOperativo $contexto): Builder
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de PQRS es inconsistente.');
        }

        return $query
            ->where($query->qualifyColumn('organizacion_id'), $contexto->organizacion->id)
            ->where($query->qualifyColumn('copropiedad_id'), $contexto->copropiedad->id);
    }

    public function resolver(ContextoOperativo $contexto, int|string $identificador): Pqr
    {
        $modelo = new Pqr();

        return $this->para($contexto)
            ->where($modelo->getRouteKeyName(), $identificador)
            ->firstOrFail();
    }
}
