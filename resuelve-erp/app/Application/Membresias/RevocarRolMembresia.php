<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RevocarRolMembresia
{
    public function ejecutar(
        MembresiaCopropiedad $membresia,
        int $rolId,
    ): void {
        DB::transaction(function () use ($membresia, $rolId) {
            $rolAsignado = $membresia->roles()
                ->where('rol_id', $rolId)
                ->where('membresia_copropiedad_rol.estado', 'activa')
                ->first();

            if ($rolAsignado === null) {
                throw new InvalidArgumentException(
                    "El Rol {$rolId} no está asignado activamente a esta membresía."
                );
            }

            $membresia->roles()->detach($rolId);
        });
    }
}
