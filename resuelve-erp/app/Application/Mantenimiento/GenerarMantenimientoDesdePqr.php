<?php

namespace App\Application\Mantenimiento;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\RegistrarActuacionPqrs;
use App\Models\AuditLog;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generación de un mantenimiento desde una PQRS (HU-PQRS-03, Sprint 19).
 *
 * Ajustes aprobados por el Product Owner (21 de octubre de 2026):
 * - operación idempotente: la clave `idempotency_key` evita duplicados por
 *   doble clic, reenvío o concurrencia; cada acción explícita usa una clave
 *   nueva y sí puede crear otro mantenimiento legítimo;
 * - la clave pertenece a una solicitud concreta: reutilizarla con otra PQRS o
 *   con datos incompatibles no devuelve ni modifica el recurso original;
 * - autorización efectiva sobre ambos recursos (`PqrPolicy::update` y
 *   `MantenimientoPolicy::create`), pertenencia al contexto y permisos de
 *   gestión; `pqrs.listar` no concede acceso suficiente;
 * - protección de duplicados accidentales mediante confirmación explícita.
 */
final class GenerarMantenimientoDesdePqr
{
    public function __construct(private readonly RegistrarActuacionPqrs $actuacion) {}

    public function ejecutar(
        User $operador,
        ContextoOperativo $contexto,
        Pqr $pqr,
        string $titulo,
        string $descripcion,
        ?string $fechaProgramada,
        string $idempotencyKey,
        bool $confirmarDuplicado = false,
    ): Mantenimiento {
        $clave = trim($idempotencyKey);
        if ($clave === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Falta la clave de idempotencia de la solicitud.']);
        }

        $fecha = trim((string) $fechaProgramada) !== '' ? date('Y-m-d', strtotime($fechaProgramada)) : null;

        try {
            return DB::transaction(function () use ($operador, $contexto, $pqr, $titulo, $descripcion, $fecha, $clave, $confirmarDuplicado): Mantenimiento {
                $existente = Mantenimiento::query()->where('idempotency_key', $clave)->first();
                if ($existente !== null) {
                    $this->verificarReutilizacionDeClave($existente, $pqr, $titulo, $descripcion, $fecha);
                    return $existente;
                }

                $this->autorizar($operador, $contexto, $pqr);

                if (! $contexto->tieneMembresiaContextual()) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('Se requiere una Membresía vigente para generar mantenimientos.');
                }

                $duplicado = Mantenimiento::query()
                    ->where('pqr_id', $pqr->id)
                    ->where('estado', '!=', 'finalizado')
                    ->get()
                    ->contains(fn (Mantenimiento $candidato) => $this->mismoTitulo($candidato->titulo, $titulo));

                if ($duplicado && ! $confirmarDuplicado) {
                    throw ValidationException::withMessages([
                        'titulo' => 'Ya existe un mantenimiento activo con el mismo título para esta PQRS. Confirma para registrar otro trabajo legítimo.',
                    ]);
                }

                $mantenimiento = Mantenimiento::create([
                    'organizacion_id' => $pqr->organizacion_id,
                    'copropiedad_id' => $pqr->copropiedad_id,
                    'pqr_id' => $pqr->id,
                    'solicitante_id' => $pqr->user_id,
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'fecha_programada' => $fecha,
                    'idempotency_key' => $clave,
                    'estado' => 'pendiente',
                ]);

                AuditLog::create([
                    'user_id' => $operador->id,
                    'action' => 'mantenimiento.desde_pqr',
                    'auditable_type' => Mantenimiento::class,
                    'auditable_id' => $mantenimiento->id,
                    'ip_address' => request()->ip(),
                    'metadata' => [
                        'pqr_id' => $pqr->id,
                        'contexto' => ['organizacion_id' => $pqr->organizacion_id, 'copropiedad_id' => $pqr->copropiedad_id],
                        'origen' => 'expediente_pqrs',
                        'duplicado_confirmado' => $duplicado,
                    ],
                ]);

                $this->actuacion->registrar(
                    $pqr,
                    $operador,
                    'mantenimiento_generado',
                    'Se generó el mantenimiento "'.$mantenimiento->titulo.'".',
                    ['mantenimiento_id' => $mantenimiento->id, 'duplicado_confirmado' => $duplicado],
                );

                return $mantenimiento;
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrencia con la misma clave: la transacción ya revirtió, por
            // lo que esta lectura (snapshot nuevo) observa la creación previa y
            // la devuelve solo si corresponde exactamente a esta solicitud.
            $previo = Mantenimiento::query()->where('idempotency_key', $clave)->firstOrFail();
            $this->verificarReutilizacionDeClave($previo, $pqr, $titulo, $descripcion, $fecha);

            return $previo;
        }
    }

    /**
     * La clave de idempotencia pertenece a una solicitud concreta (PQRS,
     * título, descripción y fecha de programación). Reutilizarla con otros
     * datos no devuelve ni modifica el mantenimiento original: se rechaza y se
     * exige una clave nueva para una solicitud distinta (ajuste aprobado).
     */
    private function verificarReutilizacionDeClave(
        Mantenimiento $existente,
        Pqr $pqr,
        string $titulo,
        string $descripcion,
        ?string $fecha,
    ): void {
        $mismaSolicitud = (int) $existente->pqr_id === (int) $pqr->id
            && $this->mismoTitulo($existente->titulo, $titulo)
            && mb_strtolower(trim((string) $existente->descripcion), 'UTF-8') === mb_strtolower(trim($descripcion), 'UTF-8')
            && $existente->fecha_programada?->format('Y-m-d') === $fecha;

        if (! $mismaSolicitud) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada con otros datos: no se devuelve ni se modifica ningún mantenimiento. Usa una clave nueva para una solicitud distinta.',
            ]);
        }
    }

    private function autorizar(User $operador, ContextoOperativo $contexto, Pqr $pqr): void
    {
        // Autorización efectiva sobre ambos recursos: gestionar la PQRS y poder
        // crear mantenimiento. `pqrs.listar` no es suficiente (ajuste aprobado).
        if (! $operador->can('update', $pqr)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('No tienes autorización para gestionar esta PQRS.');
        }

        if (! $operador->can('create', Mantenimiento::class)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('No tienes permiso para generar mantenimientos.');
        }
    }

    private function mismoTitulo(string $actual, string $nuevo): bool
    {
        return mb_strtolower(trim($actual), 'UTF-8') === mb_strtolower(trim($nuevo), 'UTF-8');
    }
}
