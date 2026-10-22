<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\Mantenimiento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registro del resultado del mantenimiento (HU-MAN-04, Sprint 19).
 *
 * Ajuste aprobado: el resultado público se separa del resultado interno. El
 * público es la información comunicable al residente; el interno permanece
 * reservado al personal con gestión. La operación exige gestión efectiva del
 * mantenimiento y pertenencia al contexto.
 */
final class RegistrarResultadoMantenimiento
{
    public function ejecutar(
        User $operador,
        ContextoOperativo $contexto,
        Mantenimiento $mantenimiento,
        ?string $resultadoPublico,
        ?string $resultadoInterno,
    ): Mantenimiento {
        return DB::transaction(function () use ($operador, $contexto, $mantenimiento, $resultadoPublico, $resultadoInterno): Mantenimiento {
            $this->autorizar($operador, $contexto, $mantenimiento);

            if ($mantenimiento->estado === 'finalizado') {
                throw ValidationException::withMessages(['resultado_publico' => 'Un mantenimiento finalizado conserva su resultado de forma inmutable.']);
            }

            $publico = trim((string) $resultadoPublico);
            $interno = trim((string) $resultadoInterno);
            if ($publico === '' && $interno === '') {
                throw ValidationException::withMessages([
                    'resultado_publico' => 'Registra al menos el resultado público o el resultado interno.',
                ]);
            }

            $mantenimiento->update([
                'resultado_publico' => $publico !== '' ? $publico : null,
                'resultado_interno' => $interno !== '' ? $interno : null,
                'resultado_registrado_por_user_id' => $operador->id,
                'resultado_registrado_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.resultado',
                'auditable_type' => Mantenimiento::class,
                'auditable_id' => $mantenimiento->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'resultado_publico_registrado' => $publico !== '',
                    'resultado_interno_registrado' => $interno !== '',
                    'estado' => $mantenimiento->estado,
                ],
            ]);

            return $mantenimiento->fresh();
        });
    }

    private function autorizar(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento): void
    {
        if (! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para registrar resultados.');
        }

        if (! $operador->can('update', $mantenimiento)) {
            throw new AuthorizationException('No tienes autorización para gestionar este mantenimiento.');
        }
    }
}
