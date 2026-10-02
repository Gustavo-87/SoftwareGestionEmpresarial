<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Mantenimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class GestionarMantenimiento
{
    public function __construct(private readonly ConsultaMantenimientos $consulta) {}

    public function ejecutar(ContextoOperativo $contexto, Mantenimiento $mantenimiento, array $datos): void
    {
        $mantenimiento = $this->consulta->resolver($contexto, $mantenimiento->id);
        Gate::forUser($contexto->usuario)->authorize('update', $mantenimiento);
        $datos = Validator::make($datos, ['responsable_id' => 'nullable|integer', 'fecha_programada' => 'nullable|date_format:Y-m-d',
            'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'finalizado'])]])->validate();
        DB::transaction(function () use ($contexto, $mantenimiento, $datos): void {
            if (! empty($datos['responsable_id']) && ! $this->consulta->responsables($contexto)->whereKey($datos['responsable_id'])->exists()) {
                throw ValidationException::withMessages(['responsable_id' => 'Selecciona un responsable activo de la Copropiedad.']);
            }
            Mantenimiento::query()->whereKey($mantenimiento->id)->lockForUpdate()->firstOrFail()->update($datos);
        });
    }
}
