<?php
namespace App\Http\Controllers;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\AplicarAccionRapidaPqrs;
use App\Models\Pqr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class PqrQuickActionController extends Controller {
    public function update(Request $request, Pqr $pqr, ContextoOperativo $contexto, AplicarAccionRapidaPqrs $accionRapida): RedirectResponse { $data = $request->validate(['estado' => ['nullable', 'in:radicada,en_revision,respondida,cerrada'], 'assigned_to_id' => ['nullable', 'integer'], 'prioridad' => ['nullable', 'in:alta,media,baja']]); $accionRapida->ejecutar($contexto, $request->user(), $pqr, $data); return back()->with('success', 'Solicitud actualizada.'); }
}
