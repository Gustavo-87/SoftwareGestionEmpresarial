<?php

namespace App\Application\Autorizacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Diagnóstico de equivalencia entre el RBAC contextual legado
 * (roles_contextuales, permisos_contextuales, rol_permiso_contextual,
 * membresia_copropiedad_rol) y el RBAC de Spatie (roles, permissions,
 * role_has_permissions, model_has_roles).
 *
 * Los permisos nuevos de Spatie se excluyen de la comparación estricta de la
 * matriz porque no existen en el sistema anterior.
 */
final class VerificadorEquivalenciaRbacSpatie
{
    public const PERMISOS_NUEVOS = ['pqrs.gestionar_asignadas', 'pqrs.ver_borradores', 'roles.gestionar'];

    private const GUARD = 'web';

    /** @return list<string> */
    public function divergencias(): array
    {
        $divergencias = [];
        $clavesLegado = DB::table('permisos_contextuales')->orderBy('clave')->pluck('clave')->all();
        $rolesLegado = DB::table('roles_contextuales')
            ->where('ambito_aplicable', 'copropiedad')
            ->orderBy('clave')
            ->get();

        // 1. Permisos legados faltantes en Spatie.
        $permisosSpatie = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();
        foreach ($clavesLegado as $clave) {
            if (! in_array($clave, $permisosSpatie, true)) {
                $divergencias[] = "Falta el permiso de Spatie {$clave}.";
            }
        }

        // 2. Roles legados faltantes en Spatie.
        $rolesSpatie = Role::query()->where('guard_name', self::GUARD)->pluck('name')->all();
        foreach ($rolesLegado as $rol) {
            if (! in_array($rol->clave, $rolesSpatie, true)) {
                $divergencias[] = "Falta el rol de Spatie {$rol->clave}.";
            }
        }

        // 3. Matriz rol-permiso (solo claves heredadas; los permisos nuevos se excluyen).
        foreach ($rolesLegado as $rol) {
            $matrizLegado = DB::table('rol_permiso_contextual')
                ->join('permisos_contextuales', 'permisos_contextuales.id', '=', 'rol_permiso_contextual.permiso_id')
                ->where('rol_permiso_contextual.rol_id', $rol->id)
                ->pluck('permisos_contextuales.clave')
                ->all();
            sort($matrizLegado);

            $rolSpatie = Role::query()->where('guard_name', self::GUARD)->where('name', $rol->clave)->first();
            if ($rolSpatie === null) {
                continue; // ya reportado como rol faltante
            }
            $matrizSpatie = $rolSpatie->permissions()->pluck('name')->all();
            $matrizSpatie = array_values(array_intersect($matrizSpatie, $clavesLegado));
            sort($matrizSpatie);

            if ($matrizLegado !== $matrizSpatie) {
                $divergencias[] = "La matriz rol-permiso de {$rol->clave} difiere del RBAC legado.";
            }
        }

        // 4 y 5. Asignaciones: faltantes e incorrectas entre Copropiedades.
        $legado = $this->asignacionesLegado();
        $spatie = $this->asignacionesSpatie();

        foreach (array_diff($legado, $spatie) as $clave) {
            $divergencias[] = "Falta la asignación de Spatie ({$clave}).";
        }
        foreach (array_diff($spatie, $legado) as $clave) {
            $divergencias[] = "La asignación de Spatie ({$clave}) no corresponde a una asignación legado activa en esa Copropiedad.";
        }

        return $divergencias;
    }

    /** @return list<string> "usuario_id|copropiedad_id|rol" de asignaciones legado activas y vigentes. */
    private function asignacionesLegado(): array
    {
        return DB::table('membresia_copropiedad_rol')
            ->join('membresias_copropiedad', 'membresias_copropiedad.id', '=', 'membresia_copropiedad_rol.membresia_copropiedad_id')
            ->join('roles_contextuales', 'roles_contextuales.id', '=', 'membresia_copropiedad_rol.rol_id')
            ->where('membresia_copropiedad_rol.estado', 'activa')
            ->where('membresia_copropiedad_rol.vigente_desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('membresia_copropiedad_rol.vigente_hasta')
                ->orWhere('membresia_copropiedad_rol.vigente_hasta', '>', now()))
            ->where('membresias_copropiedad.estado', 'activa')
            ->where('membresias_copropiedad.vigente_desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('membresias_copropiedad.vigente_hasta')
                ->orWhere('membresias_copropiedad.vigente_hasta', '>', now()))
            ->where('membresia_copropiedad_rol.ambito_rol', 'copropiedad')
            ->orderBy('membresias_copropiedad.usuario_id')
            ->orderBy('membresia_copropiedad_rol.copropiedad_id')
            ->orderBy('roles_contextuales.clave')
            ->get()
            ->map(fn (object $fila) => "{$fila->usuario_id}|{$fila->copropiedad_id}|{$fila->clave}")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Asignaciones de Spatie comparables con el espejo: solo las de roles con
     * contraparte en `roles_contextuales`. Los roles personalizados de Spatie
     * no tienen espejo y no generan divergencia; las diferencias en los roles
     * equivalentes sí se reportan.
     *
     * @return list<string> "usuario_id|copropiedad_id|rol" de asignaciones de Spatie.
     */
    private function asignacionesSpatie(): array
    {
        $nombresLegado = DB::table('roles_contextuales')
            ->where('ambito_aplicable', 'copropiedad')
            ->pluck('clave')
            ->all();

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', $nombresLegado)
            ->orderBy('model_has_roles.model_id')
            ->orderBy('model_has_roles.copropiedad_id')
            ->orderBy('roles.name')
            ->get([
                'model_has_roles.model_id',
                'model_has_roles.copropiedad_id',
                'roles.name',
            ])
            ->map(fn (object $fila) => "{$fila->model_id}|{$fila->copropiedad_id}|{$fila->name}")
            ->unique()
            ->values()
            ->all();
    }
}
