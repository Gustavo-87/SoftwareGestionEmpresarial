<?php

namespace App\Application\Usuarios;

use App\Models\User;
use InvalidArgumentException;

final class DesactivarUsuarioGlobal
{
    /**
     * Motivo canónico registrado en las Membresías suspendidas como
     * consecuencia de la desactivación de la cuenta. Permite restaurarlas al
     * reactivar sin afectar suspensiones por otros motivos (HU-USR-08).
     */
    public const MOTIVO_SUSPENSION = 'desactivacion_cuenta';

    public function ejecutar(User $user, int $solicitanteId): void
    {
        if ($user->id === $solicitanteId) {
            throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
        }

        if ($user->es_administrador_sistema) {
            $otrosAdmins = User::where('es_administrador_sistema', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otrosAdmins === 0) {
                throw new InvalidArgumentException(
                    'No se puede desactivar el último administrador del sistema.'
                );
            }
        }

        if ($user->estado === 'inactivo') {
            throw new InvalidArgumentException('El usuario ya se encuentra desactivado.');
        }

        $user->update([
            'estado' => 'inactivo',
            'desactivado_at' => now(),
        ]);

        $user->membresiasCopropiedad()
            ->where('estado', 'activa')
            ->update([
                'estado' => 'suspendida',
                'motivo_terminacion' => self::MOTIVO_SUSPENSION,
            ]);
    }

    public function reactivar(User $user): void
    {
        if ($user->estado === 'activo') {
            throw new InvalidArgumentException('El usuario ya se encuentra activo.');
        }

        $user->update([
            'estado' => 'activo',
            'desactivado_at' => null,
        ]);

        // Regla aprobada por el Product Owner: se restauran exclusivamente las
        // Membresías suspendidas como consecuencia de la desactivación (motivo
        // canónico registrado). Las suspensiones por otros motivos se
        // conservan, y las anteriores a esta regla no registran motivo
        // demostrable, por lo que tampoco se restauran automáticamente.
        $user->membresiasCopropiedad()
            ->where('estado', 'suspendida')
            ->where('motivo_terminacion', self::MOTIVO_SUSPENSION)
            ->update([
                'estado' => 'activa',
                'motivo_terminacion' => null,
            ]);
    }
}
