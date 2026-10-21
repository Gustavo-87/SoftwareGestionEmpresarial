<?php

namespace App\Application\Roles;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;

/**
 * Matriz global de permisos "ver como usuario" (HU-USR-06, Sprint 18).
 *
 * Reutiliza la agrupación por módulo del catálogo y expone, para cada permiso,
 * el origen de la capacidad efectiva: `rol activo`, `rol {nombre}` o `directo`.
 * Respeta la Membresía vigente del usuario consultado, la Copropiedad activa y
 * su Rol activo si está seleccionado. Solo lectura; requiere
 * `usuarios.gestionar` o autoridad de plataforma (403 en backend).
 */
final class ConsultaMatrizPermisosUsuario
{
    public function __construct(private readonly ConsultaPermisosCatalogo $catalogo) {}

    /**
     * @return array{
     *     membresiaVigente: bool,
     *     rolActivo: ?string,
     *     rolesAsignados: list<string>,
     *     modulos: array<string, list<array{clave:string,nombre:?string,descripcion:?string,estado:string,origenes:list<string>,efectivo:bool}>>
     * }
     */
    public function para(User $operador, ContextoOperativo $contexto, User $objetivo): array
    {
        $this->autorizar($operador, $contexto);

        setPermissionsTeamId((int) $contexto->copropiedad->id);
        $objetivo->unsetRelation('roles')->unsetRelation('permissions');

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->first();

        $rolesAsignados = $membresia === null ? collect() : $objetivo->roles()->with('permissions')->get();
        $rolActivo = null;
        if ($membresia !== null && $membresia->rol_activo_id !== null) {
            $rolActivo = $rolesAsignados->first(
                fn ($rolSpatie) => (int) $rolSpatie->id === (int) $membresia->rol_activo_id
            );
        }
        $directos = $membresia === null
            ? collect()
            : $objetivo->permissions()->get()->keyBy('name');

        $modulos = [];
        foreach ($this->catalogo->catalogoAgrupado() as $modulo => $permisos) {
            $filas = [];
            foreach ($permisos as $permiso) {
                $origenes = $this->origenes($permiso, $rolesAsignados, $rolActivo, $directos);
                // Con Rol activo seleccionado solo son efectivos el Rol activo y
                // los permisos directos (ADR-013, decisión 1).
                $efectivoPorOrigen = $rolActivo !== null
                    ? in_array('rol activo', $origenes, true) || in_array('directo', $origenes, true)
                    : $origenes !== [];
                $filas[] = [
                    'clave' => $permiso->name,
                    'nombre' => $permiso->nombre ?: $permiso->name,
                    'descripcion' => $permiso->descripcion,
                    'estado' => $permiso->estado ?? 'activo',
                    'origenes' => $origenes,
                    'efectivo' => $membresia !== null
                        && ($permiso->estado ?? 'activo') === 'activo'
                        && $efectivoPorOrigen,
                ];
            }
            $modulos[$modulo] = $filas;
        }

        return [
            'membresiaVigente' => $membresia !== null,
            'rolActivo' => $rolActivo?->name,
            'rolesAsignados' => $rolesAsignados->pluck('name')->values()->all(),
            'modulos' => $modulos,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Role>  $rolesAsignados
     * @param  \Illuminate\Support\Collection<string, \Spatie\Permission\Models\Permission>  $directos
     * @return list<string>
     */
    private function origenes(Permission $permiso, $rolesAsignados, ?object $rolActivo, $directos): array
    {
        $origenes = [];
        $activo = ($permiso->estado ?? 'activo') === 'activo';

        if ($activo && $rolActivo !== null
            && $rolActivo->permissions->contains(fn (Permission $p) => $p->name === $permiso->name)) {
            $origenes[] = 'rol activo';
        }

        foreach ($rolesAsignados as $rolSpatie) {
            if ($rolActivo !== null && (int) $rolSpatie->id === (int) $rolActivo->id) {
                continue;
            }
            if (! $activo) {
                continue;
            }
            if ($rolSpatie->permissions->contains(fn (Permission $p) => $p->name === $permiso->name)) {
                $origenes[] = 'rol '.$rolSpatie->name;
            }
        }

        if ($activo && $directos->has($permiso->name)) {
            $origenes[] = 'directo';
        }

        return array_values(array_unique($origenes));
    }

    private function autorizar(User $operador, ContextoOperativo $contexto): void
    {
        if ($operador->esAdministradorSistema()) {
            return;
        }

        if (! $contexto->tieneMembresiaContextual()
            || ! app(AutorizacionContextual::class)->tienePermiso($contexto, 'usuarios.gestionar')) {
            throw new AuthorizationException('No tienes permiso para consultar la matriz de permisos.');
        }
    }
}
