<?php

namespace App\Application\Copropiedades;

use App\Models\Copropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DesactivarCopropiedad
{
    public function ejecutar(Copropiedad $copropiedad, string $motivo): void
    {
        if (blank($motivo)) {
            throw new InvalidArgumentException('El motivo de desactivación es obligatorio.');
        }

        if ($copropiedad->estado === 'inactiva') {
            throw new InvalidArgumentException('La copropiedad ya se encuentra desactivada.');
        }

        if ($copropiedad->organizacion->estado !== 'activa') {
            throw new InvalidArgumentException(
                "No se puede desactivar la copropiedad porque la organización padre está desactivada."
            );
        }

        $membresiasActivas = $copropiedad->membresiasCopropiedad()
            ->where('estado', 'activa')
            ->count();

        if ($membresiasActivas > 0) {
            throw new InvalidArgumentException(
                "No se puede desactivar la copropiedad porque tiene {$membresiasActivas} membresía(s) activa(s). Desactivarlas primero."
            );
        }

        $copropiedad->update([
            'estado' => 'inactiva',
            'desactivada_at' => now(),
        ]);
    }

    public function reactivar(Copropiedad $copropiedad): void
    {
        if ($copropiedad->estado === 'activa') {
            throw new InvalidArgumentException('La copropiedad ya se encuentra activa.');
        }

        $copropiedad->update([
            'estado' => 'activa',
            'desactivada_at' => null,
        ]);
    }
}
