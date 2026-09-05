<?php

namespace App\Application\Organizaciones;

use App\Models\ConfiguracionOrganizacion;
use App\Models\Organizacion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CrearOrganizacion
{
    public function ejecutar(
        string $nombre,
        ?string $identificacionTributaria = null,
        ?string $email = null,
        ?string $telefono = null,
        ?int $creadaPor = null,
    ): Organizacion {
        return DB::transaction(function () use (
            $nombre,
            $identificacionTributaria,
            $email,
            $telefono,
            $creadaPor,
        ) {
            if (blank($nombre)) {
                throw new InvalidArgumentException('El nombre de la organización es obligatorio.');
            }

            $existe = Organizacion::query()
                ->where('nombre', $nombre)
                ->exists();

            if ($existe) {
                throw new InvalidArgumentException("Ya existe una organización con el nombre '{$nombre}'.");
            }

            if ($identificacionTributaria !== null) {
                $existeNIT = Organizacion::query()
                    ->where('identificacion_tributaria', $identificacionTributaria)
                    ->exists();

                if ($existeNIT) {
                    throw new InvalidArgumentException("Ya existe una organización con el NIT '{$identificacionTributaria}'.");
                }
            }

            $organizacion = Organizacion::create([
                'nombre' => $nombre,
                'identificacion_tributaria' => $identificacionTributaria,
                'email' => $email,
                'telefono' => $telefono,
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ConfiguracionOrganizacion::create([
                'organizacion_id' => $organizacion->id,
            ]);

            return $organizacion;
        });
    }
}
