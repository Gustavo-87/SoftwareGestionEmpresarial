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

        $datos = Validator::make(['permisos' => $permisos], [
            'permisos' => 'present|array',
            'permisos.*' => 'string',
        ])->validate();
        $claves = array_values(array_unique($datos['permisos']));

        $conocidas = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->whereIn('name', $claves)
            ->pluck('name')
            ->all();
        $desconocidas = array_values(array_diff($claves, $conocidas));
        if ($desconocidas !== []) {
            throw ValidationException::withMessages(['permisos' => 'Permisos desconocidos: '.implode(', ', $desconocidas).'.']);
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
