<?php

namespace App\Http\Controllers\Admin;

use App\Application\TiposPqr\GestionarTipoPqr;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTipoPqrRequest;
use App\Http\Requests\UpdateTipoPqrRequest;
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
        // El catálogo normal excluye los desactivados (HU-CONF-03): la papelera
        // los lista y restaura.
        $tipos = TipoPqr::query()->whereNull('desactivada_at')->withCount('pqrs')->orderBy('nombre')->get();

        return view('admin.tipos-pqr.index', compact('tipos'));
    }

    /** Papelera (HU-CONF-03): tipos desactivados con acción de restauración. */
    public function papelera(): View
    {
        $tipos = TipoPqr::query()->whereNotNull('desactivada_at')->withCount('pqrs')->orderBy('nombre')->get();

        return view('admin.tipos-pqr.papelera', compact('tipos'));
    }

    public function create(): View
    {
        return view('admin.tipos-pqr.form', ['tipo' => new TipoPqr]);
    }

    public function store(StoreTipoPqrRequest $request): RedirectResponse
    {
        app(GestionarTipoPqr::class)->crear($request->user(), $request->validated());
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS creado correctamente.');
    }

    public function edit(TipoPqr $tipo): View
    {
        return view('admin.tipos-pqr.form', compact('tipo'));
    }

    public function update(UpdateTipoPqrRequest $request, TipoPqr $tipo): RedirectResponse
    {
        app(GestionarTipoPqr::class)->editar($request->user(), $tipo, $request->validated());
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS actualizado correctamente.');
    }

    /** Baja lógica (HU-CONF-03): conserva la fila y las guardas por uso. */
    public function destroy(Request $request, TipoPqr $tipo): RedirectResponse
    {
        app(GestionarTipoPqr::class)->desactivar($request->user(), $tipo);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS desactivado correctamente.');
    }

    public function reactivar(Request $request, TipoPqr $tipo): RedirectResponse
    {
        app(GestionarTipoPqr::class)->reactivar($request->user(), $tipo);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('admin.tipos-pqr.index')
            ->with('success', 'Tipo de PQRS reactivado correctamente.');
    }
}
