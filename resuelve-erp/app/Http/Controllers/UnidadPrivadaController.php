<?php

namespace App\Http\Controllers;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Residencia\ConsultaPersonasContextuales;
use App\Application\Residencia\ConsultaUnidadesPrivadasContextuales;
use App\Application\Residencia\GestionarUnidadPrivada;
use App\Application\Residencia\GestionarVinculoUnidad;
use App\Domain\Residencia\Enums\TipoUnidadEnum;
use App\Models\UnidadPrivada;
use App\Models\VinculoUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestión visible de Unidades Privadas (HU-RYP-01, Sprint 20).
 * La autorización efectiva reside en backend (`residentes.gestionar`);
 * el binding `{unidad}` es contextual (aislamiento entre Copropiedades).
 */
class UnidadPrivadaController extends Controller
{
    public function index(Request $request, ContextoOperativo $contexto, ConsultaUnidadesPrivadasContextuales $consulta): View
    {
        $this->permitir($contexto);
        $q = trim((string) $request->input('q'));
        $estado = $request->input('estado');

        $unidades = $consulta->para($contexto)
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($query) use ($q): void {
                    $query->where('codigo', 'like', '%'.$q.'%')
                        ->orWhere('numero_nombre', 'like', '%'.$q.'%')
                        ->orWhere('torre_bloque', 'like', '%'.$q.'%');
                });
            })
            ->when(in_array($estado, ['activa', 'desactivada'], true), fn ($query) => $query->where('estado', $estado))
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        return view('management.unidades', compact('unidades', 'q', 'estado'));
    }

    public function create(ContextoOperativo $contexto): View
    {
        $this->permitir($contexto);

        return view('management.unidades-form', ['unidad' => null, 'tipos' => TipoUnidadEnum::cases()]);
    }

    public function store(Request $request, ContextoOperativo $contexto, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->crear($contexto, $request->user(), $request->all());

        return redirect()->route('management.unidades.index')->with('success', 'Unidad privada creada correctamente.');
    }

    public function edit(ContextoOperativo $contexto, UnidadPrivada $unidad): View
    {
        $this->permitir($contexto);

        return view('management.unidades-form', ['unidad' => $unidad, 'tipos' => TipoUnidadEnum::cases()]);
    }

    public function update(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->actualizar($contexto, $request->user(), $unidad, $request->all());

        return redirect()->route('management.unidades.index')->with('success', 'Unidad privada actualizada correctamente.');
    }

    public function show(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad): View
    {
        $this->permitir($contexto);

        $vigentes = $unidad->vinculos()->vigentes()->with('persona')->orderBy('vigente_desde')->get();
        $historicos = $unidad->vinculos()
            ->whereNotIn('id', $vigentes->modelKeys())
            ->with('persona')
            ->orderByDesc('vigente_desde')
            ->get();
        $qPersona = trim((string) $request->input('q_persona'));
        $personas = app(ConsultaPersonasContextuales::class)->para($contexto)
            ->where('estado', 'activa')
            ->when($qPersona !== '', function ($query) use ($qPersona): void {
                $query->where(function ($query) use ($qPersona): void {
                    $query->where('nombre_razon_social', 'like', '%'.$qPersona.'%')
                        ->orWhere('identificacion', 'like', '%'.$qPersona.'%');
                });
            })
            ->orderBy('nombre_razon_social')
            ->paginate(20)
            ->appends(['q_persona' => $qPersona]);

        return view('management.unidad', compact('unidad', 'vigentes', 'historicos', 'personas', 'qPersona'));
    }

    /**
     * Asociación Persona ↔ Unidad con vigencias (HU-RYP-01). El vínculo
     * residencial no concede acceso al ERP.
     */
    public function vincular(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, GestionarVinculoUnidad $gestionar): RedirectResponse
    {
        $gestionar->asociar($contexto, $request->user(), $unidad, $request->all());

        return redirect()->route('management.unidades.show', $unidad)->with('success', 'Vínculo registrado correctamente.');
    }

    public function concluirVinculo(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, VinculoUnidad $vinculo, GestionarVinculoUnidad $gestionar): RedirectResponse
    {
        // Resolución acotada a la unidad de la ruta: un vínculo ajeno no
        // existe para este recurso (404), como en los bindings contextuales.
        $vinculo = $unidad->vinculos()->whereKey($vinculo->id)->firstOrFail();

        $gestionar->concluir($contexto, $request->user(), $vinculo, $request->input('vigente_hasta') ?: null);

        return redirect()->route('management.unidades.show', $unidad)->with('success', 'Vínculo concluido; se conserva en el historial.');
    }

    /**
     * Desactivación administrativa (confirmación en la interfaz): sin
     * eliminación física y sin concluir vínculos existentes.
     */
    public function desactivar(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->desactivar($contexto, $request->user(), $unidad);

        return redirect()->route('management.unidades.index')->with('success', 'Unidad desactivada. Sus vínculos se conservan sin cambios.');
    }

    public function reactivar(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->reactivar($contexto, $request->user(), $unidad);

        return redirect()->route('management.unidades.index')->with('success', 'Unidad reactivada correctamente.');
    }

    /** Corrección justificada de fechas de un vínculo (audita antes/después). */
    public function corregirVinculo(Request $request, ContextoOperativo $contexto, UnidadPrivada $unidad, VinculoUnidad $vinculo, GestionarVinculoUnidad $gestionar): RedirectResponse
    {
        $vinculo = $unidad->vinculos()->whereKey($vinculo->id)->firstOrFail();

        $gestionar->corregirVigencia($contexto, $request->user(), $vinculo, $request->all());

        return redirect()->route('management.unidades.show', $unidad)->with('success', 'Vigencia corregida; los valores anteriores y el motivo quedaron en la auditoría.');
    }

    private function permitir(ContextoOperativo $contexto): void
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'residentes.gestionar'), 403);
    }
}
