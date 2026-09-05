<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Models\VinculoUnidad;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class ConsultaVinculosUnidadContextuales
{
    public function para(ContextoOperativo $contexto): Builder
    {
        return VinculoUnidad::query()->tap(fn (Builder $query) => $this->restringir($query, $contexto));
    }

    public function restringir(Builder $query, ContextoOperativo $contexto): Builder
    {
        $this->validarContexto($contexto);

        return $query
            ->where($query->qualifyColumn('organizacion_id'), $contexto->organizacion->id)
            ->where($query->qualifyColumn('copropiedad_id'), $contexto->copropiedad->id);
    }

    public function resolver(ContextoOperativo $contexto, int|string $identificador): VinculoUnidad
    {
        $modelo = new VinculoUnidad();

        return $this->para($contexto)
            ->where($modelo->getRouteKeyName(), $identificador)
            ->firstOrFail();
    }

    private function validarContexto(ContextoOperativo $contexto): void
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de Vínculos de Unidad es inconsistente.');
        }
    }
}
