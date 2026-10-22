<?php

namespace App\Http\Controllers;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Application\Mantenimiento\ConsultaMantenimientos;
use App\Application\Notificaciones\ConsultaNotificacionesContextuales;
use App\Application\Pqrs\ActualizarPqrs;
use App\Application\Pqrs\ConsultaParticipacionContextual;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Application\Pqrs\PresentarPqrs;
use App\Application\Pqrs\VisibilidadBorradoresPqrs;
use App\Domain\Pqrs\CalendarioLaboralColombia;
use App\Models\Documento;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\PqrActivity;
use App\Models\PqrReply;
use App\Models\PqrTag;
use App\Models\ResponseTemplate;
use App\Models\TipoPqr;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PqrController extends Controller
{
    public function panel(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs,
        ConsultaNotificacionesContextuales $consultaNotificaciones,
        ConsultaMantenimientos $consultaMantenimientos,
        ConsultaDocumentosContextuales $consultaDocumentos,
    ) {
        $autorizacion = app(AutorizacionContextual::class);
        $puedeConsultarPqrs = $request->user()->can('viewAny', Pqr::class);
        $puedeConsultarDocumentos = $request->user()->can('viewAny', Documento::class);
        $puedeConsultarNotificaciones = $autorizacion->tienePermiso($contexto, 'notificaciones.consultar');
        $resumen = null;

        if ($puedeConsultarPqrs) {
            $baseQuery = $consultaPqrs->para($contexto);
            if (! $autorizacion->tienePermiso($contexto, 'pqrs.ver_todas')) {
                $baseQuery->where('user_id', $request->user()->id);
            }

            $hoy = app(CalendarioLaboralColombia::class)->hoy();

            $resumen = [
                'total' => (clone $baseQuery)->count(),
                'pendientes' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])->count(),
                'por_vencer' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])
                    ->whereBetween('fecha_limite_respuesta', [$hoy, $hoy->addDays(3)])
                    ->count(),
                'vencidas' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])
                    ->whereDate('fecha_limite_respuesta', '<', $hoy)
                    ->count(),
            ];

            // Estadísticas: distribución por estado
            $porEstado = (clone $baseQuery)
                ->select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $estadosLabels = [
                'radicada' => 'Radicadas',
                'en_revision' => 'En revisión',
                'respondida' => 'Respondidas',
                'cerrada' => 'Cerradas',
            ];

            $estadosColores = [
                'radicada' => '#1d4ed8',
                'en_revision' => '#3b82f6',
                'respondida' => '#60a5fa',
                'cerrada' => '#93c5fd',
            ];

            $estadisticasEstados = [];
            $totalPqrs = $resumen['total'];
            foreach ($estadosLabels as $key => $label) {
                $cantidad = (int) $porEstado->get($key, 0);
                $estadisticasEstados[] = [
                    'key' => $key,
                    'label' => $label,
                    'cantidad' => $cantidad,
                    'porcentaje' => $totalPqrs > 0 ? round(($cantidad / $totalPqrs) * 100) : 0,
                    'color' => $estadosColores[$key],
                ];
            }

            // Estadísticas: PQRS por tipo
            $porTipo = (clone $baseQuery)
                ->select('tipo_pqr_id', DB::raw('count(*) as total'))
                ->groupBy('tipo_pqr_id')
                ->pluck('total', 'tipo_pqr_id');

            $tiposNombres = TipoPqr::whereIn('id', $porTipo->keys())->pluck('nombre', 'id');
            $maxTipo = $porTipo->max() ?: 1;

            $estadisticasTipos = [];
            foreach ($porTipo->sortDesc() as $tipoId => $cantidad) {
                $estadisticasTipos[] = [
                    'nombre' => $tiposNombres->get($tipoId, 'Sin tipo'),
                    'cantidad' => (int) $cantidad,
                    'porcentaje' => round(($cantidad / $maxTipo) * 100),
                ];
            }

            // Indicador analítico: Top 5 de PQRS por tipo (orden descendente).
            $estadisticasTipos = array_slice($estadisticasTipos, 0, 5);
        }

        // Resumen operativo por módulo: solo módulos con acceso (RBAC), con
        // aislamiento por Organización y Copropiedad y sin consultas N+1.
        // Estados realmente existentes: PQRS (radicada, en_revision,
        // respondida, cerrada), Mantenimiento (pendiente, en_proceso,
        // finalizado) y versiones de Documento (borrador, pendiente_aprobacion,
        // aprobada, rechazada).
        $indicadores = [];
        if ($puedeConsultarPqrs) {
            $indicadores['pqrs'] = [
                'total' => $resumen['total'],
                'pendientes' => $resumen['pendientes'], // radicada + en_revision
                'en_tramite' => (int) $porEstado->get('en_revision', 0),
                'con_mantenimiento' => (int) (clone $baseQuery)->whereHas('mantenimientos')->count(),
                'estados' => $estadisticasEstados,
            ];
        }
        if ($request->user()->can('viewAny', Mantenimiento::class)) {
            $integracionMantenimiento = $consultaMantenimientos->para($contexto);
            $porEstadoMantenimiento = $consultaMantenimientos->para($contexto)
                ->select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado');
            $totalMantenimiento = (int) $porEstadoMantenimiento->sum();
            $indicadores['mantenimiento'] = [
                'total' => $totalMantenimiento,
                'pendientes' => (int) $porEstadoMantenimiento->get('pendiente', 0),
                'en_proceso' => (int) $porEstadoMantenimiento->get('en_proceso', 0),
                'finalizados' => (int) $porEstadoMantenimiento->get('finalizado', 0),
                'vinculados' => (int) (clone $integracionMantenimiento)->whereNotNull('pqr_id')->count(),
                'abiertos' => (int) (clone $integracionMantenimiento)->whereNotNull('pqr_id')->where('estado', '!=', 'finalizado')->count(),
                'con_evidencia' => (int) (clone $integracionMantenimiento)->whereNotNull('pqr_id')->whereHas('evidencias')->count(),
                'estados' => array_map(fn (array $estado) => [
                    'label' => $estado['label'],
                    'cantidad' => (int) $porEstadoMantenimiento->get($estado['key'], 0),
                    'porcentaje' => $totalMantenimiento > 0
                        ? round((int) $porEstadoMantenimiento->get($estado['key'], 0) / $totalMantenimiento * 100)
                        : 0,
                    'color' => $estado['color'],
                ], [
                    ['label' => 'Pendientes', 'key' => 'pendiente', 'color' => '#15803d'],
                    ['label' => 'En proceso', 'key' => 'en_proceso', 'color' => '#22c55e'],
                    ['label' => 'Finalizados', 'key' => 'finalizado', 'color' => '#86efac'],
                ]),
            ];
        }
        if ($puedeConsultarDocumentos) {
            $documentosBase = $consultaDocumentos->para($contexto);
            $totalDocumentos = (clone $documentosBase)->count();
            $documentosEnTramite = (clone $documentosBase)
                ->whereHas('versiones', fn ($q) => $q->whereIn('estado', ['borrador', 'pendiente_aprobacion']))
                ->whereDoesntHave('versiones', fn ($q) => $q->where('estado', 'aprobada'))
                ->count();
            $documentosAprobados = (clone $documentosBase)
                ->whereHas('versiones', fn ($q) => $q->where('estado', 'aprobada'))
                ->count();
            $documentosOtros = max(0, $totalDocumentos - $documentosEnTramite - $documentosAprobados);
            $indicadores['documentos'] = [
                'total' => $totalDocumentos,
                'en_tramite' => $documentosEnTramite,
                'aprobados' => $documentosAprobados,
                'estados' => [
                    ['label' => 'En trámite', 'cantidad' => $documentosEnTramite, 'color' => '#7c3aed'],
                    ['label' => 'Aprobados', 'cantidad' => $documentosAprobados, 'color' => '#a78bfa'],
                    ['label' => 'Otros estados', 'cantidad' => $documentosOtros, 'color' => '#d8b4fe'],
                ],
            ];
            foreach ($indicadores['documentos']['estados'] as &$estadoDocumento) {
                $estadoDocumento['porcentaje'] = $totalDocumentos > 0
                    ? round($estadoDocumento['cantidad'] / $totalDocumentos * 100)
                    : 0;
            }
            unset($estadoDocumento);
        }

        // Actividad reciente: últimos movimientos reales de los módulos con
        // acceso (sin eventos ficticios), acotados por las mismas consultas
        // contextuales. Enlace al registro solo cuando la policy lo autoriza.
        $etiquetasEstadosPqrs = ['radicada' => 'Radicada', 'en_revision' => 'En revisión', 'respondida' => 'Respondida', 'cerrada' => 'Cerrada'];
        $actividad = collect();
        if ($puedeConsultarPqrs) {
            $actividad = $actividad->merge(
                (clone $baseQuery)->orderByDesc('updated_at')->limit(3)->get()->map(fn (Pqr $pqr) => [
                    'modulo' => 'pqrs',
                    'titulo' => $pqr->asunto,
                    'detalle' => 'PQRS · '.($etiquetasEstadosPqrs[$pqr->estado] ?? $pqr->estado),
                    'fecha' => $pqr->updated_at ?? $pqr->created_at,
                    'href' => $request->user()->can('view', $pqr) ? route('pqrs.show', $pqr) : null,
                ])
            );
        }
        if (isset($indicadores['mantenimiento'])) {
            $actividad = $actividad->merge(
                $consultaMantenimientos->para($contexto)->orderByDesc('updated_at')->limit(3)->get()->map(fn (Mantenimiento $mantenimiento) => [
                    'modulo' => 'mantenimiento',
                    'titulo' => $mantenimiento->titulo,
                    'detalle' => 'Mantenimiento · '.$mantenimiento->etiquetaEstado(),
                    'fecha' => $mantenimiento->updated_at ?? $mantenimiento->created_at,
                    'href' => $request->user()->can('view', $mantenimiento) ? route('mantenimiento.show', $mantenimiento) : null,
                ])
            );
        }
        if ($puedeConsultarDocumentos) {
            $actividad = $actividad->merge(
                $consultaDocumentos->para($contexto)->orderByDesc('updated_at')->limit(3)->get()->map(fn (Documento $documento) => [
                    'modulo' => 'documentos',
                    'titulo' => $documento->titulo,
                    'detalle' => 'Documentos · '.($documento->estado->value === 'activo' ? 'Activo' : 'Archivado'),
                    'fecha' => $documento->updated_at ?? $documento->created_at,
                    'href' => $request->user()->can('view', $documento) ? route('documentos.show', $documento) : null,
                ])
            );
        }
        $actividadReciente = $actividad
            ->sortByDesc(fn (array $item) => $item['fecha'])
            ->take(8)
            ->values();

        return view('pqrs.panel', [
            'resumen' => $resumen,
            'estadisticasEstados' => $estadisticasEstados ?? [],
            'estadisticasTipos' => $estadisticasTipos ?? [],
            'indicadores' => $indicadores,
            'actividadReciente' => $actividadReciente,
            'organizacion' => $contexto->organizacion,
            'copropiedad' => $contexto->copropiedad,
            'puedeCrearPqrs' => $request->user()->can('create', Pqr::class),
            'puedeConsultarPqrs' => $puedeConsultarPqrs,
            'puedeConsultarDocumentos' => $puedeConsultarDocumentos,
            'notificacionesNoLeidas' => $puedeConsultarNotificaciones
                ? $consultaNotificaciones->contarNoLeidas($contexto, $request->user())
                : null,
        ]);
    }

    public function index(
        Request $request,
        ContextoOperativo $contexto,
        ConsultaPqrsContextuales $consultaPqrs
    )
    {
        $this->authorize('viewAny', Pqr::class);

        $autorizacion = app(AutorizacionContextual::class);
        $puedeGestionar = $autorizacion->tienePermiso($contexto, 'pqrs.gestionar');

        $baseQuery = $consultaPqrs->para($contexto);
        if (! $autorizacion->tienePermiso($contexto, 'pqrs.ver_todas')) {
            $baseQuery->where('user_id', $request->user()->id);
        }

        $query = (clone $baseQuery)->with(['user', 'tipoPqr', 'assignee']);

        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }

        $hoy = app(CalendarioLaboralColombia::class)->hoy();

        if ($request->estado === 'pendientes') {
            $query->whereIn('estado', ['radicada', 'en_revision']);
        } elseif ($request->estado === 'por_vencer') {
            $query->whereIn('estado', ['radicada', 'en_revision'])
                ->whereBetween('fecha_limite_respuesta', [$hoy, $hoy->addDays(3)]);
        } elseif ($request->estado === 'vencidas') {
            $query->whereIn('estado', ['radicada', 'en_revision'])
                ->whereDate('fecha_limite_respuesta', '<', $hoy);
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo_pqr_id')) $query->where('tipo_pqr_id', $request->integer('tipo_pqr_id'));
        if ($request->filled('assigned_to_id')) $query->where('assigned_to_id', $request->integer('assigned_to_id'));
        if ($puedeGestionar && $request->filled('prioridad')) $query->where('prioridad', $request->input('prioridad'));
        if ($request->filled('desde')) $query->whereDate('fecha_radicacion', '>=', $request->date('desde'));
        if ($request->filled('hasta')) $query->whereDate('fecha_radicacion', '<=', $request->date('hasta'));

        $pqrs = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $resumen = [
            'total' => (clone $baseQuery)->count(),
            'pendientes' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])->count(),
            'respondidas' => (clone $baseQuery)->where('estado', 'respondida')->count(),
            'por_vencer' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])
                ->whereBetween('fecha_limite_respuesta', [$hoy, $hoy->addDays(3)])
                ->count(),
            'vencidas' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])
                ->whereDate('fecha_limite_respuesta', '<', $hoy)
                ->count(),
        ];

        $months = collect(range(5, 0))->map(fn ($offset) => Carbon::now()->subMonths($offset));
        $createdByMonth = (clone $baseQuery)->where('created_at', '>=', $months->first()->copy()->startOfMonth())
            ->get(['created_at'])->groupBy(fn ($pqr) => $pqr->created_at->format('Y-m'))->map->count();
        $chartData = [
            'months' => $months->map(fn ($month) => [
                'label' => $month->translatedFormat('M'),
                'value' => $createdByMonth->get($month->format('Y-m'), 0),
            ]),
            'states' => (clone $baseQuery)->select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')->pluck('total', 'estado'),
        ];

        $tipos = TipoPqr::orderBy('nombre')->get();
        $gestores = User::query()->whereHas('membresiasCopropiedad', fn ($query) => $query->where('organizacion_id', $contexto->organizacion->id)->where('copropiedad_id', $contexto->copropiedad->id)->where('estado', 'activa'))->orderBy('name')->get();
        return view('pqrs.index', compact('pqrs', 'resumen', 'chartData', 'tipos', 'gestores', 'puedeGestionar'));
    }

    public function create()
    {
        $this->authorize('create', Pqr::class);
        $tipos = TipoPqr::orderBy('nombre')->get();

        return view('pqrs.create', compact('tipos'));
    }

    public function store(Request $request, ContextoOperativo $contexto, PresentarPqrs $presentarPqrs)
    {
        $this->authorize('create', Pqr::class);
        $request->validate([
            'asunto' => 'required|string|max:150',
            'descripcion' => 'required|string',
            'tipo_pqr_id' => 'required|exists:tipo_pqrs,id',
            'prioridad' => ['nullable', 'in:alta,media,baja'],
            'adjuntos' => ['nullable', 'array', 'max:8'],
            'adjuntos.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip'],
        ], [
            'asunto.required' => 'El asunto es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'tipo_pqr_id.required' => 'El tipo de solicitud es obligatorio.',
            'string' => 'El :attribute debe ser texto.',
            'max.string' => 'El :attribute no debe superar :max caracteres.',
            'date' => 'La :attribute debe ser una fecha válida.',
            'exists' => 'El :attribute seleccionado no es válido.',
            'array' => 'Los :attribute deben enviarse como una lista.',
            'max.array' => 'No puedes adjuntar más de :max archivos.',
            'file' => 'Cada :attribute debe ser un archivo válido.',
            'max.file' => 'Cada :attribute no debe superar 10 MB.',
            'mimes' => 'Cada :attribute debe tener un formato permitido.',
        ], [
            'asunto' => 'asunto',
            'descripcion' => 'descripción',
            'tipo_pqr_id' => 'tipo de solicitud',
            'adjuntos' => 'archivos adjuntos',
            'adjuntos.*' => 'archivo adjunto',
        ]);

        $pqr = $presentarPqrs->ejecutar(
            $contexto,
            $request->user(),
            $request->only(['asunto', 'descripcion', 'tipo_pqr_id']),
            $request->file('adjuntos', []),
        );

        return redirect()->route('pqrs.show', $pqr)->with('success', 'PQR radicada correctamente. Ya no puede ser modificada.');
    }

    public function show(
        Request $request,
        Pqr $pqr,
        ContextoOperativo $contexto,
        ConsultaParticipacionContextual $consultaParticipacion,
        VisibilidadBorradoresPqrs $visibilidadBorradores,
        \App\Application\Pqrs\ConsultaExpedienteIntegrado $consultaExpediente,
    ) {
        $this->authorize('view', $pqr);
        $puedeGestionar = app(AutorizacionContextual::class)->puedeGestionarPqr($contexto, $pqr);
        $pqr->load([
            'user', 'tipoPqr', 'attachments', 'assignee', 'tags', 'satisfactionSurvey',
            'replies' => fn ($query) => $visibilidadBorradores->restringir($query, $contexto, $pqr)->with('user'),
            'activities' => fn ($query) => $query->when(! $puedeGestionar, fn ($query) => $query->whereIn('action', PqrActivity::PUBLIC_ACTIONS))->with('user'),
        ]);
        if ($puedeGestionar) {
            $pqr->load('internalComments.user');
        }
        $participacion = $consultaParticipacion->para($pqr->user, $contexto);
        $templates = $puedeGestionar ? ResponseTemplate::orderBy('name')->get() : collect();
        $availableTags = $puedeGestionar
            ? PqrTag::where('organizacion_id', $contexto->organizacion->id)
                ->where('copropiedad_id', $contexto->copropiedad->id)
                ->where(fn ($query) => $query->where('activo', true)->orWhereIn('id', $pqr->tags->modelKeys()))
                ->orderBy('name')
                ->get()
            : collect();
        $operationKey = function (string $operation, int $reference) use ($request): string {
            $slot = "pqr_operation_keys.{$operation}.{$reference}";

            return $request->session()->get($slot, fn () => tap((string) Str::uuid(), fn (string $uuid) => $request->session()->put($slot, $uuid)));
        };
        $replyOperationKeys = $puedeGestionar ? [
            'create_draft' => $operationKey('create_draft', $pqr->id),
            'send_reply' => $operationKey('send_reply', $pqr->id),
            'drafts' => $pqr->replies->where('is_draft', true)->where('user_id', $request->user()->id)->mapWithKeys(fn (PqrReply $reply) => [$reply->id => [
                'update_draft' => $operationKey('update_draft', $reply->id),
                'send_draft' => $operationKey('send_draft', $reply->id),
                'delete_draft' => $operationKey('delete_draft', $reply->id),
            ]])->all(),
        ] : [];

        $proximoPaso = match ($pqr->estado) {
            'radicada' => $puedeGestionar ? 'Revisar la PQRS y asignar responsable cuando corresponda.' : 'El equipo revisará la PQRS radicada.',
            'en_revision' => $puedeGestionar ? 'Actualizar la gestión o enviar una respuesta al radicador.' : 'El equipo continúa revisando esta PQRS.',
            'respondida' => 'Consulta la respuesta registrada y su seguimiento.',
            'cerrada' => 'La PQRS está cerrada; consulta el historial si necesitas verificar la gestión.',
        };

        // Integración de módulos (Sprint 19): expediente integrado, generación
        // de mantenimiento desde la PQRS y exportación del expediente.
        $expediente = $consultaExpediente->para($request->user(), $contexto, $pqr);
        $puedeGenerarMantenimiento = $puedeGestionar
            && $request->user()->can('create', \App\Models\Mantenimiento::class);
        $puedeExportarExpediente = app(AutorizacionContextual::class)->tienePermiso($contexto, 'informes.exportar');

        return view('pqrs.show', compact('pqr', 'participacion', 'templates', 'availableTags', 'puedeGestionar', 'proximoPaso', 'replyOperationKeys', 'expediente', 'puedeGenerarMantenimiento', 'puedeExportarExpediente'));
    }

    public function edit(Pqr $pqr, ContextoOperativo $contexto)
    {
        $this->authorize('update', $pqr);
        $tipos = TipoPqr::orderBy('nombre')->get();
        $gestores = User::query()->whereHas('membresiasCopropiedad', fn ($query) => $query->where('organizacion_id', $contexto->organizacion->id)->where('copropiedad_id', $contexto->copropiedad->id)->where('estado', 'activa'))->orderBy('name')->get();

        return view('pqrs.edit', compact('pqr', 'tipos', 'gestores'));
    }

    public function update(Request $request, Pqr $pqr, ContextoOperativo $contexto, ActualizarPqrs $actualizarPqrs)
    {
        $this->authorize('update', $pqr);
        $request->validate([
            'asunto' => 'required|string|max:150',
            'descripcion' => 'required|string',
            'estado' => 'required|in:radicada,en_revision,respondida,cerrada',
            'tipo_pqr_id' => 'required|exists:tipo_pqrs,id',
            'assigned_to_id' => ['nullable', 'integer'],
            'prioridad' => ['nullable', 'in:alta,media,baja'],
            'adjuntos' => ['nullable', 'array', 'max:8'],
            'adjuntos.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip'],
        ]);

        $actualizarPqrs->ejecutar(
            $contexto,
            $request->user(),
            $pqr,
            $request->only(['asunto', 'descripcion', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad']),
            $request->file('adjuntos', []),
        );

        return redirect()->route('pqrs.index')->with('success', 'PQR actualizada correctamente.');
    }

    public function destroy(Pqr $pqr, ContextoOperativo $contexto, ConsultaPqrsContextuales $consultaPqrs)
    {
        $pqr = $consultaPqrs->resolver($contexto, $pqr->getKey());
        $this->authorize('delete', $pqr);
        $deletion = DB::transaction(function () use ($pqr): array {
            $locked = Pqr::query()->lockForUpdate()->findOrFail($pqr->id);
            $protected = $locked->replies()->exists()
                || $locked->internalComments()->exists()
                || $locked->activities()->exists()
                || $locked->communicationOperations()->exists();
            if ($protected) {
                return ['blocked' => true, 'paths' => []];
            }
            $paths = $locked->attachments()->pluck('path')->all();
            $locked->delete();

            return ['blocked' => false, 'paths' => $paths];
        });
        if ($deletion['blocked']) {
            return redirect()->route('pqrs.edit', $pqr)->withErrors([
                'pqr' => 'Esta PQRS conserva actuaciones o comunicaciones y no puede eliminarse físicamente.',
            ]);
        }
        foreach ($deletion['paths'] as $path) {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('pqrs.index')->with('success', 'PQR eliminada correctamente.');
    }
}
