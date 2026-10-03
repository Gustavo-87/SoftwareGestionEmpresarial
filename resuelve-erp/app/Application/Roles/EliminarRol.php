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

    /** Eliminación segura: solo Roles sin asignaciones a usuarios. */
    public function ejecutar(User $operador, ?ContextoOperativo $contexto, int $rolId): void
    {
        $rol = SpatieRole::query()->findOrFail($rolId);
        $this->autorizacion->autorizarGestion($operador, $contexto, $rol);

        $enUso = DB::table('model_has_roles')
            ->where('role_id', $rol->id)
            ->exists();

        if ($enUso) {
            throw ValidationException::withMessages([
                'rol' => 'El Rol está asignado a usuarios y no puede eliminarse.',
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
