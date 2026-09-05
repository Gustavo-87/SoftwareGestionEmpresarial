<?php

namespace App\Policies;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;
use App\Models\User;

class PqrPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'pqrs.listar');
    }

    public function view(User $user, Pqr $pqr): bool
    {
        return app(AutorizacionContextual::class)->puedeVerPqr(app(ContextoOperativo::class), $pqr);
    }

    public function create(User $user): bool
    {
        return app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'pqrs.crear');
    }

    public function update(User $user, Pqr $pqr): bool
    {
        return app(AutorizacionContextual::class)->puedeGestionarPqr(app(ContextoOperativo::class), $pqr);
    }

    public function delete(User $user, Pqr $pqr): bool
    {
        return app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'pqrs.eliminar');
    }
}
