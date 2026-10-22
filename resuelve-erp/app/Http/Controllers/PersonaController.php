<?php

namespace App\Http\Controllers;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Residencia\ConsultaPersonasContextuales;
use App\Application\Residencia\GestionarPersona;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Personas de la Organización (HU-RYP-01, Sprint 20). Las Personas son
 * registros de residencia: no crean cuentas de usuario ni conceden acceso.
 */
class PersonaController extends Controller
{
    public function index(Request $request, ContextoOperativo $contexto, ConsultaPersonasContextuales $consulta): View
    {
        $this->permitir($contexto);
        $q = trim((string) $request->input('q'));
        $estado = $request->input('estado');

        $personas = $consulta->para($contexto)
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($query) use ($q): void {
                    $query->where('nombre_razon_social', 'like', '%'.$q.'%')
                        ->orWhere('identificacion', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                });
            })
            ->when(in_array($estado, ['activa', 'desactivada'], true), fn ($query) => $query->where('estado', $estado))
            ->orderBy('nombre_razon_social')
            ->paginate(20)
            ->withQueryString();

        return view('management.personas', compact('personas', 'q', 'estado'));
    }

    public function store(Request $request, ContextoOperativo $contexto, GestionarPersona $gestionar): RedirectResponse
    {
        $this->permitir($contexto);

        $gestionar->crear($contexto, $request->user(), $request->all());

        return redirect()->back()->with('success', 'Persona registrada correctamente.');
    }

    /** Desactivación administrativa sin eliminación: los vínculos se conservan. */
    public function desactivar(Request $request, ContextoOperativo $contexto, Persona $persona, GestionarPersona $gestionar): RedirectResponse
    {
        $gestionar->desactivar($contexto, $request->user(), $persona);

        return redirect()->route('management.personas.index')->with('success', 'Persona desactivada. Sus vínculos se conservan como historia.');
    }

    public function reactivar(Request $request, ContextoOperativo $contexto, Persona $persona, GestionarPersona $gestionar): RedirectResponse
    {
        $gestionar->reactivar($contexto, $request->user(), $persona);

        return redirect()->route('management.personas.index')->with('success', 'Persona reactivada correctamente.');
    }

    private function permitir(ContextoOperativo $contexto): void
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'residentes.gestionar'), 403);
    }
}
