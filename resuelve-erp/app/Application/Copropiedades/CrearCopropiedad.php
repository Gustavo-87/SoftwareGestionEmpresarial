<?php

namespace App\Application\Copropiedades;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CrearCopropiedad
{
    public function ejecutar(
        int $organizacionId,
        string $nombre,
        ?string $nit = null,
        ?string $representanteLegal = null,
        ?string $direccion = null,
        ?string $ciudad = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $creadaPor = null,
    ): Copropiedad {
        return DB::transaction(function () use (
            $organizacionId,
            $nombre,
            $nit,
            $representanteLegal,
            $direccion,
            $ciudad,
            $telefono,
            $email,
            $creadaPor,
        ) {
            $organizacion = Organizacion::findOrFail($organizacionId);

            if ($organizacion->estado !== 'activa') {
                throw new InvalidArgumentException(
                    "No se puede crear la copropiedad: la organización '{$organizacion->nombre}' se encuentra desactivada."
                );
            }

            if (blank($nombre)) {
                throw new InvalidArgumentException('El nombre de la copropiedad es obligatorio.');
            }

            $existe = Copropiedad::query()
                ->where('organizacion_id', $organizacionId)
                ->where('nombre', $nombre)
                ->exists();

            if ($existe) {
                throw new InvalidArgumentException(
                    "Ya existe una copropiedad con el nombre '{$nombre}' en esta organización."
                );
            }

            if ($nit !== null) {
                $existeNIT = Copropiedad::query()
                    ->where('organizacion_id', $organizacionId)
                    ->where('nit', $nit)
                    ->exists();

                if ($existeNIT) {
                    throw new InvalidArgumentException(
                        "Ya existe una copropiedad con el NIT '{$nit}' en esta organización."
                    );
                }
            }

            $copropiedad = Copropiedad::create([
                'organizacion_id' => $organizacionId,
                'nombre' => $nombre,
                'nit' => $nit,
                'representante_legal' => $representanteLegal,
                'direccion' => $direccion,
                'ciudad' => $ciudad,
                'telefono' => $telefono,
                'email' => $email,
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ConfiguracionCopropiedad::create([
                'organizacion_id' => $organizacionId,
                'copropiedad_id' => $copropiedad->id,
            ]);

            $primeraCopropiedad = Copropiedad::where('organizacion_id', $organizacionId)->count();
            if ($primeraCopropiedad === 1) {
                $siteSetting = SiteSetting::first();
                if ($siteSetting && $siteSetting->organizacion_id === null) {
                    $siteSetting->update([
                        'organizacion_id' => $organizacionId,
                        'copropiedad_id' => $copropiedad->id,
                    ]);
                }
            }

            return $copropiedad;
        });
    }
}
