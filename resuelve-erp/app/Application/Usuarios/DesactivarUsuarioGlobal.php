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

    public function reactivar(User $user): array
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
        // canónico registrado). Las suspensiones manuales se conservan, las
        // anteriores a esta regla no registran motivo demostrable y las
        // membresías finalizadas o vencidas nunca se convierten en activas.
        $candidatas = $user->membresiasCopropiedad()
            ->where('estado', 'suspendida')
            ->get();

        $noRestauradas = [];
        $restauradas = 0;
        foreach ($candidatas as $membresia) {
            if ($membresia->motivo_terminacion !== self::MOTIVO_SUSPENSION) {
                $motivo = $membresia->motivo_terminacion;
                $noRestauradas[] = $motivo === null || $motivo === ''
                    ? 'una suspensión sin motivo demostrable se conserva'
                    : 'la suspensión manual "'.$motivo.'" se conserva';

                continue;
            }

            if ($membresia->vigente_hasta !== null && ! $membresia->vigente_hasta->isFuture()) {
                $noRestauradas[] = 'la vigencia finalizó el '.$membresia->vigente_hasta->format('d/m/Y');

                continue;
            }

            $membresia->update([
                'estado' => 'activa',
                'motivo_terminacion' => null,
            ]);
            $restauradas++;
        }

        return [
            'restauradas' => $restauradas,
            'no_restauradas' => $noRestauradas,
        ];
    }

    /** Texto de resultado de la restauración para el mensaje de la operación. */
    public static function resumen(array $resultado): string
    {
        $texto = 'Membresías restauradas: '.$resultado['restauradas'].'.';
        if ($resultado['no_restauradas'] !== []) {
            $texto .= ' No restauradas: '.implode('; ', $resultado['no_restauradas']).'.';
        }

        return $texto;
    }
}
