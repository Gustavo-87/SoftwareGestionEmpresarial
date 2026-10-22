<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Domain\Residencia\Enums\TipoUnidadEnum;
use App\Models\AuditLog;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de Unidades Privadas (HU-RYP-01, Sprint 20).
 *
 * Reutiliza el modelo y la consulta contextual del Sprint 6. La unicidad del
 * código es por Copropiedad (índice `unidades_copropiedad_codigo_unique`) y el
 * contexto jamás se acepta del payload. Operar unidades residenciales no toca
 * Membresías, Roles ni permisos de acceso: son conceptos de dominio separados
 * (regla transversal del Sprint 20).
 */
final class GestionarUnidadPrivada
{
    public function crear(ContextoOperativo $contexto, User $operador, array $datos): UnidadPrivada
    {
        AutorizacionResidencia::exigirGestion($contexto);
        $validados = $this->validar($contexto, $datos);

        $unidad = new UnidadPrivada();
        $unidad->forceFill([
            'organizacion_id' => $contexto->organizacion->id,
            'copropiedad_id' => $contexto->copropiedad->id,
            ...$validados,
        ]);

        try {
            $unidad->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->errorCodigoDuplicado();
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.unidad.crear',
            'auditable_type' => UnidadPrivada::class,
            'auditable_id' => $unidad->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'codigo' => $unidad->codigo,
                'contexto' => ['organizacion_id' => $unidad->organizacion_id, 'copropiedad_id' => $unidad->copropiedad_id],
            ],
        ]);

        return $unidad;
    }

    public function actualizar(ContextoOperativo $contexto, User $operador, UnidadPrivada $unidad, array $datos): UnidadPrivada
    {
        AutorizacionResidencia::exigirGestion($contexto);
        $validados = $this->validar($contexto, $datos, $unidad->id);

        $unidad->forceFill($validados);

        try {
            $unidad->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->errorCodigoDuplicado();
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.unidad.actualizar',
            'auditable_type' => UnidadPrivada::class,
            'auditable_id' => $unidad->id,
            'ip_address' => request()->ip(),
            'metadata' => ['codigo' => $unidad->codigo, 'cambios' => array_keys($unidad->getChanges())],
        ]);

        return $unidad;
    }

    /** @return array{codigo:string,numero_nombre:?string,torre_bloque:?string,tipo:?string} */
    /**
     * Desactivación administrativa sin eliminación física: los vínculos se
     * conservan íntegros y no se concluyen automáticamente.
     */
    public function desactivar(ContextoOperativo $contexto, User $operador, UnidadPrivada $unidad): UnidadPrivada
    {
        AutorizacionResidencia::exigirGestion($contexto);

        if ($unidad->estado === 'desactivada') {
            return $unidad; // idempotente
        }

        $unidad->forceFill(['estado' => 'desactivada', 'desactivada_at' => now()])->save();

        $this->auditarEstado($operador, $unidad, 'residencia.unidad.desactivar');

        return $unidad;
    }

    public function reactivar(ContextoOperativo $contexto, User $operador, UnidadPrivada $unidad): UnidadPrivada
    {
        AutorizacionResidencia::exigirGestion($contexto);

        if ($unidad->estado === 'activa') {
            return $unidad; // idempotente
        }

        $unidad->forceFill(['estado' => 'activa', 'desactivada_at' => null])->save();

        $this->auditarEstado($operador, $unidad, 'residencia.unidad.reactivar');

        return $unidad;
    }

    private function auditarEstado(User $operador, UnidadPrivada $unidad, string $accion): void
    {
        AuditLog::create([
            'user_id' => $operador->id,
            'action' => $accion,
            'auditable_type' => UnidadPrivada::class,
            'auditable_id' => $unidad->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'codigo' => $unidad->codigo,
                'vinculos_conservados' => $unidad->vinculos()->count(),
            ],
        ]);
    }

    private function validar(ContextoOperativo $contexto, array $datos, ?int $unidadId = null): array
    {
        $validados = validator($datos, [
            'codigo' => ['required', 'string', 'max:50'],
            'numero_nombre' => ['nullable', 'string', 'max:100'],
            'torre_bloque' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', 'in:'.implode(',', array_column(TipoUnidadEnum::cases(), 'value'))],
        ], [
            'codigo.required' => 'El código de la unidad es obligatorio.',
            'codigo.max' => 'El código no puede exceder 50 caracteres.',
            'numero_nombre.max' => 'El nombre o número no puede exceder 100 caracteres.',
            'torre_bloque.max' => 'La torre o bloque no puede exceder 50 caracteres.',
            'tipo.in' => 'Selecciona un tipo de unidad válido.',
        ])->validate();

        $codigo = trim((string) $validados['codigo']);
        $existe = UnidadPrivada::query()
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->when($unidadId !== null, fn ($query) => $query->where('id', '!=', $unidadId))
            ->whereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)])
            ->exists();

        if ($existe) {
            throw $this->errorCodigoDuplicado();
        }

        return [
            'codigo' => $codigo,
            'numero_nombre' => isset($validados['numero_nombre']) ? trim((string) $validados['numero_nombre']) : null,
            'torre_bloque' => isset($validados['torre_bloque']) ? trim((string) $validados['torre_bloque']) : null,
            'tipo' => $validados['tipo'] ?? null,
        ];
    }

    private function errorCodigoDuplicado(): ValidationException
    {
        return ValidationException::withMessages([
            'codigo' => 'Ya existe una unidad con este código en la Copropiedad activa.',
        ]);
    }
}
