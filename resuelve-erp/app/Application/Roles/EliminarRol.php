<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

final class EliminarRol
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    /**
     * Eliminación segura (HU-USR-09): solo Roles sin asignaciones a usuarios
     * y nunca un Rol base del catálogo inicial. El conteo de asignaciones se
     * informa en el mensaje; la comparación con el catálogo inicial usa
     * normalización canónica consistente con la colación *_ci.
     */
    public function ejecutar(User $operador, ?ContextoOperativo $contexto, int $rolId): void
    {
        $rol = SpatieRole::query()->findOrFail($rolId);
        $this->autorizacion->autorizarGestion($operador, $contexto, $rol);

        if (CatalogoRolesBase::esBase($rol)) {
            throw ValidationException::withMessages([
                'rol' => 'El Rol "'.CatalogoRolesBase::normalizar($rol->name).'" es un Rol base del catálogo inicial y no puede eliminarse.',
            ]);
        }

        $asignaciones = DB::table('model_has_roles')
            ->where('role_id', $rol->id)
            ->distinct()
            ->count('model_id');

        if ($asignaciones > 0) {
            $usuarios = $asignaciones === 1 ? '1 usuario' : $asignaciones.' usuarios';
            throw ValidationException::withMessages([
                'rol' => "El Rol está asignado a {$usuarios} y no puede eliminarse.",
            ]);
        }

        $claves = $rol->permissions()->pluck('name')->all();
        $rol->delete();

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'rol.delete',
            'auditable_type' => SpatieRole::class,
            'auditable_id' => $rolId,
            'ip_address' => request()->ip(),
            'metadata' => ['nombre' => $rol->name, 'permisos' => $claves],
        ]);
    }
}
