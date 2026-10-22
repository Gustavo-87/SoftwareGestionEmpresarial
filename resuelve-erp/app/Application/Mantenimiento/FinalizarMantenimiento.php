<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\RegistrarActuacionPqrs;
use App\Models\AuditLog;
use App\Models\Mantenimiento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finalización del mantenimiento (HU-MAN-04, Sprint 19).
 *
 * Exige estado `en_proceso`, resultado público registrado y al menos una
 * evidencia documental válida (versión `aprobada`, HU-DOC-03). **Finalizar no
 * responde ni cierra automáticamente la PQRS** (ajuste del flujo aprobado):
 * la respuesta y el cierre se realizan con las operaciones existentes.
 * La operación es atómica e idempotente ante repetición.
 */
final class FinalizarMantenimiento
{
    public function __construct(private readonly RegistrarActuacionPqrs $actuacion) {}

    public function ejecutar(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento): Mantenimiento
    {
        return DB::transaction(function () use ($operador, $contexto, $mantenimiento): Mantenimiento {
            if (! $contexto->tieneMembresiaContextual()) {
                throw new AuthorizationException('Se requiere una Membresía vigente para finalizar mantenimientos.');
            }

            if (! $operador->can('update', $mantenimiento)) {
                throw new AuthorizationException('No tienes autorización para gestionar este mantenimiento.');
            }

            if ($mantenimiento->estado === 'finalizado') {
                return $mantenimiento->fresh(); // idempotente: ya finalizado
            }

            if ($mantenimiento->estado !== 'en_proceso') {
                throw ValidationException::withMessages([
                    'estado' => 'Solo se puede finalizar un mantenimiento en proceso (estado actual: '.$mantenimiento->etiquetaEstado().').',
                ]);
            }

            if (trim((string) $mantenimiento->resultado_publico) === '') {
                throw ValidationException::withMessages([
                    'resultado_publico' => 'Registra el resultado público antes de finalizar el mantenimiento.',
                ]);
            }

            $evidencias = $mantenimiento->evidencias()->with('version')->get();
            $valida = $evidencias->contains(
                fn ($evidencia) => $evidencia->version !== null && $evidencia->version->estado->value === 'aprobada'
            );

            if (! $valida) {
                throw ValidationException::withMessages([
                    'evidencia' => 'Vincula al menos una evidencia documental con versión aprobada antes de finalizar.',
                ]);
            }

            $antes = $mantenimiento->estado;
            $mantenimiento->update([
                'estado' => 'finalizado',
                'finalizado_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.finalizar',
                'auditable_type' => Mantenimiento::class,
                'auditable_id' => $mantenimiento->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'estado_anterior' => $antes,
                    'estado_posterior' => 'finalizado',
                    'pqr_id' => $mantenimiento->pqr_id,
                    'evidencias' => $evidencias->pluck('id')->all(),
                ],
            ]);

            // Trazabilidad en la PQRS sin alterar su estado: la respuesta y el
            // cierre de la PQRS son operaciones separadas y explícitas.
            if ($mantenimiento->pqr_id !== null) {
                $pqr = $mantenimiento->pqr;
                if ($pqr !== null) {
                    $this->actuacion->registrar(
                        $pqr,
                        $operador,
                        'mantenimiento_finalizado',
                        'Finalizó el mantenimiento "'.$mantenimiento->titulo.'". La PQRS no se cierra automáticamente.',
                        ['mantenimiento_id' => $mantenimiento->id],
                    );
                }
            }

            return $mantenimiento->fresh();
        });
    }
}
