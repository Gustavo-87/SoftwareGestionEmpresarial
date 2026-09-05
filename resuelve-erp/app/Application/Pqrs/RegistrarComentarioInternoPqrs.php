<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RegistrarComentarioInternoPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly ConsultaPqrsContextuales $consultaPqrs,
        private readonly RegistrarActuacionPqrs $actuaciones,
    ) {}

    public function ejecutar(ContextoOperativo $contexto, User $usuario, Pqr $pqr, string $cuerpo): void
    {
        $pqr = $this->consultaPqrs->resolver($contexto, $pqr->getKey());
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException();
        }

        DB::transaction(function () use ($usuario, $pqr, $cuerpo): void {
            $pqr->internalComments()->create(['user_id' => $usuario->id, 'body' => $cuerpo]);
            $this->actuaciones->registrar($pqr, $usuario, 'internal_comment', 'Agregó un comentario interno.');
        });
    }
}
