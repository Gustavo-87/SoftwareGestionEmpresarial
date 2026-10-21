<?php

namespace App\Application\Autorizacion;

use App\Application\Roles\CatalogoPermisosBase;
use App\Application\Roles\CatalogoRolesBase;
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
 * Contrato de verificación aprobado (Sprint 18): normalización canónica de
 * claves (consistente con las colaciones `*_ci` de MySQL), tolerancia
 * multi-rol (las asignaciones se comparan como conjuntos) y clasificación por
 * severidad: `error` (pérdida o contradicción efectiva) y `aviso` (deriva
 * explicada sin pérdida de acceso).
 *
 * Los permisos nuevos de Spatie se excluyen de la comparación estricta de la
 * matriz porque no existen en el sistema anterior.
 */
final class VerificadorEquivalenciaRbacSpatie
{
    public const SEVERIDAD_ERROR = 'error';
    public const SEVERIDAD_AVISO = 'aviso';

    public const PERMISOS_NUEVOS = ['pqrs.gestionar_asignadas', 'pqrs.ver_borradores', 'roles.gestionar'];

    private const GUARD = 'web';

    /** @return list<string> */
    public function divergencias(): array
    {
        return array_values(array_column($this->divergenciasClasificadas(), 'mensaje'));
    }

    /**
     * Divergencias clasificadas por severidad según el contrato aprobado.
     *
     * @return list<array{mensaje:string,severidad:string}>
     */
    public function divergenciasClasificadas(): array
    {
        $divergencias = [];
        $clavesLegado = DB::table('permisos_contextuales')->orderBy('clave')->pluck('clave')->all();
        $clavesLegadoCanonico = array_map([CatalogoPermisosBase::class, 'normalizar'], $clavesLegado);
        $rolesLegado = DB::table('roles_contextuales')
            ->where('ambito_aplicable', 'copropiedad')
            ->orderBy('clave')
            ->get();

        // 1. Permisos legados faltantes en Spatie (normalización canónica).
        $permisosSpatieCanonico = array_map(
            [CatalogoPermisosBase::class, 'normalizar'],
            Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all(),
        );
        foreach ($clavesLegado as $clave) {
            if (! in_array(CatalogoPermisosBase::normalizar($clave), $permisosSpatieCanonico, true)) {
                $divergencias[] = [
                    'mensaje' => "Falta el permiso de Spatie {$clave}.",
                    'severidad' => self::SEVERIDAD_ERROR,
                ];
            }
        }

        // 2. Roles legados faltantes en Spatie (normalización canónica).
        $rolesSpatieCanonico = array_map(
            [CatalogoRolesBase::class, 'normalizar'],
            Role::query()->where('guard_name', self::GUARD)->pluck('name')->all(),
        );
        foreach ($rolesLegado as $rol) {
            if (! in_array(CatalogoRolesBase::normalizar($rol->clave), $rolesSpatieCanonico, true)) {
                $divergencias[] = [
                    'mensaje' => "Falta el rol de Spatie {$rol->clave}.",
                    'severidad' => self::SEVERIDAD_ERROR,
                ];
            }
        }

        // 3. Matriz rol-permiso (solo claves heredadas; los permisos nuevos se
        // excluyen). La severidad distingue pérdida efectiva (faltan permisos
        // heredados) de la deriva explicada por ampliaciones de la matriz.
        foreach ($rolesLegado as $rol) {
            $matrizLegado = array_map(
                [CatalogoPermisosBase::class, 'normalizar'],
                DB::table('rol_permiso_contextual')
                    ->join('permisos_contextuales', 'permisos_contextuales.id', '=', 'rol_permiso_contextual.permiso_id')
                    ->where('rol_permiso_contextual.rol_id', $rol->id)
                    ->pluck('permisos_contextuales.clave')
                    ->all(),
            );
            sort($matrizLegado);

            $rolSpatie = Role::query()
                ->where('guard_name', self::GUARD)
                ->get()
                ->first(fn (Role $candidato) => CatalogoRolesBase::normalizar($candidato->name) === CatalogoRolesBase::normalizar($rol->clave));
            if ($rolSpatie === null) {
                continue; // ya reportado como rol faltante
            }
            $matrizSpatie = array_map(
                [CatalogoPermisosBase::class, 'normalizar'],
                $rolSpatie->permissions()->pluck('name')->all(),
            );
            $matrizSpatie = array_values(array_intersect($matrizSpatie, $clavesLegadoCanonico));
            sort($matrizSpatie);

            $faltan = array_values(array_diff($matrizLegado, $matrizSpatie));
            $adicionales = array_values(array_diff($matrizSpatie, $matrizLegado));
            if ($faltan !== [] || $adicionales !== []) {
                $divergencias[] = [
                    'mensaje' => "La matriz rol-permiso de {$rol->clave} difiere del RBAC legado.",
                    'severidad' => $faltan !== [] ? self::SEVERIDAD_ERROR : self::SEVERIDAD_AVISO,
                ];
            }
        }

        // 4 y 5. Asignaciones: faltantes e incorrectas entre Copropiedades.
        $legado = $this->asignacionesLegado();
        $spatie = $this->asignacionesSpatie();

        foreach (array_diff($legado, $spatie) as $clave) {
            $divergencias[] = [
                'mensaje' => "Falta la asignación de Spatie ({$clave}).",
                'severidad' => self::SEVERIDAD_ERROR,
            ];
        }
        foreach (array_diff($spatie, $legado) as $clave) {
            $divergencias[] = [
                'mensaje' => "La asignación de Spatie ({$clave}) no corresponde a una asignación legado activa en esa Copropiedad.",
                'severidad' => self::SEVERIDAD_AVISO,
            ];
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
            ->map(fn (object $fila) => "{$fila->usuario_id}|{$fila->copropiedad_id}|".CatalogoRolesBase::normalizar((string) $fila->clave))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Asignaciones de Spatie comparables con el espejo: solo las de roles con
     * contraparte en `roles_contextuales`. Los roles personalizados de Spatie
     * no tienen espejo y no generan divergencia; las diferencias en los roles
     * equivalentes sí se reportan. La comparación de nombres es canónica.
     *
     * @return list<string> "usuario_id|copropiedad_id|rol" de asignaciones de Spatie.
     */
    private function asignacionesSpatie(): array
    {
        $nombresLegadoCanonico = array_map(
            [CatalogoRolesBase::class, 'normalizar'],
            DB::table('roles_contextuales')
                ->where('ambito_aplicable', 'copropiedad')
                ->pluck('clave')
                ->all(),
        );

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->orderBy('model_has_roles.model_id')
            ->orderBy('model_has_roles.copropiedad_id')
            ->orderBy('roles.name')
            ->get([
                'model_has_roles.model_id',
                'model_has_roles.copropiedad_id',
                'roles.name',
            ])
            ->filter(fn (object $fila) => in_array(CatalogoRolesBase::normalizar((string) $fila->name), $nombresLegadoCanonico, true))
            ->map(fn (object $fila) => "{$fila->model_id}|{$fila->copropiedad_id}|".CatalogoRolesBase::normalizar((string) $fila->name))
            ->unique()
            ->values()
            ->all();
    }
}
