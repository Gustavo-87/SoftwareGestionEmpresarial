<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\PqrActivity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Expediente integrado de una PQRS (HU-REP-03 y HU-REP-05, Sprint 19).
 *
 * Compone la PQRS con los mantenimientos vinculados y su evidencia documental
 * aplicando las policies existentes en cada recurso. El resultado público se
 * separa del interno: el interno solo se expone a quien gestiona el
 * mantenimiento o tiene `mantenimiento.ver_todas`. Los documentos restringidos
 * no filtran metadatos (ni título, ni versión, ni archivo, ni fechas).
 */
final class ConsultaExpedienteIntegrado
{
    public function para(User $espectador, ContextoOperativo $contexto, Pqr $pqr): array
    {
        if (! $espectador->can('view', $pqr)) {
            throw new AuthorizationException('No tienes autorización para consultar esta PQRS.');
        }

        $puedeGestionarPqr = $espectador->can('update', $pqr);

        // Se reutilizan las relaciones que el controlador ya cargó (sin
        // crecimiento N+1) y se vuelve a aplicar la visibilidad en colección:
        // los borradores y las actuaciones internas nunca salen por aquí.
        $respuestas = $pqr->loadMissing('replies')->replies
            ->filter(fn ($respuesta) => ! $respuesta->is_draft)
            ->sortBy('sent_at')
            ->values();
        $actividades = $pqr->loadMissing('activities')->activities
            ->filter(fn ($actividad) => $puedeGestionarPqr || in_array($actividad->action, PqrActivity::PUBLIC_ACTIONS, true))
            ->sortByDesc('created_at')
            ->values();

        $expediente = [
            'pqr' => $pqr->loadMissing(['tipoPqr', 'user', 'assignee']),
            'puede_gestionar_pqr' => $puedeGestionarPqr,
            'respuestas' => $respuestas,
            'actividades' => $actividades,
            'mantenimientos' => [],
        ];

        $vinculados = Mantenimiento::query()
            ->where('pqr_id', $pqr->id)
            ->with(['responsable', 'evidencias.version', 'evidencias.documento'])
            ->orderBy('created_at')
            ->get();

        foreach ($vinculados as $mantenimiento) {
            if (! $espectador->can('view', $mantenimiento)) {
                continue;
            }

            $gestiona = $espectador->can('update', $mantenimiento)
                || app(AutorizacionContextual::class)->tienePermiso($contexto, 'mantenimiento.ver_todas');

            $visibles = [];
            $restringidas = false;
            foreach ($mantenimiento->evidencias as $evidencia) {
                $documento = $evidencia->documento;
                if ($documento === null || ! $espectador->can('view', $documento)) {
                    // Ajuste aprobado: sin metadatos para quien no tiene acceso.
                    $restringidas = true;

                    continue;
                }

                $visibles[] = [
                    'evidencia_id' => $evidencia->id,
                    'documento_id' => $documento->id,
                    'documento' => $documento->titulo,
                    'version' => $evidencia->version?->numero,
                    'version_estado' => $evidencia->version?->estado->value,
                    'vinculada' => $evidencia->created_at,
                ];
            }

            // La separación público/interno se impone aquí: el residente solo
            // recibe los campos expresamente autorizados.
            $datos = $gestiona
                ? [
                    'id' => $mantenimiento->id,
                    'titulo' => $mantenimiento->titulo,
                    'estado' => $mantenimiento->estado,
                    'estado_label' => $mantenimiento->etiquetaEstado(),
                    'responsable' => $mantenimiento->responsable?->name,
                    'fecha_programada' => $mantenimiento->fecha_programada,
                    'finalizado_at' => $mantenimiento->finalizado_at,
                    'resultado_publico' => $mantenimiento->resultado_publico,
                    'resultado_interno' => $mantenimiento->resultado_interno,
                ]
                : [
                    'id' => $mantenimiento->id,
                    'estado' => $mantenimiento->estado,
                    'estado_label' => $mantenimiento->etiquetaEstado(),
                    'fecha_programada' => $mantenimiento->fecha_programada,
                    'finalizado_at' => $mantenimiento->finalizado_at,
                    'resultado_publico' => $mantenimiento->resultado_publico,
                ];

            $expediente['mantenimientos'][] = [
                'datos' => $datos,
                'puede_gestionar' => $gestiona,
                'evidencias' => $visibles,
                'evidencias_restringidas' => $restringidas,
            ];
        }

        return $expediente;
    }
}
