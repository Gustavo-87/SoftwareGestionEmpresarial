<?php

namespace App\Application\Autorizacion;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Pqr;

final class AutorizacionContextual
{
    public function tienePermiso(ContextoOperativo $contexto, string $clave): bool
    {
        return $contexto->tieneMembresiaContextual()
            && in_array($clave, $contexto->clavesPermisos(), true);
    }

    public function tieneRol(ContextoOperativo $contexto, string $clave): bool
    {
        return $contexto->tieneMembresiaContextual()
            && in_array($clave, $contexto->clavesRoles(), true);
    }

    public function puedeVerPqr(ContextoOperativo $contexto, Pqr $pqr): bool
    {
        if (! $this->perteneceAlContexto($contexto, $pqr)) {
            return false;
        }

        return $this->tienePermiso($contexto, 'pqrs.ver_todas')
            || ($this->tienePermiso($contexto, 'pqrs.ver_propias')
                && $contexto->usuario?->id === $pqr->user_id);
    }

    public function puedeGestionarPqr(ContextoOperativo $contexto, Pqr $pqr): bool
    {
        if (! $this->perteneceAlContexto($contexto, $pqr)
            || ! $this->tienePermiso($contexto, 'pqrs.gestionar')) {
            return false;
        }

        // Capacidad `pqrs.gestionar_asignadas`: gestión restringida a la PQR
        // sin asignar o asignada al propio usuario (semántica de apoyo).
        if (! $this->tienePermiso($contexto, 'pqrs.gestionar_asignadas')) {
            return true;
        }

        return $pqr->assigned_to_id === null
            || $pqr->assigned_to_id === $contexto->usuario?->id;
    }

    private function perteneceAlContexto(ContextoOperativo $contexto, Pqr $pqr): bool
    {
        return $pqr->organizacion_id === $contexto->organizacion->id
            && $pqr->copropiedad_id === $contexto->copropiedad->id;
    }
}
