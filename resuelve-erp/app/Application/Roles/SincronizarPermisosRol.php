<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

final class SincronizarPermisosRol
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    /**
     * Sincroniza la matriz rol-permiso completa. El operador solo puede
     * conceder permisos que posee (anti-escalada).
     *
     * @param  list<string>  $permisos
     */
    public function ejecutar(User $operador, ?ContextoOperativo $contexto, int $rolId, array $permisos): void
    {
        $rol = SpatieRole::query()->findOrFail($rolId);
        $this->autorizacion->autorizarGestion($operador, $contexto, $rol);

        // HU-USR-09: el Rol base admin es inmutable (matriz no editable vía
        // web), para cualquier autoridad. Comparación con normalización
        // canónica consistente con la colación *_ci.
        if (CatalogoRolesBase::esAdminBase($rol)) {
            throw ValidationException::withMessages([
                'permisos' => 'El Rol admin base del catálogo inicial es inmutable: su matriz de permisos no puede editarse.',
            ]);
        }

        $datos = Validator::make(['permisos' => $permisos], [
            'permisos' => 'present|array',
            'permisos.*' => 'string',
        ])->validate();
        // Normalización canónica de claves: recorte + unicidad estricta, para
        // que el resultado no dependa de la sensibilidad a mayúsculas de la
        // colación de la base (anti-escalada verificada y endurecida).
        $claves = array_values(array_unique(array_map(
            static fn (string $clave) => trim($clave),
            $datos['permisos'],
        )));

        $conocidas = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->whereIn('name', $claves)
            ->pluck('name')
            ->all();
        $desconocidas = array_values(array_diff($claves, $conocidas));
        if ($desconocidas !== []) {
            throw ValidationException::withMessages(['permisos' => 'Permisos desconocidos: '.implode(', ', $desconocidas).'.']);
        }

        // HU-USR-07: un permiso desactivado no admite nuevas asignaciones.
        $inactivas = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->whereIn('name', $claves)
            ->where('estado', '!=', GestionarCatalogoPermisos::ESTADO_ACTIVO)
            ->pluck('name')
            ->all();
        if ($inactivas !== []) {
            throw ValidationException::withMessages([
                'permisos' => 'Permisos desactivados que no admiten nuevas asignaciones: '.implode(', ', $inactivas).'.',
            ]);
        }

        $this->autorizacion->autorizarPermisosOtorgables($operador, $contexto, $claves);

        $actuales = $rol->permissions()->pluck('name')->all();
        $rol->syncPermissions($claves);

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'rol.permissions.sync',
            'auditable_type' => SpatieRole::class,
            'auditable_id' => $rol->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'nombre' => $rol->name,
                'agregados' => array_values(array_diff($claves, $actuales)),
                'quitados' => array_values(array_diff($actuales, $claves)),
            ],
        ]);
    }
}
