<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use App\Models\User;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CrearMembresia
{
    public function ejecutar(
        int $usuarioId,
        int $organizacionId,
        int $copropiedadId,
        string $vigenteDesde,
        ?string $vigenteHasta = null,
        ?int $creadaPor = null,
    ): MembresiaCopropiedad {
        return DB::transaction(function () use (
            $usuarioId,
            $organizacionId,
            $copropiedadId,
            $vigenteDesde,
            $vigenteHasta,
            $creadaPor,
        ) {
            $usuario = User::findOrFail($usuarioId);
            $organizacion = Organizacion::findOrFail($organizacionId);
            $copropiedad = Copropiedad::findOrFail($copropiedadId);

            if ($copropiedad->organizacion_id !== $organizacionId) {
                throw new InvalidArgumentException(
                    "La Copropiedad {$copropiedadId} no pertenece a la Organización {$organizacionId}."
                );
            }

            $existeActiva = MembresiaCopropiedad::query()
                ->where('usuario_id', $usuarioId)
                ->where('copropiedad_id', $copropiedadId)
                ->where('estado', 'activa')
                ->exists();

            if ($existeActiva) {
                throw new InvalidArgumentException(
                    "El Usuario {$usuarioId} ya tiene una membresía activa en la Copropiedad {$copropiedadId}."
                );
            }

            $fechaDesde = strtotime($vigenteDesde);
            if ($fechaDesde === false) {
                throw new InvalidArgumentException('La fecha vigente_desde no es válida.');
            }

            if ($vigenteHasta !== null) {
                $fechaHasta = strtotime($vigenteHasta);
                if ($fechaHasta === false) {
                    throw new InvalidArgumentException('La fecha vigente_hasta no es válida.');
                }
                if ($fechaHasta <= $fechaDesde) {
                    throw new InvalidArgumentException(
                        'La fecha vigente_hasta debe ser posterior a vigente_desde.'
                    );
                }
            }

            $membresia = MembresiaCopropiedad::create([
                'usuario_id' => $usuarioId,
                'organizacion_id' => $organizacionId,
                'copropiedad_id' => $copropiedadId,
                'estado' => 'activa',
                'vigente_desde' => $vigenteDesde,
                'vigente_hasta' => $vigenteHasta,
                'creada_por' => $creadaPor,
            ]);

            return $membresia;
        });
    }
}
