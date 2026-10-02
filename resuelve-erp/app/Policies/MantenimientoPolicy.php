<?php

namespace App\Policies;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Mantenimiento;
use App\Models\User;

class MantenimientoPolicy
{
    private function permiso(string $clave): bool
    {
        return app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'mantenimiento.'.$clave);
    }

    public function viewAny(User $user): bool
    {
        return $this->permiso('ver_todas') || $this->permiso('ver_propias');
    }

    public function create(User $user): bool
    {
        return $this->permiso('crear');
    }

    public function view(User $user, Mantenimiento $mantenimiento): bool
    {
        return $this->local($mantenimiento) && ($this->permiso('ver_todas') || ($this->permiso('ver_propias') && $mantenimiento->solicitante_id === $user->id));
    }

    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $this->local($mantenimiento) && $this->permiso('gestionar');
    }

    private function local(Mantenimiento $mantenimiento): bool
    {
        $c = app(ContextoOperativo::class);

        return $mantenimiento->organizacion_id === $c->organizacion->id && $mantenimiento->copropiedad_id === $c->copropiedad->id;
    }
}
