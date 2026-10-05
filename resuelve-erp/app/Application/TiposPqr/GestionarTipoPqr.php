<?php

namespace App\Application\TiposPqr;

use App\Models\AuditLog;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administración global del catálogo de Tipos de PQRS (Petición, Queja,
 * Reclamo, Sugerencia, ...). El catálogo es global: solo la autoridad de
 * plataforma puede gestionarlo. Sin campo de estado. La eliminación se bloquea
 * cuando el tipo está en uso (PQRS o reglas automáticas) para no disparar las
 * cascadas destructivas definidas en el esquema.
 */
final class GestionarTipoPqr
{
    public function crear(User $operador, array $datos): TipoPqr
    {
        $this->autorizar($operador);
        $datos = $this->validar($datos);

        $tipo = TipoPqr::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
        $this->auditar($operador, 'tipo_pqr.store', $tipo, ['nombre' => $tipo->nombre]);

        return $tipo;
    }

    public function editar(User $operador, TipoPqr $tipo, array $datos): TipoPqr
    {
        $this->autorizar($operador);
        $datos = $this->validar($datos, $tipo);
        $anterior = $tipo->nombre;

        $tipo->update([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
        $this->auditar($operador, 'tipo_pqr.update', $tipo, [
            'nombre_anterior' => $anterior,
            'nombre' => $tipo->nombre,
        ]);

        return $tipo->refresh();
    }

    public function eliminar(User $operador, TipoPqr $tipo): void
    {
        $this->autorizar($operador);

        $enPqrs = Pqr::query()->where('tipo_pqr_id', $tipo->id)->count();
        $enReglas = DB::table('automation_rules')->where('tipo_pqr_id', $tipo->id)->count();
        if ($enPqrs + $enReglas > 0) {
            $motivos = [];
            if ($enPqrs > 0) {
                $motivos[] = "{$enPqrs} PQRS";
            }
            if ($enReglas > 0) {
                $motivos[] = "{$enReglas} reglas automáticas";
            }

            throw ValidationException::withMessages([
                'tipo' => "El tipo \"{$tipo->nombre}\" está en uso (".implode(', ', $motivos).') y no puede eliminarse.',
            ]);
        }

        $this->auditar($operador, 'tipo_pqr.delete', $tipo, ['nombre' => $tipo->nombre]);
        $tipo->delete();
    }

    private function validar(array $datos, ?TipoPqr $actual = null): array
    {
        return Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipo_pqrs', 'nombre')->ignore($actual?->id)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], [
            'nombre.required' => 'El nombre del tipo es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de PQRS con ese nombre.',
        ])->validate();
    }

    private function autorizar(User $operador): void
    {
        if (! $operador->esAdministradorSistema()) {
            throw new AuthorizationException('Solo la autoridad de plataforma puede gestionar los Tipos de PQRS.');
        }
    }

    private function auditar(User $operador, string $accion, TipoPqr $tipo, array $metadata): void
    {
        AuditLog::create([
            'user_id' => $operador->id,
            'action' => $accion,
            'auditable_type' => TipoPqr::class,
            'auditable_id' => $tipo->id,
            'ip_address' => request()->ip(),
            'metadata' => $metadata,
        ]);
    }
}
