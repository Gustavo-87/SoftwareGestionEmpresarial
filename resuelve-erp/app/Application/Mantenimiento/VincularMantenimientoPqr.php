<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\RegistrarActuacionPqrs;
use App\Models\AuditLog;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vínculo entre PQRS y mantenimiento (HU-MAN-03, Sprint 19).
 *
 * Ajustes aprobados: autorización efectiva sobre ambos recursos (las policies
 * de gestión; `pqrs.listar` no es suficiente), pertenencia al contexto y
 * operación idempotente (vincular lo ya vinculado es un no-op). El vínculo se
 * administra solo antes de finalizar el mantenimiento; después es inmutable.
 */
final class VincularMantenimientoPqr
{
    public function __construct(private readonly RegistrarActuacionPqrs $actuacion) {}

    public function vincular(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento, Pqr $pqr): Mantenimiento
    {
        return DB::transaction(function () use ($operador, $contexto, $mantenimiento, $pqr): Mantenimiento {
            $this->autorizar($operador, $contexto, $mantenimiento, $pqr);

            if ($mantenimiento->estado === 'finalizado') {
                throw ValidationException::withMessages(['pqr_id' => 'Un mantenimiento finalizado conserva sus vínculos de forma inmutable.']);
            }

            if ($mantenimiento->pqr_id === $pqr->id) {
                return $mantenimiento; // idempotente
            }

            if ($mantenimiento->pqr_id !== null) {
                throw ValidationException::withMessages([
                    'pqr_id' => 'El mantenimiento ya está vinculado a otra PQRS. Retira el vínculo antes de cambiarlo.',
                ]);
            }

            $mantenimiento->update(['pqr_id' => $pqr->id]);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.vincular_pqr',
                'auditable_type' => Mantenimiento::class,
                'auditable_id' => $mantenimiento->id,
                'ip_address' => request()->ip(),
                'metadata' => ['pqr_id' => $pqr->id, 'origen' => 'vinculacion_posterior'],
            ]);

            $this->actuacion->registrar(
                $pqr,
                $operador,
                'mantenimiento_vinculado',
                'Se vinculó el mantenimiento "'.$mantenimiento->titulo.'".',
                ['mantenimiento_id' => $mantenimiento->id],
            );

            return $mantenimiento->fresh();
        });
    }

    public function desvincular(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento): Mantenimiento
    {
        return DB::transaction(function () use ($operador, $contexto, $mantenimiento): Mantenimiento {
            $pqr = $mantenimiento->pqr;
            if ($pqr === null) {
                return $mantenimiento; // idempotente
            }

            $this->autorizar($operador, $contexto, $mantenimiento, $pqr);

            if ($mantenimiento->estado === 'finalizado') {
                throw ValidationException::withMessages(['pqr_id' => 'Un mantenimiento finalizado conserva sus vínculos de forma inmutable.']);
            }

            $mantenimiento->update(['pqr_id' => null]);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.desvincular_pqr',
                'auditable_type' => Mantenimiento::class,
                'auditable_id' => $mantenimiento->id,
                'ip_address' => request()->ip(),
                'metadata' => ['pqr_id' => $pqr->id],
            ]);

            $this->actuacion->registrar(
                $pqr,
                $operador,
                'mantenimiento_desvinculado',
                'Se retiró el vínculo del mantenimiento "'.$mantenimiento->titulo.'".',
                ['mantenimiento_id' => $mantenimiento->id],
            );

            return $mantenimiento->fresh();
        });
    }

    private function autorizar(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento, Pqr $pqr): void
    {
        if (! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar vínculos.');
        }

        if (! $operador->can('update', $mantenimiento)) {
            throw new AuthorizationException('No tienes autorización para gestionar este mantenimiento.');
        }

        if (! $operador->can('update', $pqr)) {
            throw new AuthorizationException('No tienes autorización para gestionar la PQRS relacionada.');
        }
    }
}
