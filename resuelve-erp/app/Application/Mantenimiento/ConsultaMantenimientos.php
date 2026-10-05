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

    /**
     * Conteos resumen del listado derivados del catálogo real del modelo
     * (estados pendiente/en_proceso/finalizado y fecha_programada). El indicador
     * "atrasados" corresponde a fecha programada pasada y sin finalizar.
     * Se resuelve en una sola consulta agregada, sin N+1.
     *
     * @return array{total:int,pendientes:int,en_proceso:int,finalizados:int,programados:int,atrasados:int}
     */
    public function resumen(ContextoOperativo $contexto): array
    {
        $fila = $this->para($contexto)->selectRaw(
            "COUNT(*) as total,
             COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END), 0) as pendientes,
             COALESCE(SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END), 0) as en_proceso,
             COALESCE(SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END), 0) as finalizados,
             COALESCE(SUM(CASE WHEN fecha_programada IS NOT NULL THEN 1 ELSE 0 END), 0) as programados,
             COALESCE(SUM(CASE WHEN fecha_programada IS NOT NULL AND fecha_programada < ? AND estado <> 'finalizado' THEN 1 ELSE 0 END), 0) as atrasados",
            [now()->toDateString()]
        )->toBase()->first();

        return [
            'total' => (int) $fila->total,
            'pendientes' => (int) $fila->pendientes,
            'en_proceso' => (int) $fila->en_proceso,
            'finalizados' => (int) $fila->finalizados,
            'programados' => (int) $fila->programados,
            'atrasados' => (int) $fila->atrasados,
        ];
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
