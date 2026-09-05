<?php

namespace App\Application\Pqrs;

use App\Models\Pqr;
use App\Models\User;

final class RegistrarActuacionPqrs
{
    public function registrar(
        Pqr $pqr,
        User $usuario,
        string $accion,
        string $descripcion,
        array $metadata = [],
    ): void {
        $atributos = [
            'user_id' => $usuario->id,
            'action' => $accion,
            'description' => $descripcion,
        ];

        if ($metadata !== []) {
            $atributos['metadata'] = $metadata;
        }

        $pqr->activities()->create($atributos);
    }
}
