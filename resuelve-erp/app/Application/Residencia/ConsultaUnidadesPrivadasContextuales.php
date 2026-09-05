<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Models\UnidadPrivada;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class ConsultaUnidadesPrivadasContextuales
{
    public function para(ContextoOperativo $contexto): Builder
    {
        return UnidadPrivada::query()->tap(fn (Builder $query) => $this->restringir($query, $contexto));
    }

    public function restringir(Builder $query, ContextoOperativo $contexto): Builder
    {
        $this->validarContexto($contexto);

        return $query
            ->where($query->qualifyColumn('organizacion_id'), $contexto->organizacion->id)
            ->where($query->qualifyColumn('copropiedad_id'), $contexto->copropiedad->id);
    }

    public function resolver(ContextoOperativo $contexto, int|string $identificador): UnidadPrivada
    {
        $modelo = new UnidadPrivada();

        return $this->para($contexto)
            ->where($modelo->getRouteKeyName(), $identificador)
            ->firstOrFail();
    }

    private function validarContexto(ContextoOperativo $contexto): void
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de Unidades Privadas es inconsistente.');
        }
    }
}
