<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsión de sesión (HU-USR-08): un usuario con `users.estado ≠ 'activo'`
 * pierde su sesión abierta y su acceso "recordarme" en la siguiente petición.
 * `Auth::logout()` rota el `remember_token`, invalidando las cookies
 * "recordarme" ya emitidas; `invalidate()` destruye la sesión actual.
 */
class ExpulsarUsuariosInactivos
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && $usuario->estado !== 'activo') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta está desactivada. Contacta con el administrador del sistema.']);
        }

        return $next($request);
    }
}
