<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Models\MembresiaCopropiedad;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActualizarPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly RegistrarActuacionPqrs $actuaciones,
        private readonly EmitirNotificacionPqrs $notificaciones,
    ) {}

    /** @param iterable<object> $adjuntos */
    public function ejecutar(ContextoOperativo $contexto, User $usuario, Pqr $pqr, array $datos, iterable $adjuntos = []): Pqr
    {
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException();
        }

        $datosPermitidos = array_intersect_key($datos, array_flip([
            'asunto', 'descripcion', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad',
        ]));
        $this->validarResponsable($contexto, $datosPermitidos['assigned_to_id'] ?? null);

        $resultado = DB::transaction(function () use ($usuario, $pqr, $datosPermitidos, $adjuntos): array {
            $before = $pqr->only(['estado', 'assigned_to_id', 'prioridad']);
            $pqr->update($datosPermitidos);
            $this->guardarAdjuntos($pqr, $adjuntos);

            // Actuación principal: solo estado y responsable (sin prioridad).
            $changes = [];
            if ($before['estado'] !== $pqr->estado) {
                $changes[] = "Cambió el estado a {$pqr->estado_label}.";
            }
            if ((int) $before['assigned_to_id'] !== (int) $pqr->assigned_to_id) {
                $changes[] = 'Asignó la solicitud a '.($pqr->assignee?->name ?? 'Sin responsable').'.';
            }

            if ($changes) {
                $this->actuaciones->registrar(
                    $pqr,
                    $usuario,
                    'updated',
                    implode(' ', $changes),
                    ['before' => $before],
                );
            } elseif ($before['prioridad'] === $pqr->prioridad) {
                $this->actuaciones->registrar(
                    $pqr,
                    $usuario,
                    'updated',
                    'Actualizó la información de la solicitud.',
                    ['before' => $before],
                );
            }

            // Actuación separada para prioridad (siempre interna).
            if ($before['prioridad'] !== $pqr->prioridad) {
                $this->actuaciones->registrar(
                    $pqr,
                    $usuario,
                    'priority_changed',
                    "Cambió la prioridad a {$pqr->prioridad_label}.",
                    ['before' => ['prioridad' => $before['prioridad']]],
                );
            }

            return [$pqr, $before];
        });

        [$pqr, $before] = $resultado;
        if ($before['estado'] !== $pqr->estado) {
            $this->notificaciones->ejecutar($contexto, $pqr, 'pqr_estado_actualizado');
        }
        if ((int) $before['assigned_to_id'] !== (int) $pqr->assigned_to_id && $pqr->assignee) {
            $this->notificaciones->ejecutar($contexto, $pqr, 'pqr_asignada');
        }

        return $pqr;
    }

    private function validarResponsable(ContextoOperativo $contexto, mixed $responsableId): void
    {
        if ($responsableId === null) {
            return;
        }

        $esVigente = MembresiaCopropiedad::query()
            ->where('usuario_id', $responsableId)
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
            ->exists();

        if (! $esVigente) {
            throw ValidationException::withMessages(['assigned_to_id' => ['El responsable seleccionado no es válido.']]);
        }
    }

    /** @param iterable<object> $adjuntos */
    private function guardarAdjuntos(Pqr $pqr, iterable $adjuntos): void
    {
        foreach ($adjuntos as $adjunto) {
            $path = $adjunto->store("pqrs/{$pqr->id}");
            $pqr->attachments()->create([
                'original_name' => $adjunto->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $adjunto->getMimeType() ?: 'application/octet-stream',
                'size' => $adjunto->getSize(),
            ]);
        }
    }
}
