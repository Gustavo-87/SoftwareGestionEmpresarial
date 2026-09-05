<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AsignarRolMembresia
{
    public function ejecutar(
        MembresiaCopropiedad $membresia,
        int $rolId,
        string $vigenteDesde,
        ?string $vigenteHasta = null,
        ?int $asignadoPor = null,
    ): void {
        DB::transaction(function () use ($membresia, $rolId, $vigenteDesde, $vigenteHasta, $asignadoPor) {
            $rol = Rol::where('id', $rolId)
                ->where('ambito_aplicable', 'copropiedad')
                ->where('estado', 'activo')
                ->first();

            if ($rol === null) {
                throw new InvalidArgumentException(
                    "El Rol {$rolId} no existe, no es de ámbito copropiedad o no está activo."
                );
            }

            $yaAsignado = $membresia->roles()
                ->where('rol_id', $rolId)
                ->where('membresia_copropiedad_rol.estado', 'activa')
                ->exists();

            if ($yaAsignado) {
                throw new InvalidArgumentException(
                    "El Rol {$rolId} ya está asignado activamente a esta membresía."
                );
            }

            $tsDesde = strtotime($vigenteDesde);
            if ($tsDesde === false) {
                throw new InvalidArgumentException('La fecha vigente_desde no es válida.');
            }

            if ($vigenteHasta !== null) {
                $tsHasta = strtotime($vigenteHasta);
                if ($tsHasta === false) {
                    throw new InvalidArgumentException('La fecha vigente_hasta no es válida.');
                }
                if ($tsHasta <= $tsDesde) {
                    throw new InvalidArgumentException(
                        'La fecha vigente_hasta debe ser posterior a vigente_desde.'
                    );
                }
            }

            $membresia->roles()->attach($rolId, [
                'organizacion_id' => $membresia->organizacion_id,
                'copropiedad_id' => $membresia->copropiedad_id,
                'ambito_rol' => 'copropiedad',
                'estado' => 'activa',
                'vigente_desde' => $vigenteDesde,
                'vigente_hasta' => $vigenteHasta,
                'asignado_por' => $asignadoPor,
            ]);
        });
    }
}
