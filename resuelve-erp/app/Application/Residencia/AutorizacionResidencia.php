<?php

namespace App\Application\Residencia;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Puerta de autorización compartida de gestión de residencia (HU-RYP-01).
 * Exige Membresía vigente y `residentes.gestionar` sobre el contexto activo.
 */
final class AutorizacionResidencia
{
    public static function exigirGestion(ContextoOperativo $contexto): void
    {
        if (! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar la residencia.');
        }

        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'residentes.gestionar')) {
            throw new AuthorizationException('No tienes permiso para gestionar la residencia de esta Copropiedad.');
        }
    }
}
