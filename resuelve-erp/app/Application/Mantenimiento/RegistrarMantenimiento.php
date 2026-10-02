<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Mantenimiento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RegistrarMantenimiento
{
    public function ejecutar(ContextoOperativo $contexto, array $datos): Mantenimiento
    {
        Gate::forUser($contexto->usuario)->authorize('create', Mantenimiento::class);
        $datos = Validator::make($datos, ['titulo' => 'required|string|max:180', 'descripcion' => 'required|string|max:5000'])->validate();
        $mantenimiento = new Mantenimiento($datos);
        $mantenimiento->forceFill(['organizacion_id' => $contexto->organizacion->id,
            'copropiedad_id' => $contexto->copropiedad->id, 'solicitante_id' => $contexto->usuario->id, 'estado' => 'pendiente'])->save();

        return $mantenimiento;
    }
}
