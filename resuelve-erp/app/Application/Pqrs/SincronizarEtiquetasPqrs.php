<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SincronizarEtiquetasPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly ConsultaPqrsContextuales $consultaPqrs,
        private readonly RegistrarActuacionPqrs $actuaciones,
    ) {}

    /** @param list<int> $etiquetas */
    public function ejecutar(ContextoOperativo $contexto, User $usuario, Pqr $pqr, array $etiquetas): void
    {
        $pqr = $this->consultaPqrs->resolver($contexto, $pqr->getKey());
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException();
        }

        $etiquetas = array_values(array_unique(array_map('intval', $etiquetas)));
        sort($etiquetas);

        DB::transaction(function () use ($contexto, $usuario, $pqr, $etiquetas): void {
            $originales = $pqr->tags()->lockForUpdate()->pluck('pqr_tags.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $this->validarEtiquetas($contexto, $etiquetas, $originales);
            $anadidas = array_diff($etiquetas, $originales);
            $retiradas = array_diff($originales, $etiquetas);
            if ($anadidas === [] && $retiradas === []) {
                return;
            }

            $pqr->tags()->syncWithPivotValues($etiquetas, [
                'organizacion_id' => $contexto->organizacion->id,
                'copropiedad_id' => $contexto->copropiedad->id,
            ]);
            $this->actuaciones->registrar($pqr, $usuario, 'tags_updated', 'Actualizó las etiquetas de la solicitud.');
        });
    }

    /** @param list<int> $etiquetas */
    private function validarEtiquetas(ContextoOperativo $contexto, array $etiquetas, array $originales): void
    {
        if ($etiquetas === []) {
            return;
        }

        $cantidad = PqrTag::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->where(fn ($query) => $query->where('activo', true)->orWhereIn('id', $originales))
            ->whereIn('id', $etiquetas)
            ->count();

        if ($cantidad !== count(array_unique($etiquetas))) {
            throw ValidationException::withMessages(['tags.0' => ['La etiqueta seleccionada no es válida.']]);
        }
    }
}
