<?php

namespace App\Application\Usuarios;

use App\Models\User;
use InvalidArgumentException;

final class DesactivarUsuarioGlobal
{
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
            ->update(['estado' => 'suspendida']);
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
    }
}
