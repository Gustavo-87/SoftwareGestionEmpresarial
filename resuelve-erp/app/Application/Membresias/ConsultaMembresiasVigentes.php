<?php

namespace App\Application\Membresias;

use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Membresías vigentes de un Usuario (HU-API-01, Sprint 21).
 *
 * Comparte la semántica de vigencia del flujo de selección de acceso web
 * (estado `activa`, `vigente_desde` alcanzada y `vigente_hasta` abierta o
 * futura) para que el login de la API resuelva el mismo contexto operativo sin
 * depender de HTTP (ADR-008).
 */
final class ConsultaMembresiasVigentes
{
    /** @return Collection<int, MembresiaCopropiedad> */
    public function paraUsuario(User $usuario): Collection
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->with('copropiedad')
            ->orderBy('id')
            ->get();
    }
}
