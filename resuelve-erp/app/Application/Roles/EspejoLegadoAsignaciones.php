<?php

namespace App\Application\Roles;

use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Mantiene el espejo legado (`membresia_copropiedad_rol`) únicamente para los
 * Roles que tienen contraparte en `roles_contextuales`. Los Roles personalizados
 * de Spatie no se fuerzan al espejo y quedan fuera del diagnóstico de
 * equivalencia.
 */
final class EspejoLegadoAsignaciones
{
    public function asignar(MembresiaCopropiedad $membresia, SpatieRole $rol, int $asignadoPor): void
    {
        $rolLegado = $this->contraparte($rol);
        if ($rolLegado === null) {
            return;
        }

        $activa = DB::table('membresia_copropiedad_rol')
            ->where('membresia_copropiedad_id', $membresia->id)
            ->where('rol_id', $rolLegado->id)
            ->where('estado', 'activa')
            ->exists();

        if ($activa) {
            return;
        }

        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rolLegado->id,
            'organizacion_id' => $membresia->organizacion_id,
            'copropiedad_id' => $membresia->copropiedad_id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'asignado_por' => $asignadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function revocar(MembresiaCopropiedad $membresia, SpatieRole $rol): void
    {
        $rolLegado = $this->contraparte($rol);
        if ($rolLegado === null) {
            return;
        }

        DB::table('membresia_copropiedad_rol')
            ->where('membresia_copropiedad_id', $membresia->id)
            ->where('rol_id', $rolLegado->id)
            ->where('estado', 'activa')
            ->delete();
    }

    private function contraparte(SpatieRole $rol): ?Rol
    {
        return Rol::query()
            ->where('clave', $rol->name)
            ->where('ambito_aplicable', 'copropiedad')
            ->where('estado', 'activo')
            ->first();
    }
}
