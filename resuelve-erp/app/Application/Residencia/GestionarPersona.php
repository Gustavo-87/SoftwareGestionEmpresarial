<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Domain\Residencia\Enums\TipoPersonaEnum;
use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Registro de Personas de la Organización (HU-RYP-01, Sprint 20).
 *
 * La Persona pertenece a la Organización del contexto (se asigna en backend) y
 * **no crea cuentas de usuario**: `usuario_id` jamás se acepta del payload.
 * La identificación es única por Organización (índice contextual existente).
 */
final class GestionarPersona
{
    public function crear(ContextoOperativo $contexto, User $operador, array $datos): Persona
    {
        AutorizacionResidencia::exigirGestion($contexto);
        $validados = $this->validar($contexto, $datos);

        $persona = new Persona();
        $persona->forceFill([
            'organizacion_id' => $contexto->organizacion->id,
            'estado' => 'activa',
            ...$validados,
        ]);

        try {
            $persona->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->errorIdentificacionDuplicada();
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.persona.crear',
            'auditable_type' => Persona::class,
            'auditable_id' => $persona->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'nombre_razon_social' => $persona->nombre_razon_social,
                'organizacion_id' => $persona->organizacion_id,
            ],
        ]);

        return $persona;
    }

    /**
     * Desactivación administrativa sin eliminación física: los vínculos de la
     * persona se conservan como historia y no se concluyen automáticamente.
     */
    public function desactivar(ContextoOperativo $contexto, User $operador, Persona $persona): Persona
    {
        AutorizacionResidencia::exigirGestion($contexto);

        if ($persona->estado === 'desactivada') {
            return $persona; // idempotente
        }

        $persona->forceFill(['estado' => 'desactivada', 'desactivada_at' => now()])->save();

        $this->auditarEstado($operador, $persona, 'residencia.persona.desactivar');

        return $persona;
    }

    public function reactivar(ContextoOperativo $contexto, User $operador, Persona $persona): Persona
    {
        AutorizacionResidencia::exigirGestion($contexto);

        if ($persona->estado === 'activa') {
            return $persona; // idempotente
        }

        $persona->forceFill(['estado' => 'activa', 'desactivada_at' => null])->save();

        $this->auditarEstado($operador, $persona, 'residencia.persona.reactivar');

        return $persona;
    }

    private function auditarEstado(User $operador, Persona $persona, string $accion): void
    {
        AuditLog::create([
            'user_id' => $operador->id,
            'action' => $accion,
            'auditable_type' => Persona::class,
            'auditable_id' => $persona->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'nombre_razon_social' => $persona->nombre_razon_social,
                'vinculos_conservados' => $persona->vinculos()->count(),
            ],
        ]);
    }

    private function validar(ContextoOperativo $contexto, array $datos): array
    {
        $validados = validator($datos, [
            'tipo_persona' => ['required', 'string', 'in:'.implode(',', array_column(TipoPersonaEnum::cases(), 'value'))],
            'nombre_razon_social' => ['required', 'string', 'max:255'],
            'identificacion' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ], [
            'tipo_persona.required' => 'Selecciona el tipo de persona.',
            'tipo_persona.in' => 'Selecciona un tipo de persona válido.',
            'nombre_razon_social.required' => 'El nombre o razón social es obligatorio.',
            'nombre_razon_social.max' => 'El nombre o razón social no puede exceder 255 caracteres.',
            'identificacion.max' => 'La identificación no puede exceder 50 caracteres.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'telefono.max' => 'El teléfono no puede exceder 50 caracteres.',
        ])->validate();

        $identificacion = isset($validados['identificacion']) ? trim((string) $validados['identificacion']) : null;

        if ($identificacion !== null && $identificacion !== '') {
            $existe = Persona::query()
                ->where('organizacion_id', $contexto->organizacion->id)
                ->whereRaw('LOWER(identificacion) = ?', [mb_strtolower($identificacion)])
                ->exists();

            if ($existe) {
                throw $this->errorIdentificacionDuplicada();
            }
        }

        return [
            'tipo_persona' => $validados['tipo_persona'],
            'nombre_razon_social' => trim((string) $validados['nombre_razon_social']),
            'identificacion' => $identificacion !== '' ? $identificacion : null,
            'email' => isset($validados['email']) ? trim((string) $validados['email']) : null,
            'telefono' => isset($validados['telefono']) ? trim((string) $validados['telefono']) : null,
        ];
    }

    private function errorIdentificacionDuplicada(): ValidationException
    {
        return ValidationException::withMessages([
            'identificacion' => 'Ya existe una persona con esta identificación en la Organización.',
        ]);
    }
}
