<?php

namespace App\Http\Middleware;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Application\Membresias\ConsultaMembresiasVigentes;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalidación por petición de la API (HU-API-01, Sprint 21).
 *
 * En cada solicitud comprueba el usuario activo, resuelve la Membresía
 * vigente (la indicada en el header `X-Membresia-Id` o la única disponible),
 * revalida el contexto institucional y los roles/permisos efectivos vía
 * `ContextResolver`. Un token de usuario inactivo o de Membresía vencida no
 * obtiene acceso. El contexto resuelto sustituye el binding de
 * `ContextoOperativo` para que las policies existentes operen sobre el mismo
 * contexto que el controlador (sin duplicar reglas de autorización).
 */
final class ResolverContextoApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof User) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // HU-USR-08 por petición: un token emitido antes de la desactivación
        // deja de dar acceso en cuanto el usuario deja de estar activo.
        if ($usuario->estado !== 'activo') {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contacta con el administrador del sistema.',
            ], 403);
        }

        $vigentes = app(ConsultaMembresiasVigentes::class)->paraUsuario($usuario);
        if ($vigentes->isEmpty()) {
            return response()->json([
                'message' => 'Se requiere una Membresía vigente para operar la API.',
            ], 403);
        }

        $membresia = $this->resolverMembresia($vigentes, $request->header('X-Membresia-Id'));

        try {
            $contexto = app(ContextResolver::class)->resolverExplicito(
                (int) $membresia->organizacion_id,
                (int) $membresia->copropiedad_id,
                (int) $usuario->id,
            );
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'No fue posible resolver el contexto operativo.',
            ], 403);
        }

        if (! $contexto->membresiaCopropiedad instanceof MembresiaCopropiedad) {
            return response()->json([
                'message' => 'Se requiere una Membresía vigente para operar la API.',
            ], 403);
        }

        app()->instance(ContextoOperativo::class, $contexto);
        $request->attributes->set('contexto_api', $contexto);

        return $next($request);
    }

    /** @param Collection<int, MembresiaCopropiedad> $vigentes */
    private function resolverMembresia($vigentes, ?string $membresiaId): MembresiaCopropiedad
    {
        if ($membresiaId !== null && $membresiaId !== '') {
            $seleccionada = $vigentes->firstWhere('id', (int) $membresiaId);
            if ($seleccionada === null) {
                throw ValidationException::withMessages([
                    'membresia_id' => 'La Membresía indicada no pertenece al usuario o no está vigente.',
                ]);
            }

            return $seleccionada;
        }

        if ($vigentes->count() > 1) {
            throw ValidationException::withMessages([
                'membresia_id' => 'Indica la Membresía (cabecera X-Membresia-Id) para operar la API.',
            ]);
        }

        return $vigentes->first();
    }
}
