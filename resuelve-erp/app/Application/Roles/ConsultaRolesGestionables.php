<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

final class ConsultaRolesGestionables
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    /**
     * Roles que el operador puede administrar:
     * autoridad de plataforma → todos; autoridad contextual → los de su Copropiedad.
     */
    public function ejecutar(User $operador, ?ContextoOperativo $contexto): Collection
    {
        $this->autorizacion->autorizarGestion($operador, $contexto);

        $query = SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->with('permissions')
            ->orderBy('name');

        if (! $this->autorizacion->esAutoridadPlataforma($operador)) {
            $query->where('copropiedad_id', $contexto->copropiedad->id);
        }

        $asignaciones = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->groupBy('role_id')
            ->selectRaw('role_id, count(*) as total')
            ->pluck('total', 'role_id');

        return $query->get()->each(function (SpatieRole $rol) use ($asignaciones): void {
            $rol->setAttribute('asignaciones', (int) ($asignaciones[$rol->id] ?? 0));
        });
    }

    /**
     * Roles asignables en una Copropiedad: globales y del equipo. La
     * autorización la ejerce el flujo llamador sobre sus propias acciones.
     */
    public function asignablesEn(int $copropiedadId): Collection
    {
        return SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->where(fn ($query) => $query->whereNull('copropiedad_id')->orWhere('copropiedad_id', $copropiedadId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Roles cuya matriz de permisos puede copiarse al crear un Rol:
     * globales y del alcance gestionable por el operador.
     */
    public function origenesDeCopia(User $operador, ?ContextoOperativo $contexto): Collection
    {
        $this->autorizacion->autorizarGestion($operador, $contexto);

        return SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->when(! $this->autorizacion->esAutoridadPlataforma($operador), fn ($query) => $query->where(
                fn ($query) => $query->whereNull('copropiedad_id')
                    ->orWhere('copropiedad_id', $contexto->copropiedad->id)
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'copropiedad_id']);
    }
}
