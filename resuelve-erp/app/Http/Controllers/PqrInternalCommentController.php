<?php

namespace App\Http\Controllers;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Application\Pqrs\RegistrarComentarioInternoPqrs;
use App\Models\Pqr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PqrInternalCommentController extends Controller
{
    public function store(Request $request, Pqr $pqr, ContextoOperativo $contexto, ConsultaPqrsContextuales $consultaPqrs, AutorizacionContextual $autorizacion, RegistrarComentarioInternoPqrs $registrarComentario): RedirectResponse
    {
        $pqr = $consultaPqrs->resolver($contexto, $pqr->getKey());
        abort_unless($autorizacion->puedeGestionarPqr($contexto, $pqr), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $registrarComentario->ejecutar($contexto, $request->user(), $pqr, $data['body']);

        return back()->with('success', 'Comentario interno agregado.');
    }
}
