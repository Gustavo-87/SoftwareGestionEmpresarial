<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\VincularEvidenciaMantenimiento;
use App\Application\Mantenimiento\ConsultaMantenimientos;
use App\Application\Mantenimiento\FinalizarMantenimiento;
use App\Application\Mantenimiento\GenerarMantenimientoDesdePqr;
use App\Application\Mantenimiento\GestionarMantenimiento;
use App\Application\Mantenimiento\RegistrarMantenimiento;
use App\Application\Mantenimiento\RegistrarResultadoMantenimiento;
use App\Application\Mantenimiento\VincularMantenimientoPqr;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoDocumentoEvidencia;
use App\Models\Pqr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MantenimientoController extends Controller
{
    public function index(ContextoOperativo $contexto, ConsultaMantenimientos $consulta): View
    {
        $mantenimientos = $consulta->para($contexto)->with(['solicitante', 'responsable'])->latest()->paginate(15);
        $resumen = $consulta->resumen($contexto);

        return view('mantenimiento.index', compact('mantenimientos', 'resumen'));
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
        $mantenimiento->load(['solicitante', 'responsable', 'pqr']);
        $usuario = request()->user();
        $gestiona = $usuario->can('update', $mantenimiento);
        $puedeVerInterno = $gestiona
            || app(\App\Application\Autorizacion\AutorizacionContextual::class)->tienePermiso($contexto, 'mantenimiento.ver_todas');
        $responsables = $gestiona ? $consulta->responsables($contexto)->orderBy('name')->get() : collect();
        $evidencias = $mantenimiento->evidencias()->with(['documento', 'version'])->orderBy('id')->get();
        $pqrsVinculables = collect();
        $evidenciasDisponibles = collect();
        if ($gestiona && $mantenimiento->estado !== 'finalizado') {
            $pqrsVinculables = app(\App\Application\Pqrs\ConsultaPqrsContextuales::class)
                ->para($contexto)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->filter(fn (Pqr $pqr) => $usuario->can('update', $pqr))
                ->values();
            $evidenciasDisponibles = app(\App\Application\Documentos\ConsultaDocumentosContextuales::class)
                ->para($contexto)
                ->where('estado', 'activo')
                ->with('versiones')
                ->get()
                ->filter(fn (Documento $documento) => $usuario->can('view', $documento))
                ->flatMap(fn (Documento $documento) => $documento->versiones
                    ->filter(fn (DocumentoVersion $version) => $version->estado->value === 'aprobada')
                    ->map(fn (DocumentoVersion $version) => [
                        'version_id' => $version->id,
                        'documento' => $documento->titulo,
                        'numero' => $version->numero,
                    ]))
                ->values();
        }

        return view('mantenimiento.show', compact('mantenimiento', 'responsables', 'evidencias', 'pqrsVinculables', 'evidenciasDisponibles', 'gestiona', 'puedeVerInterno'));
    }

    public function update(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, GestionarMantenimiento $gestionar): RedirectResponse
    {
        $gestionar->ejecutar($contexto, $mantenimiento, $request->only('responsable_id', 'fecha_programada', 'estado'));

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Mantenimiento actualizado.');
    }

    /**
     * Generación de mantenimiento desde una PQRS (HU-PQRS-03). La operación es
     * idempotente mediante `idempotency_key`: doble clic, reenvío o concurrencia
     * no duplican el mantenimiento.
     */
    public function storeDesdePqr(Request $request, Pqr $pqr, ContextoOperativo $contexto, GenerarMantenimientoDesdePqr $generar): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
            'fecha_programada' => ['nullable', 'date'],
            'idempotency_key' => ['required', 'string', 'max:36'],
            'confirmar_duplicado' => ['nullable', 'boolean'],
        ]);

        $mantenimiento = $generar->ejecutar(
            $request->user(),
            $contexto,
            $pqr,
            $datos['titulo'],
            $datos['descripcion'],
            $datos['fecha_programada'] ?? null,
            $datos['idempotency_key'],
            $request->boolean('confirmar_duplicado'),
        );

        return redirect()->route('mantenimiento.show', $mantenimiento)
            ->with('success', 'Mantenimiento generado y vinculado a la PQRS.');
    }

    /**
     * Vínculo posterior con una PQRS (HU-MAN-03). Sin `pqr_id` se retira el
     * vínculo; ambos movimientos son idempotentes y auditados.
     */
    public function vincularPqr(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, VincularMantenimientoPqr $vincular): RedirectResponse
    {
        $datos = $request->validate(['pqr_id' => ['nullable', 'integer']]);

        if (empty($datos['pqr_id'])) {
            $vincular->desvincular($request->user(), $contexto, $mantenimiento);

            return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Vínculo con la PQRS retirado.');
        }

        $pqr = Pqr::query()->findOrFail($datos['pqr_id']);
        $vincular->vincular($request->user(), $contexto, $mantenimiento, $pqr);

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Mantenimiento vinculado a la PQRS.');
    }

    /** Resultado público e interno del mantenimiento (HU-MAN-04). */
    public function resultado(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, RegistrarResultadoMantenimiento $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'resultado_publico' => ['nullable', 'string', 'max:4000'],
            'resultado_interno' => ['nullable', 'string', 'max:4000'],
        ]);

        $registrar->ejecutar(
            $request->user(),
            $contexto,
            $mantenimiento,
            $datos['resultado_publico'] ?? null,
            $datos['resultado_interno'] ?? null,
        );

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Resultado registrado correctamente.');
    }

    /**
     * Finalización del mantenimiento (HU-MAN-04). No responde ni cierra la
     * PQRS: esas operaciones se realizan de forma separada y explícita.
     */
    public function finalizar(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, FinalizarMantenimiento $finalizar): RedirectResponse
    {
        $finalizar->ejecutar($request->user(), $contexto, $mantenimiento);

        return redirect()->route('mantenimiento.show', $mantenimiento)
            ->with('success', 'Mantenimiento finalizado. La PQRS no se cierra automáticamente: registra la respuesta y el cierre desde su expediente.');
    }

    /** Evidencia documental con versión exacta (HU-DOC-03). */
    public function vincularEvidencia(Request $request, Mantenimiento $mantenimiento, ContextoOperativo $contexto, VincularEvidenciaMantenimiento $vincular): RedirectResponse
    {
        $datos = $request->validate(['documento_version_id' => ['required', 'integer']]);

        $version = DocumentoVersion::query()->findOrFail($datos['documento_version_id']);
        $documento = $version->documento ?? abort(404);

        $vincular->vincular($request->user(), $contexto, $mantenimiento, $documento, $version);

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Evidencia documental vinculada.');
    }

    public function retirarEvidencia(Request $request, Mantenimiento $mantenimiento, MantenimientoDocumentoEvidencia $evidencia, ContextoOperativo $contexto, VincularEvidenciaMantenimiento $vincular): RedirectResponse
    {
        // Resolución acotada al mantenimiento de la ruta: una evidencia ajena
        // no existe para este recurso (404), igual que los bindings contextuales.
        $evidencia = $mantenimiento->evidencias()->whereKey($evidencia->id)->firstOrFail();

        $vincular->retirar($request->user(), $contexto, $mantenimiento, $evidencia);

        return redirect()->route('mantenimiento.show', $mantenimiento)->with('success', 'Evidencia documental retirada.');
    }
}
