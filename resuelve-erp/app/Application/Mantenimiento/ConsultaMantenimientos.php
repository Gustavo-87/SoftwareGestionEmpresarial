<?php

namespace App\Application\Mantenimiento;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Mantenimiento;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class ConsultaMantenimientos
{
    public function para(ContextoOperativo $contexto): Builder
    {
        Gate::forUser($contexto->usuario)->authorize('viewAny', Mantenimiento::class);
        $query = Mantenimiento::query()->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id);
        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'mantenimiento.ver_todas')) {
            $query->where('solicitante_id', $contexto->usuario->id);
        }

        return $query;
    }

    public function resolver(ContextoOperativo $contexto, int|string $id): Mantenimiento
    {
        return $this->para($contexto)->whereKey($id)->firstOrFail();
    }

    public function responsables(ContextoOperativo $contexto): Builder
    {
        return User::query()->where('estado', 'activo')->whereIn('id',
            MembresiaCopropiedad::query()->select('usuario_id')
                ->where('organizacion_id', $contexto->organizacion->id)
                ->where('copropiedad_id', $contexto->copropiedad->id)
                ->where('estado', 'activa')->where('vigente_desde', '<=', now())
                ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now())));
    }
}
