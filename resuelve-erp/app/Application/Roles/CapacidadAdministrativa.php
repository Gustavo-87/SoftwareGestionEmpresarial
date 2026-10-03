<?php

namespace App\Application\Roles;

use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Capacidad efectiva de administración de una Copropiedad: miembros vigentes
 * con el permiso `usuarios.gestionar`. Nunca evalúa nombres de rol.
 *
 * Debe ejecutarse con las Membresías de la Copropiedad ya bloqueadas
 * (`lockForUpdate`) y usa lecturas bloqueantes sobre las asignaciones para
 * observar el último estado confirmado incluso con REPEATABLE READ.
 */
final class CapacidadAdministrativa
{
    public const PERMISO = 'usuarios.gestionar';

    /**
     * Miembros vigentes que conservarían la capacidad tras retirar un Rol de
     * la Membresía objetivo y/o excluir por completo un Usuario.
     *
     * @param  Collection<int, MembresiaCopropiedad>  $membresias
     */
    public function titularesRestantes(
        Collection $membresias,
        MembresiaCopropiedad $objetivo,
        ?SpatieRole $rolRetirado = null,
        ?int $usuarioExcluido = null,
    ): int {
        setPermissionsTeamId((int) $objetivo->copropiedad_id);

        $capacidades = 0;
        foreach ($membresias as $membresia) {
            if ($usuarioExcluido !== null && (int) $membresia->usuario_id === $usuarioExcluido) {
                continue;
            }
            if (! $this->vigente($membresia)) {
                continue;
            }

            $usuario = User::query()->find($membresia->usuario_id);
            if ($usuario === null) {
                continue;
            }
            $usuario->unsetRelation('roles')->unsetRelation('permissions');
            $roles = $usuario->roles()->with('permissions')->lockForUpdate()->get();
            if ($rolRetirado !== null && $membresia->id === $objetivo->id) {
                $roles = $roles->reject(fn (SpatieRole $rol) => (int) $rol->id === (int) $rolRetirado->id);
            }
            if ($roles->contains(fn (SpatieRole $rol) => $rol->permissions->contains('name', self::PERMISO))) {
                $capacidades++;
            }
        }

        return $capacidades;
    }

    /**
     * Membresías de la Copropiedad bloqueadas para serializar decisiones
     * concurrentes sobre la capacidad administrativa.
     *
     * @return Collection<int, MembresiaCopropiedad>
     */
    public function membresiasBloqueadas(MembresiaCopropiedad $objetivo): Collection
    {
        return MembresiaCopropiedad::query()
            ->where('organizacion_id', $objetivo->organizacion_id)
            ->where('copropiedad_id', $objetivo->copropiedad_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function vigente(MembresiaCopropiedad $membresia): bool
    {
        return $membresia->estado === 'activa'
            && $membresia->vigente_desde !== null
            && ! $membresia->vigente_desde->isFuture()
            && ($membresia->vigente_hasta === null || $membresia->vigente_hasta->isFuture());
    }
}
