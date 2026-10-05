<?php

namespace App\Http\Controllers\Admin;

use App\Application\TiposPqr\GestionarTipoPqr;
use App\Http\Controllers\Controller;
use App\Models\TipoPqr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administración global del catálogo de Tipos de PQRS (autoridad de plataforma).
 * Sección: Administración → Configuración PQRS → Tipos de PQRS.
 */
class TipoPqrController extends Controller
{
    public function index(): View
    {
        $tipos = TipoPqr::query()->withCount('pqrs')->orderBy('nombre')->get();

        return view('admin.tipos-pqr.index', compact('tipos'));
    }

    public function create(): View
    {
        return view('admin.tipos-pqr.form', ['tipo' => new TipoPqr]);
    }

    public function store(Request $request): RedirectResponse
    {
        app(GestionarTipoPqr::class)->crear($request->user(), $request->only('nombre', 'descripcion'));
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS creado correctamente.');
    }

    public function edit(TipoPqr $tipo): View
    {
        return view('admin.tipos-pqr.form', compact('tipo'));
    }

    public function update(Request $request, TipoPqr $tipo): RedirectResponse
    {
        app(GestionarTipoPqr::class)->editar($request->user(), $tipo, $request->only('nombre', 'descripcion'));
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS actualizado correctamente.');
    }

    public function destroy(Request $request, TipoPqr $tipo): RedirectResponse
    {
        app(GestionarTipoPqr::class)->eliminar($request->user(), $tipo);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS eliminado correctamente.');
    }
}
