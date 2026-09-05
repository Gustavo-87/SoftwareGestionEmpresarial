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

final class AplicarAccionRapidaPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly RegistrarActuacionPqrs $actuaciones,
        private readonly EmitirNotificacionPqrs $notificaciones,
    ) {}

    public function ejecutar(ContextoOperativo $contexto, User $usuario, Pqr $pqr, array $datos): Pqr
    {
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException();
        }

        $this->validarResponsable($contexto, $datos['assigned_to_id'] ?? null);

        [$pqr, $before] = DB::transaction(function () use ($usuario, $pqr, $datos): array {
            $before = $pqr->only(['estado', 'assigned_to_id', 'prioridad']);
            $pqr->update($datos);

            // Actuación principal: solo estado y responsable (sin prioridad).
            $description = 'Aplicó una acción rápida: estado '.$pqr->estado_label.', responsable '.($pqr->assignee?->name ?? 'sin asignar').'.';
            $this->actuaciones->registrar($pqr, $usuario, 'quick_action', $description);

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

        if (($before['estado'] ?? null) !== $pqr->estado) {
            $this->notificaciones->ejecutar($contexto, $pqr, 'pqr_estado_actualizado');
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
}
