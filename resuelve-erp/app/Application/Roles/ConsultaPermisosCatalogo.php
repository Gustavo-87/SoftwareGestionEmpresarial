<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

final class ConsultaPermisosCatalogo
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    /**
     * Catálogo de permisos agrupado por módulo (prefijo de la clave).
     *
     * @return array<string, list<Permission>>
     */
    public function porModulo(User $operador, ?ContextoOperativo $contexto): array
    {
        $this->autorizacion->autorizarGestion($operador, $contexto);

        return $this->catalogoAgrupado();
    }

    /**
     * Misma agrupación por módulo sin autorizar la gestión de Roles: los
     * consumidores con otra capacidad (por ejemplo la matriz "ver como
     * usuario", HU-USR-06) autorizan antes de reutilizar esta consulta.
     *
     * @return array<string, list<Permission>>
     */
    public function catalogoAgrupado(): array
    {
        return Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permiso) => Str::before($permiso->name, '.'))
            ->map(fn ($grupo) => $grupo->values()->all())
            ->all();
    }
}
