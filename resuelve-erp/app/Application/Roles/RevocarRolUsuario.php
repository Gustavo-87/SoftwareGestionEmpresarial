<?php

namespace App\Application\Roles;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Revoca un Rol de un usuario en una Copropiedad (equipo Spatie), de forma
 * transaccional con su espejo legado y la auditoría. Protege la última
 * capacidad efectiva de administración (permiso `usuarios.gestionar`) de forma
 * segura ante concurrencia mediante el servicio CapacidadAdministrativa.
 */
final class RevocarRolUsuario
{
    public function __construct(
        private readonly AutorizacionGestionRoles $autorizacion,
        private readonly EspejoLegadoAsignaciones $espejo,
        private readonly CapacidadAdministrativa $capacidad,
    ) {}

    public function ejecutar(User $operador, ?ContextoOperativo $contexto, MembresiaCopropiedad $membresia, int $rolId): void
    {
        DB::transaction(function () use ($operador, $contexto, $membresia, $rolId): void {
            $this->autorizar($operador, $contexto, $membresia);

            $rol = SpatieRole::query()
                ->where('guard_name', AutorizacionGestionRoles::GUARD)
                ->findOrFail($rolId);

            if ($rol->copropiedad_id !== null && (int) $rol->copropiedad_id !== (int) $membresia->copropiedad_id) {
                throw new AuthorizationException('El Rol pertenece a otra Copropiedad.');
            }

            // Bloqueo de concurrencia: serializa revocaciones simultáneas y
            // observa el último estado confirmado (lecturas bloqueantes).
            $membresias = $this->capacidad->membresiasBloqueadas($membresia);

            setPermissionsTeamId((int) $membresia->copropiedad_id);
            $usuario = User::query()->findOrFail($membresia->usuario_id);
            $usuario->unsetRelation('roles')->unsetRelation('permissions');

            if (! $usuario->roles()->where('roles.id', $rol->id)->exists()) {
                throw ValidationException::withMessages([
                    'rol_id' => 'El usuario no tiene asignado ese rol en esta Copropiedad.',
                ]);
            }

            if ($this->capacidad->titularesRestantes($membresias, $membresia, $rol) === 0) {
                throw ValidationException::withMessages([
                    'rol' => 'La Copropiedad quedaría sin capacidad efectiva de administración (permiso usuarios.gestionar).',
                ]);
            }

            $usuario->removeRole($rol);
            $this->espejo->revocar($membresia, $rol);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'usuario.rol.revocar',
                'auditable_type' => User::class,
                'auditable_id' => $usuario->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'rol_id' => $rol->id,
                    'rol' => $rol->name,
                    'copropiedad_id' => $membresia->copropiedad_id,
                ],
            ]);
        });
    }

    private function autorizar(User $operador, ?ContextoOperativo $contexto, MembresiaCopropiedad $membresia): void
    {
        if ($this->autorizacion->esAutoridadPlataforma($operador)) {
            return;
        }

        if ($contexto === null || ! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar Usuarios.');
        }

        if ((int) $contexto->copropiedad->id !== (int) $membresia->copropiedad_id) {
            throw new AuthorizationException('La Membresía pertenece a otra Copropiedad.');
        }

        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'usuarios.gestionar')) {
            throw new AuthorizationException('No tienes permiso para gestionar Usuarios.');
        }
    }
}
