<?php

namespace App\Application\Pqrs;

use App\Application\Contexto\ContextoOperativo;
use App\Models\PqrTag;
use Illuminate\Database\Eloquent\Builder;

final class ConsultaEtiquetasPqrsContextuales
{
    public function para(ContextoOperativo $contexto): Builder
    {
        return PqrTag::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id);
    }

    public function resolver(ContextoOperativo $contexto, int|string $id): PqrTag
    {
        return $this->para($contexto)->findOrFail($id);
    }
}
