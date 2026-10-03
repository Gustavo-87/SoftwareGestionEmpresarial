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
 * Asigna un Rol a un usuario en una Copropiedad (equipo Spatie), de forma
 * transaccional con su espejo legado y la auditoría. Exige Membresía activa y
 * vigente, pertenencia al contexto y anti-escalada.
 */
final class AsignarRolUsuario
{
    public function __construct(
        private readonly AutorizacionGestionRoles $autorizacion,
        private readonly EspejoLegadoAsignaciones $espejo,
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

            if (! $this->vigente($membresia)) {
                throw ValidationException::withMessages([
                    'usuario' => 'La Membresía del usuario no está activa y vigente en esta Copropiedad.',
                ]);
            }

            $this->autorizacion->autorizarPermisosOtorgables(
                $operador,
                $contexto,
                $rol->permissions()->pluck('name')->all(),
            );

            setPermissionsTeamId((int) $membresia->copropiedad_id);
            $usuario = User::query()->findOrFail($membresia->usuario_id);
            $usuario->unsetRelation('roles')->unsetRelation('permissions');

            if ($usuario->roles()->where('roles.id', $rol->id)->exists()) {
                throw ValidationException::withMessages([
                    'rol_id' => 'El usuario ya tiene asignado ese rol en esta Copropiedad.',
                ]);
            }

            $usuario->assignRole($rol);
            $this->espejo->asignar($membresia, $rol, $operador->id);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'usuario.rol.asignar',
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

    private function vigente(MembresiaCopropiedad $membresia): bool
    {
        return $membresia->estado === 'activa'
            && $membresia->vigente_desde !== null
            && $membresia->vigente_desde->lessThanOrEqualTo(now())
            && ($membresia->vigente_hasta === null || $membresia->vigente_hasta->isFuture());
    }
}
