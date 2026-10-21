<?php

namespace App\Application\Roles;

use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol activo por Membresía/Copropiedad (HU-USR-04, Sprint 18; ADR-013).
 *
 * El usuario elige cuál de sus Roles asignados concentra sus funciones: con
 * selección explícita los permisos efectivos son los del Rol activo más sus
 * permisos directos; sin selección se conserva la unión de Roles. La selección
 * se valida contra los Roles realmente asignados en la Copropiedad activa y se
 * revalida al resolver el contexto.
 */
final class GestionarRolActivo
{
    /**
     * Selecciona el Rol activo del propio usuario en una Membresía.
     * `null` limpia la selección y restaura la unión de Roles.
     */
    public function ejecutar(User $usuario, MembresiaCopropiedad $membresia, ?int $rolId): void
    {
        DB::transaction(function () use ($usuario, $membresia, $rolId): void {
            $this->autorizar($usuario, $membresia);

            if ($rolId === null) {
                $membresia->forceFill(['rol_activo_id' => null])->save();

                AuditLog::create([
                    'user_id' => $usuario->id,
                    'action' => 'usuario.rol_activo.seleccionar',
                    'auditable_type' => MembresiaCopropiedad::class,
                    'auditable_id' => $membresia->id,
                    'ip_address' => request()->ip(),
                    'metadata' => [
                        'rol_id' => null,
                        'rol' => null,
                        'copropiedad_id' => $membresia->copropiedad_id,
                    ],
                ]);

                return;
            }

            setPermissionsTeamId((int) $membresia->copropiedad_id);
            $asignado = $usuario->roles()->where('roles.id', $rolId)->first();
            if ($asignado === null) {
                throw ValidationException::withMessages([
                    'rol_activo_id' => 'El Rol seleccionado no está asignado al usuario en esta Copropiedad.',
                ]);
            }

            $membresia->forceFill(['rol_activo_id' => $asignado->id])->save();

            AuditLog::create([
                'user_id' => $usuario->id,
                'action' => 'usuario.rol_activo.seleccionar',
                'auditable_type' => MembresiaCopropiedad::class,
                'auditable_id' => $membresia->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'rol_id' => $asignado->id,
                    'rol' => $asignado->name,
                    'copropiedad_id' => $membresia->copropiedad_id,
                ],
            ]);
        });
    }

    /** @return list<SpatieRole> Roles asignados al usuario en la Copropiedad. */
    public function rolesAsignados(User $usuario, MembresiaCopropiedad $membresia): array
    {
        setPermissionsTeamId((int) $membresia->copropiedad_id);
        $usuario->unsetRelation('roles');

        return $usuario->roles()->get()->values()->all();
    }

    private function autorizar(User $usuario, MembresiaCopropiedad $membresia): void
    {
        // Segregación de funciones del propio usuario: solo sobre su Membresía
        // vigente en la Copropiedad indicada. La autoridad de gestión de
        // Usuarios no fija el Rol activo ajeno (ADR-013, decisión 1).
        if ((int) $membresia->usuario_id !== (int) $usuario->id) {
            throw new AuthorizationException('Solo puedes seleccionar tu propio Rol activo.');
        }

        if (! $this->vigente($membresia)) {
            throw new AuthorizationException('Se requiere una Membresía vigente para seleccionar el Rol activo.');
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
