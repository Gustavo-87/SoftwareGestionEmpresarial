<?php

namespace App\Application\Roles;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Permission;

/**
 * Permisos directos por usuario (HU-USR-05, Sprint 18; ADR-013).
 *
 * Se administran sobre `model_has_permissions` con `team` igual a la
 * Copropiedad activa. Aplica la misma anti-escalada que los Roles (ningún
 * operador concede permisos que no posee; la autoridad de plataforma queda
 * exenta), audita cada mutación y protege la capacidad administrativa: el
 * permiso `usuarios.gestionar` no puede revocarse si deja a la Copropiedad sin
 * ningún miembro vigente que lo conserve. Un permiso desactivado no admite
 * nuevas asignaciones (HU-USR-07).
 */
final class GestionarPermisosDirectos
{
    public function __construct(
        private readonly AutorizacionGestionRoles $autorizacion,
        private readonly CapacidadAdministrativa $capacidad,
    ) {}

    public function asignar(User $operador, ?ContextoOperativo $contexto, MembresiaCopropiedad $membresia, string $clave): void
    {
        DB::transaction(function () use ($operador, $contexto, $membresia, $clave): void {
            $this->autorizar($operador, $contexto, $membresia);

            $permiso = $this->permisoActivo($clave);

            $this->autorizacion->autorizarPermisosOtorgables($operador, $contexto, [$permiso->name]);

            $usuario = $this->usuarioDeLaMembresia($membresia);
            if ($usuario->permissions()->where('permissions.id', $permiso->id)->exists()) {
                throw ValidationException::withMessages([
                    'permiso' => 'El usuario ya tiene ese permiso directo en esta Copropiedad.',
                ]);
            }

            $usuario->givePermissionTo($permiso);

            $this->auditar($operador, 'usuario.permiso_directo.asignar', $usuario, $membresia, $permiso);
        });
    }

    public function revocar(User $operador, ?ContextoOperativo $contexto, MembresiaCopropiedad $membresia, string $clave): void
    {
        DB::transaction(function () use ($operador, $contexto, $membresia, $clave): void {
            $this->autorizar($operador, $contexto, $membresia);

            $permiso = Permission::query()
                ->where('guard_name', AutorizacionGestionRoles::GUARD)
                ->where('name', $clave)
                ->first();

            if ($permiso === null) {
                throw ValidationException::withMessages([
                    'permiso' => 'El permiso indicado no existe en el catálogo.',
                ]);
            }

            $usuario = $this->usuarioDeLaMembresia($membresia);
            if (! $usuario->permissions()->where('permissions.id', $permiso->id)->exists()) {
                throw ValidationException::withMessages([
                    'permiso' => 'El usuario no tiene ese permiso directo en esta Copropiedad.',
                ]);
            }

            if ($permiso->name === CapacidadAdministrativa::PERMISO) {
                $membresias = $this->capacidad->membresiasBloqueadas($membresia);
                if ($this->capacidad->titularesRestantes($membresias, $membresia, null, null, CapacidadAdministrativa::PERMISO) < 1) {
                    throw new RuntimeException(
                        'No se puede revocar: es el único permiso directo que conserva la capacidad de administración '
                        .'(permiso usuarios.gestionar) en esta Copropiedad.'
                    );
                }
            }

            $usuario->revokePermissionTo($permiso);

            $this->auditar($operador, 'usuario.permiso_directo.revocar', $usuario, $membresia, $permiso);
        });
    }

    /** Permisos directos vigentes en la Copropiedad (equipo = Copropiedad activa). */
    public function directos(MembresiaCopropiedad $membresia): Collection
    {
        $usuario = User::query()->findOrFail($membresia->usuario_id);
        setPermissionsTeamId((int) $membresia->copropiedad_id);
        $usuario->unsetRelation('permissions');

        return $usuario->permissions()->orderBy('name')->get();
    }

    private function permisoActivo(string $clave): Permission
    {
        $permiso = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->where('name', trim($clave))
            ->first();

        if ($permiso === null) {
            throw ValidationException::withMessages([
                'permiso' => 'El permiso indicado no existe en el catálogo.',
            ]);
        }

        if (($permiso->estado ?? 'activo') !== 'activo') {
            throw ValidationException::withMessages([
                'permiso' => 'El permiso está desactivado y no admite nuevas asignaciones.',
            ]);
        }

        return $permiso;
    }

    private function usuarioDeLaMembresia(MembresiaCopropiedad $membresia): User
    {
        setPermissionsTeamId((int) $membresia->copropiedad_id);
        $usuario = User::query()->findOrFail($membresia->usuario_id);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');

        return $usuario;
    }

    private function autorizar(User $operador, ?ContextoOperativo $contexto, MembresiaCopropiedad $membresia): void
    {
        if ($this->autorizacion->esAutoridadPlataforma($operador)) {
            return;
        }

        if ($contexto === null || ! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar permisos directos.');
        }

        if ((int) $contexto->copropiedad->id !== (int) $membresia->copropiedad_id) {
            throw new AuthorizationException('La Membresía pertenece a otra Copropiedad.');
        }

        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'usuarios.gestionar')) {
            throw new AuthorizationException('No tienes permiso para gestionar Usuarios.');
        }
    }

    private function auditar(User $operador, string $accion, User $usuario, MembresiaCopropiedad $membresia, Permission $permiso): void
    {
        AuditLog::create([
            'user_id' => $operador->id,
            'action' => $accion,
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'permiso' => $permiso->name,
                'copropiedad_id' => $membresia->copropiedad_id,
            ],
        ]);
    }
}
