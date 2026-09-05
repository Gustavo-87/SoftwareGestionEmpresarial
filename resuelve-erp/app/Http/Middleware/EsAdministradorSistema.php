<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdministradorSistema
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->esAdministradorSistema()) {
            abort(403, 'No tienes autoridad para acceder al área administrativa del sistema.');
        }

        return $next($request);
    }
}
