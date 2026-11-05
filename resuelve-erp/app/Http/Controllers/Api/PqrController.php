<?php

namespace App\Http\Controllers\Api;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Application\Pqrs\PresentarPqrs;
use App\Application\Pqrs\VisibilidadBorradoresPqrs;
use App\Http\Controllers\Controller;
use App\Models\Pqr;
use App\Models\PqrActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PQRS por API REST v1 (HU-API-01, Sprint 21).
 *
 * Transporte HTTP que reutiliza los casos de uso, policies y autorización
 * contextual del canal web (ADR-008): el listado se restringe al contexto y a
 * los permisos del Rol activo, el detalle conserva las reglas de visibilidad
 * de borradores y de contenido interno, y la radicación delega en
 * `PresentarPqrs` sin duplicar reglas. El contexto ya fue revalidado por
 * `ResolverContextoApi` en la misma petición.
 */
final class PqrController extends Controller
{
    public function index(Request $request, ConsultaPqrsContextuales $consultaPqrs): JsonResponse
    {
        /** @var ContextoOperativo $contexto */
        $contexto = $request->attributes->get('contexto_api');

        $this->authorize('viewAny', Pqr::class);

        $autorizacion = app(AutorizacionContextual::class);

        $query = $consultaPqrs->para($contexto);
        if (! $autorizacion->tienePermiso($contexto, 'pqrs.ver_todas')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('tipo_pqr_id')) {
            $query->where('tipo_pqr_id', $request->integer('tipo_pqr_id'));
        }

        $pagina = $query
            ->with(['user', 'tipoPqr', 'assignee'])
            ->orderByDesc('fecha_radicacion')
            ->orderByDesc('id')
            ->paginate($request->integer('por_pagina', 15) > 0 ? min($request->integer('por_pagina', 15), 50) : 15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Pqr $pqr): array => $this->resumen($pqr))->values()->all(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function show(Request $request, ConsultaPqrsContextuales $consultaPqrs, int $identificador): JsonResponse
    {
        /** @var ContextoOperativo $contexto */
        $contexto = $request->attributes->get('contexto_api');

        // Mismo criterio que los bindings contextuales: una PQR de otra
        // Copropiedad responde 404.
        $pqr = $consultaPqrs->resolver($contexto, $identificador);

        $this->authorize('view', $pqr);

        $autorizacion = app(AutorizacionContextual::class);
        $puedeGestionar = $autorizacion->puedeGestionarPqr($contexto, $pqr);

        $pqr->load([
            'user', 'tipoPqr', 'assignee', 'satisfactionSurvey',
            'replies' => fn ($query) => app(VisibilidadBorradoresPqrs::class)
                ->restringir($query, $contexto, $pqr)->with('user'),
            'activities' => fn ($query) => $query
                ->when(! $puedeGestionar, fn ($query) => $query->whereIn('action', PqrActivity::PUBLIC_ACTIONS))
                ->with('user'),
        ]);
        if ($puedeGestionar) {
            $pqr->load('internalComments.user');
        }

        $detalle = $this->resumen($pqr);
        $detalle['descripcion'] = $pqr->descripcion;
        $detalle['respuestas'] = collect($pqr->replies)->map(fn ($reply): array => [
            'id' => $reply->id,
            'autor' => $reply->user?->name,
            'body' => $reply->body,
            'es_borrador' => (bool) $reply->is_draft,
            'enviada_at' => $reply->sent_at?->toIso8601String(),
            'creada_at' => $reply->created_at?->toIso8601String(),
        ])->values()->all();
        $detalle['actividades'] = collect($pqr->activities)->map(fn ($actividad): array => [
            'action' => $actividad->action,
            'descripcion' => $actividad->description,
            'autor' => $actividad->user?->name,
            'fecha' => $actividad->created_at?->toIso8601String(),
        ])->values()->all();

        // Contenido interno solo para personal con gestión efectiva (mismas
        // reglas del expediente web; nunca se filtra al resto de actores).
        $detalle['comentarios_internos'] = $puedeGestionar
            ? collect($pqr->internalComments)->map(fn ($comentario): array => [
                'id' => $comentario->id,
                'autor' => $comentario->user?->name,
                'body' => $comentario->body,
                'fecha' => $comentario->created_at?->toIso8601String(),
            ])->values()->all()
            : null;

        return response()->json(['data' => $detalle]);
    }

    public function store(Request $request, PresentarPqrs $presentarPqrs): JsonResponse
    {
        /** @var ContextoOperativo $contexto */
        $contexto = $request->attributes->get('contexto_api');

        $datos = $request->validate([
            'asunto' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string'],
            'tipo_pqr_id' => ['required', 'integer', 'exists:tipo_pqrs,id'],
        ], [
            'asunto.required' => 'El asunto es obligatorio.',
            'asunto.max' => 'El asunto no debe superar 150 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'tipo_pqr_id.required' => 'El tipo de solicitud es obligatorio.',
            'tipo_pqr_id.exists' => 'El tipo de solicitud seleccionado no es válido.',
        ]);

        $this->authorize('create', Pqr::class);

        // El caso de uso valida el permiso, radica en transacción, aplica las
        // reglas contextuales de plazo y registra la actuación (sin duplicar).
        $pqr = $presentarPqrs->ejecutar($contexto, $request->user(), $datos);

        return response()->json(['data' => $this->resumen($pqr->load(['user', 'tipoPqr']))], 201);
    }

    private function resumen(Pqr $pqr): array
    {
        return [
            'id' => $pqr->id,
            'radicado' => 'PQR-'.str_pad((string) $pqr->id, 4, '0', STR_PAD_LEFT),
            'asunto' => $pqr->asunto,
            'estado' => $pqr->estado,
            'prioridad' => $pqr->prioridad,
            'tipo' => $pqr->tipoPqr?->nombre,
            'fecha_radicacion' => $pqr->fecha_radicacion?->toDateString(),
            'fecha_limite_respuesta' => $pqr->fecha_limite_respuesta?->toDateString(),
            'radicador' => $pqr->user?->name,
            'responsable' => $pqr->assignee?->name,
        ];
    }
}
