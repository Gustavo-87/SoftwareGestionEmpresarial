<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EditarMembresia
{
    public function ejecutar(
        MembresiaCopropiedad $membresia,
        ?string $vigenteDesde = null,
        ?string $vigenteHasta = null,
    ): MembresiaCopropiedad {
        return DB::transaction(function () use ($membresia, $vigenteDesde, $vigenteHasta) {
            $fechaDesde = $vigenteDesde ?? $membresia->vigente_desde->format('Y-m-d');
            $fechaHasta = $vigenteHasta ?? $membresia->vigente_hasta?->format('Y-m-d');

            $tsDesde = strtotime($fechaDesde);
            if ($tsDesde === false) {
                throw new InvalidArgumentException('La fecha vigente_desde no es válida.');
            }

            if ($fechaHasta !== null) {
                $tsHasta = strtotime($fechaHasta);
                if ($tsHasta === false) {
                    throw new InvalidArgumentException('La fecha vigente_hasta no es válida.');
                }
                if ($tsHasta <= $tsDesde) {
                    throw new InvalidArgumentException(
                        'La fecha vigente_hasta debe ser posterior a vigente_desde.'
                    );
                }
            }

            $membresia->update([
                'vigente_desde' => $fechaDesde,
                'vigente_hasta' => $fechaHasta,
            ]);

            return $membresia->fresh();
        });
    }
}
