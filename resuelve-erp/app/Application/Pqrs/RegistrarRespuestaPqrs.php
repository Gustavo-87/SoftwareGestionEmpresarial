<?php

namespace App\Application\Pqrs;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Adaptador legado: toda escritura pasa por el ciclo idempotente B-2. */
final class RegistrarRespuestaPqrs
{
    public function __construct(private readonly GestionarCicloRespuestaPqrs $ciclo) {}

    /** @param iterable<object> $adjuntos */
    public function ejecutar(ContextoOperativo $contexto, User $usuario, Pqr $pqr, array $datos, iterable $adjuntos = [], ?string $idempotencyKey = null): Pqr
    {
        $pqr = $this->ciclo->autorizarCreacion($contexto, $pqr);
        $files = is_array($adjuntos) ? $adjuntos : iterator_to_array($adjuntos);
        if (! in_array($datos['action'] ?? null, ['draft', 'send'], true)) {
            throw new InvalidArgumentException('La operación de respuesta debe ser explícita.');
        }
        $draft = $datos['action'] === 'draft';
        $this->ciclo->create($contexto, $usuario, $pqr, $datos, $files, $idempotencyKey ?? (string) Str::uuid(), $draft);

        return $pqr->refresh();
    }
}
