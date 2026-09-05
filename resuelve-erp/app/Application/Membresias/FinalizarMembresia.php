<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FinalizarMembresia
{
    public function ejecutar(
        MembresiaCopropiedad $membresia,
        string $motivo,
    ): MembresiaCopropiedad {
        return DB::transaction(function () use ($membresia, $motivo) {
            if (! in_array($membresia->estado, ['activa', 'suspendida'], true)) {
                throw new InvalidArgumentException(
                    'Solo se pueden finalizar membresías con estado activa o suspendida.'
                );
            }

            if (empty($motivo)) {
                throw new InvalidArgumentException(
                    'El motivo de finalización es obligatorio.'
                );
            }

            $membresia->update([
                'estado' => 'finalizada',
                'vigente_hasta' => now(),
                'motivo_terminacion' => $motivo,
            ]);

            return $membresia->fresh();
        });
    }
}
