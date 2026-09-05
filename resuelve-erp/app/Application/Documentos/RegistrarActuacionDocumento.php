<?php

namespace App\Application\Documentos;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;

final class RegistrarActuacionDocumento
{
    public function registrar(Documento $documento, ?DocumentoVersion $version, ?User $actor, string $accion, ?string $detalle = null): void
    {
        $documento->actuaciones()->create([
            'documento_version_id' => $version?->id,
            'organizacion_id' => $documento->organizacion_id,
            'copropiedad_id' => $documento->copropiedad_id,
            'accion' => $accion,
            'detalle' => $detalle,
            'actor_user_id' => $actor?->id,
            'nombre_actor' => $actor?->name,
        ]);
    }
}
