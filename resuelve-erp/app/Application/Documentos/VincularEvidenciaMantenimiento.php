<?php

namespace App\Application\Documentos;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoDocumentoEvidencia;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Evidencia documental de mantenimientos (HU-DOC-03, Sprint 19).
 *
 * La evidencia fija la versión exacta (`documento_version_id`) sin alterar el
 * ciclo documental. Solo versiones `aprobada` de documentos `activo` pueden
 * vincularse; la operación exige gestión del mantenimiento y acceso efectivo
 * al documento (autorización sobre ambos recursos; vincular no amplía la
 * visibilidad). Es idempotente ante doble clic, reenvío o concurrencia y el
 * conjunto de evidencias es inmutable tras finalizar.
 */
final class VincularEvidenciaMantenimiento
{
    public function vincular(
        User $operador,
        ContextoOperativo $contexto,
        Mantenimiento $mantenimiento,
        Documento $documento,
        DocumentoVersion $version,
    ): MantenimientoDocumentoEvidencia {
        return DB::transaction(function () use ($operador, $contexto, $mantenimiento, $documento, $version): MantenimientoDocumentoEvidencia {
            $this->autorizar($operador, $contexto, $mantenimiento, $documento);

            if ($mantenimiento->estado === 'finalizado') {
                throw ValidationException::withMessages(['evidencia' => 'Un mantenimiento finalizado conserva sus evidencias de forma inmutable.']);
            }

            if ((int) $version->documento_id !== (int) $documento->id) {
                throw ValidationException::withMessages(['documento_version_id' => 'La versión no pertenece al documento seleccionado.']);
            }

            if ($documento->estado->value !== 'activo') {
                throw ValidationException::withMessages(['documento_id' => 'Solo los documentos activos pueden vincularse como evidencia.']);
            }

            if ($version->estado->value !== 'aprobada') {
                throw ValidationException::withMessages([
                    'documento_version_id' => 'Solo una versión aprobada sirve como evidencia (estado actual: '.$version->estado->value.').',
                ]);
            }

            $existente = MantenimientoDocumentoEvidencia::query()
                ->where('mantenimiento_id', $mantenimiento->id)
                ->where('documento_version_id', $version->id)
                ->first();
            if ($existente !== null) {
                return $existente; // idempotente
            }

            try {
                $evidencia = MantenimientoDocumentoEvidencia::create([
                    'mantenimiento_id' => $mantenimiento->id,
                    'documento_id' => $documento->id,
                    'documento_version_id' => $version->id,
                    'vinculado_por_user_id' => $operador->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                return MantenimientoDocumentoEvidencia::query()
                    ->where('mantenimiento_id', $mantenimiento->id)
                    ->where('documento_version_id', $version->id)
                    ->firstOrFail();
            }

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.evidencia.vincular',
                'auditable_type' => MantenimientoDocumentoEvidencia::class,
                'auditable_id' => $evidencia->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'mantenimiento_id' => $mantenimiento->id,
                    'documento_id' => $documento->id,
                    'documento_version_id' => $version->id,
                ],
            ]);

            return $evidencia;
        });
    }

    public function retirar(
        User $operador,
        ContextoOperativo $contexto,
        Mantenimiento $mantenimiento,
        MantenimientoDocumentoEvidencia $evidencia,
    ): void {
        DB::transaction(function () use ($operador, $contexto, $mantenimiento, $evidencia): void {
            $documento = $evidencia->documento;
            if ($documento === null) {
                throw ValidationException::withMessages(['evidencia' => 'La evidencia ya no está disponible.']);
            }

            $this->autorizar($operador, $contexto, $mantenimiento, $documento);

            if ($mantenimiento->estado === 'finalizado') {
                throw ValidationException::withMessages(['evidencia' => 'Un mantenimiento finalizado conserva sus evidencias de forma inmutable.']);
            }

            $evidencia->delete();

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'mantenimiento.evidencia.retirar',
                'auditable_type' => Mantenimiento::class,
                'auditable_id' => $mantenimiento->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'documento_id' => $documento->id,
                    'documento_version_id' => $evidencia->documento_version_id,
                ],
            ]);
        });
    }

    private function autorizar(User $operador, ContextoOperativo $contexto, Mantenimiento $mantenimiento, Documento $documento): void
    {
        if (! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar evidencias.');
        }

        if (! $operador->can('update', $mantenimiento)) {
            throw new AuthorizationException('No tienes autorización para gestionar este mantenimiento.');
        }

        if ((int) $documento->organizacion_id !== (int) $contexto->organizacion->id
            || (int) $documento->copropiedad_id !== (int) $contexto->copropiedad->id) {
            throw new AuthorizationException('El documento pertenece a otro contexto.');
        }

        if (! $operador->can('view', $documento)) {
            throw new AuthorizationException('No tienes acceso al documento seleccionado.');
        }
    }
}
