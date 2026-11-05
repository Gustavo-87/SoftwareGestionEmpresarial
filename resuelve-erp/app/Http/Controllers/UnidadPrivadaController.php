<?php

namespace App\Http\Controllers;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Residencia\ConsultaPersonasContextuales;
use App\Application\Residencia\ConsultaUnidadesPrivadasContextuales;
use App\Application\Residencia\GestionarUnidadPrivada;
use App\Application\Residencia\GestionarVinculoUnidad;
use App\Application\Residencia\ImportarUnidadesPrivadas;
use App\Domain\Residencia\Enums\TipoUnidadEnum;
use App\Http\Requests\StoreUnidadPrivadaRequest;
use App\Http\Requests\UpdateUnidadPrivadaRequest;
use App\Models\SiteSetting;
use App\Models\UnidadPrivada;
use App\Models\VinculoUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function store(StoreUnidadPrivadaRequest $request, ContextoOperativo $contexto, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->crear($contexto, $request->user(), $request->validated());

        return redirect()->route('management.unidades.index')->with('success', 'Unidad privada creada correctamente.');
    }

    public function edit(ContextoOperativo $contexto, UnidadPrivada $unidad): View
    {
        $this->permitir($contexto);

        return view('management.unidades-form', ['unidad' => $unidad, 'tipos' => TipoUnidadEnum::cases()]);
    }

    public function update(UpdateUnidadPrivadaRequest $request, ContextoOperativo $contexto, UnidadPrivada $unidad, GestionarUnidadPrivada $gestionar): RedirectResponse
    {
        $gestionar->actualizar($contexto, $request->user(), $unidad, $request->validated());

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

    /**
     * Importación masiva desde XLSX (HU-RYP-03, Sprint 22). Solo altas.
     */
    public function importar(ContextoOperativo $contexto): View
    {
        $this->permitir($contexto);

        return view('management.unidades-importar', [
            'resultado' => session('importacion_unidades_resultado'),
            'tieneReporte' => (bool) session('importacion_unidades_reporte'),
        ]);
    }

    public function importarStore(Request $request, ContextoOperativo $contexto, ImportarUnidadesPrivadas $importar): RedirectResponse
    {
        $this->permitir($contexto);

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ], [
            'archivo.required' => 'Selecciona un archivo XLSX para importar.',
            'archivo.file' => 'El archivo no pudo ser leído.',
            'archivo.mimes' => 'El archivo debe tener formato XLSX.',
            'archivo.max' => 'El archivo no debe superar 2 MB.',
        ]);

        try {
            $resultado = $importar->ejecutar($contexto, $request->user(), $request->file('archivo'));
        } catch (ValidationException $exception) {
            // Límites del archivo (filas, encabezados o lectura): se rechaza el
            // archivo completo, sin crear registros.
            return redirect()->route('management.unidades.importar')->withErrors($exception->errors());
        }

        // La operación la audita el caso de uso con sus totales.
        $request->attributes->set('auditoria_especifica_registrada', true);
        $request->session()->put('importacion_unidades_reporte', $resultado['errores']);

        return redirect()
            ->route('management.unidades.importar')
            ->with('importacion_unidades_resultado', $resultado + ['archivo' => $request->file('archivo')->getClientOriginalName()]);
    }

    public function reporteErrores(Request $request, ContextoOperativo $contexto): StreamedResponse|RedirectResponse
    {
        $this->permitir($contexto);

        /** @var list<array{fila: int, campo: ?string, motivo: string}> $errores */
        $errores = $request->session()->get('importacion_unidades_reporte', []);
        if ($errores === []) {
            return redirect()->route('management.unidades.importar');
        }

        $settings = SiteSetting::current();
        $color = ltrim(SiteSetting::COLOR_INSTITUCIONAL, '#');

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Errores');
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:C2')->setCellValue('A1', $settings->nombre_conjunto);
        $sheet->mergeCells('A3:C3')->setCellValue('A3', 'REPORTE DE ERRORES DE IMPORTACIÓN DE UNIDADES PRIVADAS');
        $sheet->getStyle('A1:C3')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:C3')->getFont()->setBold(true);
        $sheet->getStyle('A1:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$color);
        $sheet->getStyle('A1:C3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->fromArray(['Fila', 'Campo', 'Motivo'], null, 'A5');
        $sheet->getStyle('A5:C5')->getFont()->setBold(true);

        $fila = 6;
        foreach ($errores as $error) {
            $sheet->setCellValue('A'.$fila, $error['fila']);
            $sheet->setCellValue('B'.$fila, (string) ($error['campo'] ?? ''));
            $sheet->setCellValue('C'.$fila, $error['motivo']);
            $fila++;
        }
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, 'reporte-errores-importacion-unidades-'.now()->format('Ymd').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Papelera (HU-CONF-03): unidades desactivadas con acción de restauración. */
    public function papelera(ContextoOperativo $contexto, ConsultaUnidadesPrivadasContextuales $consulta): View
    {
        $this->permitir($contexto);

        $unidades = $consulta->para($contexto)
            ->where('estado', 'desactivada')
            ->orderBy('codigo')
            ->get();

        return view('management.papelera', [
            'titulo' => 'Papelera de unidades privadas',
            'descripcion' => 'Unidades desactivadas de la Copropiedad activa. Restaurar vuelve a ponerlas en servicio sin crear registros nuevos.',
            'volver' => route('management.unidades.index'),
            'volverTexto' => 'Volver a unidades',
            'columna' => 'Unidad',
            'registros' => $unidades->map(fn (UnidadPrivada $unidad): array => [
                'id' => $unidad->id,
                'resumen' => $unidad->codigo.($unidad->numero_nombre ? ' — '.$unidad->numero_nombre : ''),
                'fecha' => $unidad->desactivada_at?->format('d/m/Y H:i'),
                'restaurar' => route('management.unidades.reactivar', $unidad),
            ]),
        ]);
    }

    private function permitir(ContextoOperativo $contexto): void
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'residentes.gestionar'), 403);
    }
}
