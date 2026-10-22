<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Domain\Residencia\Enums\TipoVinculoEnum;
use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\UnidadPrivada;
use App\Models\User;
use App\Models\VinculoUnidad;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Vínculos residenciales Persona ↔ Unidad Privada (HU-RYP-01, Sprint 20).
 *
 * Reutiliza `VinculoUnidad::asociarPartesContextuales` (contexto por backend),
 * `concluirVigencia` (cierre sin eliminación) y `scopeVigentes`. Un mismo
 * vínculo (persona, unidad, tipo) solo puede solaparse consigo mismo en
 * periodos incompatibles: se rechazan solapamientos indebidos y se permiten
 * vinculaciones históricas legítimas (periodos sin solaparse) y tipos
 * concurrentes (propietario y residente a la vez).
 *
 * Regla transversal: los vínculos residenciales **no conceden ni modifican
 * acceso** al ERP; no se tocan Membresías, Roles ni permisos, y no se crean
 * cuentas de usuario.
 */
final class GestionarVinculoUnidad
{
    public function asociar(ContextoOperativo $contexto, User $operador, UnidadPrivada $unidad, array $datos): VinculoUnidad
    {
        AutorizacionResidencia::exigirGestion($contexto);
        $validados = $this->validar($datos);

        $persona = app(ConsultaPersonasContextuales::class)->resolver($contexto, $validados['persona_id']);

        if ($unidad->estado !== 'activa') {
            throw ValidationException::withMessages([
                'vinculo' => 'La unidad está desactivada: no admite nuevas vinculaciones.',
            ]);
        }

        if ($persona->estado !== 'activa') {
            throw ValidationException::withMessages([
                'persona_id' => 'La persona está desactivada: no admite nuevos vínculos.',
            ]);
        }

        $this->verificarSolapamiento($unidad, $persona, $validados['tipo_vinculo'], $validados['vigente_desde'], $validados['vigente_hasta']);

        $vinculo = new VinculoUnidad();
        $vinculo->asociarPartesContextuales($persona, $unidad);
        $vinculo->forceFill([
            'tipo_vinculo' => $validados['tipo_vinculo'],
            'estado' => 'activo',
            'vigente_desde' => $validados['vigente_desde'],
            'vigente_hasta' => $validados['vigente_hasta'],
            'observacion' => $validados['observacion'],
        ]);

        DB::transaction(function () use ($vinculo, $unidad, $persona, $validados, $operador): void {
            try {
                $vinculo->save();
            } catch (UniqueConstraintViolationException) {
                throw $this->errorSolapamiento($validados['tipo_vinculo']);
            }

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'residencia.vinculo.crear',
                'auditable_type' => VinculoUnidad::class,
                'auditable_id' => $vinculo->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'persona_id' => $persona->id,
                    'unidad_privada_id' => $unidad->id,
                    'tipo_vinculo' => $vinculo->tipo_vinculo->value,
                    'vigente_desde' => (string) $vinculo->vigente_desde,
                    'vigente_hasta' => $vinculo->vigente_hasta ? (string) $vinculo->vigente_hasta : null,
                ],
            ]);
        });

        return $vinculo;
    }

    public function concluir(ContextoOperativo $contexto, User $operador, VinculoUnidad $vinculo, ?string $fechaTerminacion = null): VinculoUnidad
    {
        AutorizacionResidencia::exigirGestion($contexto);

        if ($vinculo->estado === 'concluido') {
            throw ValidationException::withMessages([
                'vigente_hasta' => 'El vínculo ya está concluido; su historia se conserva sin cambios.',
            ]);
        }

        try {
            $vinculo->concluirVigencia($fechaTerminacion ?: null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['vigente_hasta' => $exception->getMessage()]);
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.vinculo.concluir',
            'auditable_type' => VinculoUnidad::class,
            'auditable_id' => $vinculo->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'persona_id' => $vinculo->persona_id,
                'unidad_privada_id' => $vinculo->unidad_privada_id,
                'tipo_vinculo' => $vinculo->tipo_vinculo->value,
                'vigente_hasta' => (string) $vinculo->vigente_hasta,
            ],
        ]);

        return $vinculo;
    }

    /**
     * Corrección justificada de fechas de vigencia (HU-RYP-01). Solo corrige
     * fechas —jamás persona, unidad ni tipo— y audita los valores anteriores y
     * nuevos junto con el motivo.
     */
    public function corregirVigencia(ContextoOperativo $contexto, User $operador, VinculoUnidad $vinculo, array $datos): VinculoUnidad
    {
        AutorizacionResidencia::exigirGestion($contexto);

        $reglas = [
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'motivo' => ['required', 'string', 'max:2000'],
        ];
        if ($vinculo->estado === 'concluido') {
            // Un vínculo concluido siempre tiene fecha final: no puede quedar abierta.
            $reglas['vigente_hasta'][] = 'required';
        }

        $validados = validator($datos, $reglas, [
            'vigente_desde.required' => 'Indica la fecha de inicio del vínculo.',
            'vigente_hasta.required' => 'Un vínculo concluido conserva su fecha de finalización.',
            'vigente_hasta.after_or_equal' => 'La fecha de finalización no puede ser anterior a la fecha de inicio.',
            'motivo.required' => 'Justifica la corrección de fechas.',
            'motivo.max' => 'El motivo no puede exceder 2000 caracteres.',
        ])->validate();

        $desde = date('Y-m-d', strtotime($validados['vigente_desde']));
        $hasta = isset($validados['vigente_hasta']) ? date('Y-m-d', strtotime($validados['vigente_hasta'])) : null;

        $unidad = $vinculo->unidadPrivada()->firstOrFail();
        $persona = $vinculo->persona()->firstOrFail();
        $this->verificarSolapamiento($unidad, $persona, $vinculo->tipo_vinculo->value, $desde, $hasta, $vinculo->id);

        $antes = [
            'vigente_desde' => $vinculo->vigente_desde->format('Y-m-d'),
            'vigente_hasta' => $vinculo->vigente_hasta?->format('Y-m-d'),
        ];
        $vinculo->forceFill(['vigente_desde' => $desde, 'vigente_hasta' => $hasta]);

        try {
            $vinculo->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->errorSolapamiento($vinculo->tipo_vinculo->value);
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.vinculo.corregir',
            'auditable_type' => VinculoUnidad::class,
            'auditable_id' => $vinculo->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'persona_id' => $vinculo->persona_id,
                'unidad_privada_id' => $vinculo->unidad_privada_id,
                'tipo_vinculo' => $vinculo->tipo_vinculo->value,
                'antes' => $antes,
                'despues' => ['vigente_desde' => $desde, 'vigente_hasta' => $hasta],
                'motivo' => trim((string) $validados['motivo']),
            ],
        ]);

        return $vinculo;
    }

    /** @return array{persona_id:int,tipo_vinculo:string,vigente_desde:string,vigente_hasta:?string,observacion:?string} */
    private function validar(array $datos): array
    {
        $validados = validator($datos, [
            'persona_id' => ['required', 'integer'],
            'tipo_vinculo' => ['required', 'string', 'in:'.implode(',', array_column(TipoVinculoEnum::cases(), 'value'))],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'persona_id.required' => 'Selecciona la persona a vincular.',
            'tipo_vinculo.required' => 'Selecciona el tipo de vínculo.',
            'tipo_vinculo.in' => 'Selecciona un tipo de vínculo válido.',
            'vigente_desde.required' => 'Indica la fecha de inicio del vínculo.',
            'vigente_hasta.after_or_equal' => 'La fecha de finalización no puede ser anterior a la fecha de inicio.',
            'observacion.max' => 'La observación no puede exceder 2000 caracteres.',
        ])->validate();

        return [
            'persona_id' => (int) $validados['persona_id'],
            'tipo_vinculo' => $validados['tipo_vinculo'],
            'vigente_desde' => date('Y-m-d', strtotime($validados['vigente_desde'])),
            'vigente_hasta' => isset($validados['vigente_hasta']) ? date('Y-m-d', strtotime($validados['vigente_hasta'])) : null,
            'observacion' => isset($validados['observacion']) ? trim((string) $validados['observacion']) : null,
        ];
    }

    /**
     * Rechaza solapamientos indebidos del mismo (persona, unidad, tipo) y
     * permite vínculos históricos legítimos y tipos concurrentes.
     */
    private function verificarSolapamiento(UnidadPrivada $unidad, Persona $persona, string $tipo, string $desde, ?string $hasta, ?int $excluirVinculoId = null): void
    {
        $solapado = VinculoUnidad::query()
            ->where('unidad_privada_id', $unidad->id)
            ->where('persona_id', $persona->id)
            ->where('tipo_vinculo', $tipo)
            ->when($excluirVinculoId !== null, fn ($query) => $query->where('id', '!=', $excluirVinculoId))
            ->where('vigente_desde', '<=', $hasta ?? '9999-12-31')
            ->where(function ($query) use ($desde): void {
                $query->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $desde);
            })
            ->exists();

        if ($solapado) {
            throw $this->errorSolapamiento($tipo);
        }
    }

    private function errorSolapamiento(string $tipo): ValidationException
    {
        return ValidationException::withMessages([
            'vigente_desde' => 'La persona ya tiene un vínculo «'.$tipo.'» con esta unidad en un periodo que se solapa con el indicado. Ajusta las fechas o concluye el vínculo anterior.',
        ]);
    }
}
