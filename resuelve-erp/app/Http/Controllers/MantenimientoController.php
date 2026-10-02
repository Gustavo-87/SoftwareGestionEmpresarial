<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Mantenimiento\ConsultaMantenimientos;
use App\Application\Mantenimiento\GestionarMantenimiento;
use App\Application\Mantenimiento\RegistrarMantenimiento;
use App\Models\Mantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MantenimientoController extends Controller
{
    public function index(ContextoOperativo $contexto, ConsultaMantenimientos $consulta): View
    {
        $mantenimientos = $consulta->para($contexto)->with(['solicitante', 'responsable'])->latest()->paginate(15);

        return view('mantenimiento.index', compact('mantenimientos'));
    }

    public function create(): View
    {
        $this->authorize('create', Mantenimiento::class);

        return view('mantenimiento.create');
    }

    public function store(Request $request, ContextoOperativo $contexto, RegistrarMantenimiento $registrar): RedirectResponse
    {
        $mantenimiento = $registrar->ejecutar($contexto, $request->only('titulo', 'descripcion'));

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Solicitud de mantenimiento registrada.');
    }

    public function show(Mantenimiento $mantenimiento, ContextoOperativo $contexto, ConsultaMantenimientos $consulta): View
    {
        $this->authorize('view', $mantenimiento);
        $mantenimiento->load(['solicitante', 'responsable']);
        $responsables = request()->user()->can('update', $mantenimiento) ? $consulta->responsables($contexto)->orderBy('name')->get() : collect();

        return view('mantenimiento.show', compact('mantenimiento', 'responsables'));
    }

    public function update(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, GestionarMantenimiento $gestionar): RedirectResponse
    {
        $gestionar->ejecutar($contexto, $mantenimiento, $request->only('responsable_id', 'fecha_programada', 'estado'));

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Mantenimiento actualizado.');
    }
}
