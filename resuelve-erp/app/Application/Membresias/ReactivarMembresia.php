<?php

namespace App\Application\Membresias;

use App\Application\Usuarios\DesactivarUsuarioGlobal;
use App\Models\MembresiaCopropiedad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reactivación de Membresías suspendidas (decisión del Product Owner,
 * 21 de octubre de 2026, ampliando la regla HU-USR-08).
 *
 * Reactiva las Membresías suspendidas —tanto las suspendidas manualmente como
 * las suspendidas por desactivación de la cuenta (motivo canónico
 * `desactivacion_cuenta`)— sin convertir automáticamente Membresías
 * finalizadas o vencidas en activas. Conserva los Roles asignados y exige
 * usuario activo, coherencia de contexto y Organización/Copropiedad activas.
 * Cuando la operación se bloquea, el motivo concreto se expresa en el error.
 *
 * La suspensión manual corta `vigente_hasta` al suspender; al reactivar se
 * restaura la vigencia anterior (si se conoce por la auditoría) o se reabre de
 * forma indefinida.
 */
final class ReactivarMembresia
{
    /**
     * @param  string|null  $vigenciaAnterior  `vigente_hasta` previo a la
     *     suspensión, según la auditoría (`membresia.suspend` → before).
     */
    public function ejecutar(MembresiaCopropiedad $membresia, ?string $vigenciaAnterior = null): MembresiaCopropiedad
    {
        return DB::transaction(function () use ($membresia, $vigenciaAnterior): MembresiaCopropiedad {
            $membresia->loadMissing(['usuario', 'organizacion', 'copropiedad']);

            if ($membresia->estado === 'activa') {
                throw new InvalidArgumentException('La membresía ya se encuentra activa.');
            }

            if ($membresia->estado === 'finalizada') {
                throw new InvalidArgumentException(
                    'La membresía está finalizada'
                    .($membresia->motivo_terminacion ? ' (motivo: '.$membresia->motivo_terminacion.')' : '')
                    .'. El diseño aprobado no permite reactivar membresías finalizadas.'
                );
            }

            if ($membresia->estado !== 'suspendida') {
                throw new InvalidArgumentException('Solo se pueden reactivar membresías suspendidas.');
            }

            if ($membresia->usuario === null || $membresia->usuario->estado !== 'activo') {
                throw new InvalidArgumentException(
                    'No se puede reactivar: el usuario está desactivado. Reactiva primero su cuenta.'
                );
            }

            if ($membresia->copropiedad === null
                || (int) $membresia->copropiedad->organizacion_id !== (int) $membresia->organizacion_id) {
                throw new InvalidArgumentException(
                    'No se puede reactivar: la membresía no corresponde a la Organización de su Copropiedad.'
                );
            }

            if ($membresia->organizacion === null || $membresia->organizacion->estado !== 'activa') {
                throw new InvalidArgumentException(
                    'No se puede reactivar: la Organización "'.$membresia->organizacion?->nombre.'" está inactiva.'
                );
            }

            if ($membresia->copropiedad->estado !== 'activa') {
                throw new InvalidArgumentException(
                    'No se puede reactivar: la Copropiedad "'.$membresia->copropiedad->nombre.'" está inactiva.'
                );
            }

            $vigenteHasta = $this->vigenciaObjetivo($membresia, $vigenciaAnterior);

            if ($vigenteHasta !== null && ! $vigenteHasta->isFuture()) {
                throw new InvalidArgumentException(
                    'No se puede reactivar: la vigencia de la membresía finalizó el '
                    .$vigenteHasta->format('d/m/Y').'.'
                );
            }

            // Se conservan los Roles asignados: solo cambian estado, motivo y
            // vigencia. La restauración nunca convierte finalizadas o vencidas.
            $membresia->update([
                'estado' => 'activa',
                'motivo_terminacion' => null,
                'vigente_hasta' => $vigenteHasta,
            ]);

            return $membresia->fresh();
        });
    }

    /**
     * La suspensión por desactivación de cuenta no alteró la vigencia: se
     * conserva. La suspensión manual puede haberla cortado al aplicarse: si la
     * vigencia actual sigue siendo válida se conserva; si no, se restaura la
     * vigencia previa conocida por la auditoría o se reabre de forma
     * indefinida. Una vigencia anterior ya vencida se conserva como tal y
     * bloquea la reactivación.
     */
    private function vigenciaObjetivo(MembresiaCopropiedad $membresia, ?string $vigenciaAnterior)
    {
        if ($membresia->motivo_terminacion === DesactivarUsuarioGlobal::MOTIVO_SUSPENSION
            || $membresia->vigente_hasta === null
            || $membresia->vigente_hasta->isFuture()) {
            return $membresia->vigente_hasta;
        }

        return $vigenciaAnterior === null ? null : \Illuminate\Support\Carbon::parse($vigenciaAnterior);
    }
}
