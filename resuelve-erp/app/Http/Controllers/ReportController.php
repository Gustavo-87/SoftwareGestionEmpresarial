<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Application\Mantenimiento\ConsultaMantenimientos;
use App\Application\Pqrs\ConsultaExpedienteIntegrado;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Domain\GestionDocumental\Enums\EstadoDocumentoEnum;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs,
        ConsultaMantenimientos $consultaMantenimientos,
        ConsultaDocumentosContextuales $consultaDocumentos
    ): View
    {
        $pqrs = $this->reportData($request, $contexto, $consultaPqrs);
        $filtros = $this->mantenimientoFiltros($request);
        $reporte = Gate::forUser($request->user())->allows('viewAny', Mantenimiento::class)
            ? $this->mantenimientoReportData($filtros, $contexto, $consultaMantenimientos)
            : null;
        $documentosFiltros = $this->documentosFiltros($request);
        $documentos = Gate::forUser($request->user())->allows('viewAny', Documento::class)
            ? $this->documentosReportData($documentosFiltros, $contexto, $consultaDocumentos)
            : null;

        return view('reports.index', $pqrs + [
            'copropiedad' => $contexto->copropiedad,
            'mantenimiento' => $reporte === null ? null : $reporte['stats'] + ['rows' => $reporte['rows'], 'period' => $reporte['period']],
            'mantenimientoFiltros' => $filtros,
            'responsables' => $reporte === null ? collect() : $consultaMantenimientos->responsables($contexto)->orderBy('name')->get(),
            'documentos' => $documentos === null ? null : $documentos['stats'] + ['rows' => $documentos['rows'], 'period' => $documentos['period']],
            'documentosFiltros' => $documentosFiltros,
            'consolidado' => $this->consolidadoData($pqrs['stats'], $reporte['stats'] ?? null, $documentos['stats'] ?? null),
        ]);
    }

    public function mantenimientoPdf(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaMantenimientos $consulta
    ): Response
    {
        $filtros = array_filter($request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['pendiente', 'en_proceso', 'finalizado'])],
            'responsable_id' => ['nullable', 'integer'],
        ]), fn ($valor) => $valor !== null);

        $data = $this->mantenimientoReportData($filtros, $contexto, $consulta);

        return Pdf::loadView('reports.mantenimiento', $data)->setPaper('a4', 'landscape')->download('informe-mantenimiento-'.now()->format('Ymd').'.pdf');
    }

    /**
     * Expediente integrado de una PQRS en PDF (HU-REP-03, Sprint 19).
     * Reutiliza la consulta del expediente: el PDF nunca revela contenido ni
     * metadatos que el usuario no puede ver.
     */
    public function expedientePdf(
        Request $request,
        Pqr $pqr,
        ContextoOperativo $contexto,
        ConsultaExpedienteIntegrado $consulta
    ): Response
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'informes.exportar'), 403);
        $this->authorize('view', $pqr);

        $data = $consulta->para($request->user(), $contexto, $pqr);
        $data['settings'] = SiteSetting::current();
        $data['copropiedad'] = $contexto->copropiedad;
        $data['generatedAt'] = now();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'informe.expediente.pdf',
            'auditable_type' => Pqr::class,
            'auditable_id' => $pqr->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'organizacion_id' => $contexto->organizacion->id,
                'copropiedad_id' => $contexto->copropiedad->id,
            ],
        ]);

        return Pdf::loadView('reports.expediente', $data)->setPaper('a4', 'landscape')->download('expediente-pqrs-'.$pqr->id.'-'.now()->format('Ymd').'.pdf');
    }

    public function documentosPdf(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaDocumentosContextuales $consulta
    ): Response
    {
        $filtros = array_filter($request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['activo', 'archivado'])],
        ]), fn ($valor) => $valor !== null);

        $data = $this->documentosReportData($filtros, $contexto, $consulta);

        return Pdf::loadView('reports.documentos', $data)->setPaper('a4', 'landscape')->download('informe-documentos-'.now()->format('Ymd').'.pdf');
    }

    // Los filtros del Centro de Reportes viajan prefijados para no interferir
    // con los filtros de PQRS en la misma URL; el PDF recibe los nombres simples.
    private function mantenimientoFiltros(Request $request): array
    {
        $data = $request->validate([
            'mantenimiento_desde' => ['nullable', 'date'],
            'mantenimiento_hasta' => ['nullable', 'date', 'after_or_equal:mantenimiento_desde'],
            'mantenimiento_estado' => ['nullable', Rule::in(['pendiente', 'en_proceso', 'finalizado'])],
            'mantenimiento_responsable_id' => ['nullable', 'integer'],
        ]);

        return array_filter([
            'desde' => $data['mantenimiento_desde'] ?? null,
            'hasta' => $data['mantenimiento_hasta'] ?? null,
            'estado' => $data['mantenimiento_estado'] ?? null,
            'responsable_id' => $data['mantenimiento_responsable_id'] ?? null,
        ], fn ($valor) => $valor !== null);
    }

    private function mantenimientoQuery(
        array $filtros,
        ContextoOperativo $contexto,
        ConsultaMantenimientos $consulta
    ): Collection
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'informes.exportar'), 403);
        $query = $consulta->para($contexto)->with(['solicitante', 'responsable']);
        if (isset($filtros['estado'])) $query->where('estado', $filtros['estado']);
        if (isset($filtros['responsable_id'])) $query->where('responsable_id', (int) $filtros['responsable_id']);
        if (isset($filtros['desde'])) $query->whereDate('fecha_programada', '>=', $filtros['desde']);
        if (isset($filtros['hasta'])) $query->whereDate('fecha_programada', '<=', $filtros['hasta']);

        return $query->orderBy('fecha_programada')->orderBy('id')->get();
    }

    private function mantenimientoReportData(
        array $filtros,
        ContextoOperativo $contexto,
        ConsultaMantenimientos $consulta
    ): array
    {
        $rows = $this->mantenimientoQuery($filtros, $contexto, $consulta);
        $abiertas = $rows->whereIn('estado', ['pendiente', 'en_proceso']);
        $from = $filtros['desde'] ?? $rows->min('fecha_programada');
        $to = $filtros['hasta'] ?? $rows->max('fecha_programada');

        return [
            'rows' => $rows,
            'settings' => SiteSetting::current(),
            'copropiedad' => $contexto->copropiedad,
            'stats' => [
                'total' => $rows->count(),
                'pendientes' => $rows->where('estado', 'pendiente')->count(),
                'en_proceso' => $rows->where('estado', 'en_proceso')->count(),
                'finalizados' => $rows->where('estado', 'finalizado')->count(),
                'programados' => $rows->whereNotNull('fecha_programada')->count(),
                'atrasados' => $abiertas->filter(fn (Mantenimiento $m) => $m->fecha_programada?->isBefore(today()))->count(),
            ],
            'period' => ($from ? Carbon::parse($from)->format('d/m/Y') : 'Sin registros').' - '.($to ? Carbon::parse($to)->format('d/m/Y') : 'Sin registros'),
            'generatedAt' => now(),
        ];
    }

    // Los filtros del Centro de Reportes viajan prefijados para no interferir
    // con los filtros de PQRS en la misma URL; el PDF recibe los nombres simples.
    private function documentosFiltros(Request $request): array
    {
        $data = $request->validate([
            'documentos_desde' => ['nullable', 'date'],
            'documentos_hasta' => ['nullable', 'date', 'after_or_equal:documentos_desde'],
            'documentos_estado' => ['nullable', Rule::in(['activo', 'archivado'])],
        ]);

        return array_filter([
            'desde' => $data['documentos_desde'] ?? null,
            'hasta' => $data['documentos_hasta'] ?? null,
            'estado' => $data['documentos_estado'] ?? null,
        ], fn ($valor) => $valor !== null);
    }

    private function documentosQuery(
        array $filtros,
        ContextoOperativo $contexto,
        ConsultaDocumentosContextuales $consulta
    ): Collection
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'informes.exportar'), 403);
        $this->authorize('viewAny', Documento::class);
        $query = $consulta->para($contexto)->with(['propietarioDocumental', 'versiones']);
        if (isset($filtros['estado'])) $query->where('estado', $filtros['estado']);
        if (isset($filtros['desde'])) $query->whereDate('created_at', '>=', $filtros['desde']);
        if (isset($filtros['hasta'])) $query->whereDate('created_at', '<=', $filtros['hasta']);

        return $query->orderBy('created_at')->orderBy('id')->get();
    }

    private function documentosReportData(
        array $filtros,
        ContextoOperativo $contexto,
        ConsultaDocumentosContextuales $consulta
    ): array
    {
        $rows = $this->documentosQuery($filtros, $contexto, $consulta);
        $from = $filtros['desde'] ?? $rows->min('created_at');
        $to = $filtros['hasta'] ?? $rows->max('created_at');

        return [
            'rows' => $rows,
            'settings' => SiteSetting::current(),
            'copropiedad' => $contexto->copropiedad,
            'stats' => [
                'total' => $rows->count(),
                'activos' => $rows->filter(fn (Documento $documento) => $documento->estado === EstadoDocumentoEnum::ACTIVO)->count(),
                'archivados' => $rows->filter(fn (Documento $documento) => $documento->estado === EstadoDocumentoEnum::ARCHIVADO)->count(),
            ],
            'period' => ($from ? Carbon::parse($from)->format('d/m/Y') : 'Sin registros').' - '.($to ? Carbon::parse($to)->format('d/m/Y') : 'Sin registros'),
            'generatedAt' => now(),
        ];
    }

    private function consolidadoData(array $stats, ?array $mantenimiento, ?array $documentos): array
    {
        $modulos = [['nombre' => 'PQRS', 'total' => $stats['total']]];
        if ($mantenimiento !== null) $modulos[] = ['nombre' => 'Mantenimiento', 'total' => $mantenimiento['total']];
        if ($documentos !== null) $modulos[] = ['nombre' => 'Documentos', 'total' => $documentos['total']];

        return ['total' => array_sum(array_column($modulos, 'total')), 'modulos' => $modulos];
    }

    private function query(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    ): Collection
    {
        $this->authorize('viewAny', Pqr::class);
        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'informes.exportar'), 403);
        $query = $consultaPqrs->para($contexto)->with(['user', 'tipoPqr', 'assignee']);
        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'pqrs.ver_todas')) $query->where('user_id', $request->user()->id);
        if ($request->filled('estado') && ! in_array($request->estado, ['pendientes', 'por_vencer'])) $query->where('estado', $request->estado);
        if ($request->estado === 'pendientes') $query->whereIn('estado', ['radicada', 'en_revision']);
        if ($request->estado === 'por_vencer') $query->whereIn('estado', ['radicada', 'en_revision'])->whereBetween('fecha_limite_respuesta', [today(), today()->addDays(3)]);
        if ($request->filled('tipo_pqr_id')) $query->where('tipo_pqr_id', $request->integer('tipo_pqr_id'));
        if ($request->filled('assigned_to_id')) $query->where('assigned_to_id', $request->integer('assigned_to_id'));
        if ($request->filled('desde')) $query->whereDate('fecha_radicacion', '>=', $request->date('desde'));
        if ($request->filled('hasta')) $query->whereDate('fecha_radicacion', '<=', $request->date('hasta'));
        if ($request->filled('buscar')) $query->buscar($request->buscar);

        return $query->orderBy('fecha_radicacion')->get();
    }

    private function reportData(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    ): array
    {
        $rows = $this->query($request, $contexto, $consultaPqrs);
        $open = $rows->whereIn('estado', ['radicada', 'en_revision']);
        $upcoming = $open->filter(fn (Pqr $pqr) => $pqr->fecha_limite_respuesta?->between(today(), today()->addDays(3)))->sortBy('fecha_limite_respuesta');
        $overdue = $open->filter(fn (Pqr $pqr) => $pqr->fecha_limite_respuesta?->isBefore(today()));
        $resolved = $rows->whereIn('estado', ['respondida', 'cerrada'])->count();
        $from = $request->filled('desde') ? Carbon::parse($request->desde) : $rows->min('fecha_radicacion');
        $to = $request->filled('hasta') ? Carbon::parse($request->hasta) : $rows->max('fecha_radicacion');

        return [
            'rows' => $rows,
            'settings' => SiteSetting::current(),
            'upcoming' => $upcoming,
            'stats' => [
                'total' => $rows->count(), 'pending' => $open->count(), 'upcoming' => $upcoming->count(),
                'overdue' => $overdue->count(), 'resolved' => $resolved,
                'compliance' => $rows->count() ? round($resolved / $rows->count() * 100) : 0,
            ],
            'byStatus' => $rows->groupBy('estado')->map->count(),
            'byType' => $rows->groupBy(fn (Pqr $pqr) => $pqr->tipoPqr?->nombre ?? 'Sin tipo')->map->count()->sortDesc(),
            'period' => ($from ? Carbon::parse($from)->format('d/m/Y') : 'Sin registros').' - '.($to ? Carbon::parse($to)->format('d/m/Y') : 'Sin registros'),
            'generatedAt' => now(),
        ];
    }

    public function csv(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    ): StreamedResponse
    {
        $rows = $this->query($request, $contexto, $consultaPqrs);
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Radicado', 'Asunto', 'Tipo', 'Estado', 'Residente', 'Responsable', 'Radicación', 'Límite']);
            foreach ($rows as $pqr) fputcsv($out, [$this->code($pqr), $pqr->asunto, $pqr->tipoPqr?->nombre, $pqr->estado_label, $pqr->user?->name, $pqr->assignee?->name, $pqr->fecha_radicacion->format('Y-m-d'), $pqr->fecha_limite_respuesta?->format('Y-m-d')]);
            fclose($out);
        }, 'informe-pqrs-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    ): StreamedResponse
    {
        $data = $this->reportData($request, $contexto, $consultaPqrs);
        $book = new Spreadsheet();
        $summary = $book->getActiveSheet(); $summary->setTitle('Resumen'); $summary->setShowGridlines(false);
        $color = ltrim(SiteSetting::COLOR_INSTITUCIONAL, '#');
        $summary->mergeCells('A1:H2')->setCellValue('A1', $data['settings']->nombre_conjunto);
        $summary->mergeCells('A3:H3')->setCellValue('A3', 'INFORME DE GESTIÓN DE PQRS');
        $summary->mergeCells('A4:H4')->setCellValue('A4', 'Periodo: '.$data['period'].' | Generado: '.$data['generatedAt']->format('d/m/Y H:i'));
        $summary->getStyle('A1:H4')->getFont()->getColor()->setARGB('FFFFFFFF');
        $summary->getStyle('A1:H3')->getFont()->setBold(true);
        $summary->getStyle('A1:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$color);
        $summary->getStyle('A1:H4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $summary->getStyle('A1')->getFont()->setSize(18); $summary->getStyle('A3')->getFont()->setSize(13);
        $summary->fromArray(['Total', 'Pendientes', 'Por vencer', 'Vencidas', 'Resueltas', 'Cumplimiento'], null, 'A6');
        $summary->fromArray([$data['stats']['total'], $data['stats']['pending'], $data['stats']['upcoming'], $data['stats']['overdue'], $data['stats']['resolved'], $data['stats']['compliance'] / 100], null, 'A7');
        $summary->getStyle('A6:F6')->getFont()->setBold(true)->getColor()->setARGB('FF'.$color);
        $summary->getStyle('A7:F7')->getFont()->setBold(true)->setSize(16); $summary->getStyle('F7')->getNumberFormat()->setFormatCode('0%');
        $summary->setCellValue('A10', 'Distribución por estado')->setCellValue('D10', 'Distribución por tipo');
        $summary->getStyle('A10:B10')->getFont()->setBold(true); $summary->getStyle('D10:E10')->getFont()->setBold(true);
        $row = 11; foreach ($data['byStatus'] as $status => $count) { $summary->setCellValue("A{$row}", Str::headline($status))->setCellValue("B{$row}", $count); $row++; }
        $row = 11; foreach ($data['byType'] as $type => $count) { $summary->setCellValue("D{$row}", $type)->setCellValue("E{$row}", $count); $row++; }
        $summary->mergeCells('A18:H18')->setCellValue('A18', 'Datos de la copropiedad');
        $summary->fromArray([['NIT', $data['settings']->nit ?: 'No registrado', 'Dirección', trim(($data['settings']->direccion ?? '').' '.($data['settings']->ciudad ?? ''))], ['Representante legal', $data['settings']->representante_legal ?: 'No registrado', 'Contacto', $data['settings']->email ?: $data['settings']->telefono]], null, 'A19');
        $summary->getStyle('A18:H18')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF'); $summary->getStyle('A18:H18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$color);
        foreach (range('A', 'H') as $column) $summary->getColumnDimension($column)->setWidth(18);

        $detail = $book->createSheet(); $detail->setTitle('Detalle PQRS');
        $headers = ['Radicado', 'Fecha', 'Fecha límite', 'Días restantes', 'Estado', 'Tipo', 'Asunto', 'Residente', 'Unidad', 'Responsable'];
        $detail->fromArray($headers, null, 'A1');
        $row = 2; foreach ($data['rows'] as $pqr) {
            $remaining = in_array($pqr->estado, ['respondida', 'cerrada'], true) ? null : $pqr->remaining_days;
            $detail->fromArray([$this->code($pqr), ExcelDate::PHPToExcel($pqr->fecha_radicacion), $pqr->fecha_limite_respuesta ? ExcelDate::PHPToExcel($pqr->fecha_limite_respuesta) : null, $remaining, $pqr->estado_label, $pqr->tipoPqr?->nombre, $pqr->asunto, $pqr->user?->name, trim(($pqr->user?->tower ? 'Torre '.$pqr->user->tower : '').' '.($pqr->user?->unit ? 'Apto '.$pqr->user->unit : '')), $pqr->assignee?->name ?? 'Sin asignar'], null, "A{$row}"); $row++;
        }
        $this->styleTable($detail, $row - 1, $color); $detail->freezePane('A2'); $detail->setAutoFilter("A1:J".max(1, $row - 1));
        $detail->getStyle("B2:C".max(2, $row - 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        foreach (['A'=>14,'B'=>13,'C'=>14,'D'=>14,'E'=>15,'F'=>15,'G'=>42,'H'=>22,'I'=>16,'J'=>22] as $column => $width) $detail->getColumnDimension($column)->setWidth($width);

        $due = $book->createSheet(); $due->setTitle('Próximas a vencer');
        $due->fromArray(['Radicado', 'Fecha límite', 'Días restantes', 'Asunto', 'Residente', 'Responsable'], null, 'A1');
        $row = 2; foreach ($data['upcoming'] as $pqr) { $due->fromArray([$this->code($pqr), ExcelDate::PHPToExcel($pqr->fecha_limite_respuesta), $pqr->remaining_days, $pqr->asunto, $pqr->user?->name, $pqr->assignee?->name ?? 'Sin asignar'], null, "A{$row}"); $row++; }
        $this->styleTable($due, $row - 1, $color); $due->freezePane('A2'); $due->getStyle("B2:B".max(2, $row - 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        foreach (['A'=>14,'B'=>15,'C'=>15,'D'=>46,'E'=>23,'F'=>23] as $column => $width) $due->getColumnDimension($column)->setWidth($width);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) { (new Xlsx($book))->save('php://output'); }, 'informe-pqrs-'.now()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    ): Response
    {
        $data = $this->reportData($request, $contexto, $consultaPqrs);
        return Pdf::loadView('reports.pqrs', $data)->setPaper('a4', 'landscape')->download('informe-pqrs-'.now()->format('Ymd').'.pdf');
    }

    private function styleTable($sheet, int $lastRow, string $color): void
    {
        $lastColumn = $sheet->getHighestColumn(); $lastRow = max(1, $lastRow);
        $sheet->setShowGridlines(false); $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$color);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFDDE5E1');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(28);
    }

    private function code(Pqr $pqr): string { return 'PQR-'.str_pad((string) $pqr->id, 4, '0', STR_PAD_LEFT); }
}
