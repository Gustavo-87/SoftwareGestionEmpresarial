<?php

namespace App\Http\Requests;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\ConsultaEtiquetasPqrsContextuales;
use App\Models\PqrTag;
use Illuminate\Validation\Rules\Unique;

/**
 * Edición de Etiqueta (HU-CONF-03). Misma validación que el alta, con la
 * etiqueta actual excluida de la unicidad contextual.
 */
final class UpdateEtiquetaRequest extends StoreEtiquetaRequest
{
    public function authorize(): bool
    {
        parent::authorize();

        // La resolución contextual precede a la validación: una etiqueta de
        // otra Copropiedad responde 404 sin revelar existencia (sin IDOR).
        $tag = $this->route('tag');
        app(ConsultaEtiquetasPqrsContextuales::class)->resolver(
            app(ContextoOperativo::class),
            $tag instanceof PqrTag ? $tag->getKey() : (string) $tag,
        );

        return true;
    }

    protected function unicidad(ContextoOperativo $contexto): Unique
    {
        $tag = $this->route('tag');

        return parent::unicidad($contexto)->ignore($tag instanceof PqrTag ? $tag->id : null);
    }
}
