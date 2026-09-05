<?php

namespace App\Application\Organizaciones;

use App\Models\Organizacion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DesactivarOrganizacion
{
    public function ejecutar(Organizacion $organizacion, string $motivo): void
    {
        if (blank($motivo)) {
            throw new InvalidArgumentException('El motivo de desactivación es obligatorio.');
        }

        if ($organizacion->estado === 'inactiva') {
            throw new InvalidArgumentException('La organización ya se encuentra desactivada.');
        }

        $copropiedadesActivas = $organizacion->copropiedades()
            ->where('estado', 'activa')
            ->count();

        if ($copropiedadesActivas > 0) {
            throw new InvalidArgumentException(
                "No se puede desactivar la organización porque tiene {$copropiedadesActivas} copropiedad(es) activa(s). Desactivarlas primero."
            );
        }

        $organizacion->update([
            'estado' => 'inactiva',
            'desactivada_at' => now(),
        ]);
    }

    public function reactivar(Organizacion $organizacion): void
    {
        if ($organizacion->estado === 'activa') {
            throw new InvalidArgumentException('La organización ya se encuentra activa.');
        }

        $organizacion->update([
            'estado' => 'activa',
            'desactivada_at' => null,
        ]);
    }
}
