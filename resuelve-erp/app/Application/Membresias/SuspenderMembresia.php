<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SuspenderMembresia
{
    public function ejecutar(
        MembresiaCopropiedad $membresia,
        string $motivo,
    ): MembresiaCopropiedad {
        return DB::transaction(function () use ($membresia, $motivo) {
            if ($membresia->estado !== 'activa') {
                throw new InvalidArgumentException(
                    'Solo se pueden suspender membresías con estado activa.'
                );
            }

            if (empty($motivo)) {
                throw new InvalidArgumentException(
                    'El motivo de suspensión es obligatorio.'
                );
            }

            $membresia->update([
                'estado' => 'suspendida',
                'vigente_hasta' => now(),
                'motivo_terminacion' => $motivo,
            ]);

            return $membresia->fresh();
        });
    }
}
