<?php

namespace App\Http\Controllers;

use App\Application\Auditoria\ConsultaAuditoria;
use App\Application\Contexto\ContextoOperativo;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

final class AuditController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', new Middleware('admin.sistema', only: ['plataforma'])];
    }

    public function index(Request $request, ContextoOperativo $contexto, ConsultaAuditoria $consulta): View
    {
        return view('management.audit', $consulta->ejecutar($request->user(), $contexto, $request));
    }

    public function plataforma(Request $request, ConsultaAuditoria $consulta): View
    {
        return view('management.audit', $consulta->ejecutar($request->user(), null, $request, true));
    }
}
