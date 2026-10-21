<?php

namespace App\Http\Middleware;

use App\Application\Contexto\ContextoInstitucionalNoConfigurado;
use App\Application\Contexto\ContextoOperativo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selección de Copropiedad y Rol activo después del inicio de sesión
 * (ampliación del Sprint 18). Un usuario con membresía operativa no accede a
 * rutas operativas mientras la selección de acceso del inicio de sesión siga
 * pendiente, o cuando su Copropiedad activa tiene varios Roles y ninguno activo
 * válido (por ejemplo, tras revocar el Rol seleccionado). La autoridad de
 * plataforma y los usuarios sin membresía operativa conservan sus flujos
 * específicos.
 *
 * Reutiliza `ContextoOperativo` ya resuelto por la petición (instancia
 * memoizada), por lo que no agrega consultas adicionales.
 */
final class SeleccionAccesoPendiente
{
    /** Rutas que completan o rodean la selección sin exigirla. */
    private const RUTAS_PERMITIDAS = [
        'acceso.seleccion',
        'acceso.copropiedad',
        'acceso.rol',
        'logout',
        'contexto.cambiar',
        'profile.edit',
        'profile.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null
            || $request->routeIs(...self::RUTAS_PERMITIDAS)
            || $usuario->esAdministradorSistema()) {
            return $next($request);
        }

        if ($request->session()->get('acceso_seleccion_pendiente') === true) {
            return redirect()->guest(route('acceso.seleccion'));
        }

        try {
            $contexto = app(ContextoOperativo::class);
        } catch (ContextoInstitucionalNoConfigurado) {
            // Sin contexto institucional la selección no aplica: cada ruta
            // conserva su propia autorización y sus respuestas de error.
            return $next($request);
        }

        if (! $contexto->tieneMembresiaContextual()) {
            return $next($request);
        }

        // Con varios Roles y ninguno activo válido se exige elegir antes de
        // operar; la selección caducada se revalida al resolver el contexto.
        if (! $contexto->tieneRolActivo() && count($contexto->clavesRolesAsignados()) > 1) {
            return redirect()->guest(route('acceso.seleccion'));
        }

        return $next($request);
    }
}
