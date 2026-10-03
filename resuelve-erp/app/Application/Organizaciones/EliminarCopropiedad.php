<?php

namespace App\Application\Organizaciones;

use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sprint 17 — Eliminación segura de Copropiedades.
 *
 * Elimina una Copropiedad únicamente cuando no contiene información operativa.
 * No destruye datos: su existencia bloquea la eliminación con un mensaje que
 * identifica cada dependencia. Solo se limpian relaciones auxiliares propias de
 * una Copropiedad sin operación. Requiere autoridad de plataforma.
 */
final class EliminarCopropiedad
{
    /**
     * Tablas con datos operativos que impiden la eliminación.
     * Todas comparten las columnas organizacion_id y copropiedad_id.
     */
    private const DEPENDENCIAS = [
        'pqrs' => 'PQRS',
        'pqr_tags' => 'etiquetas',
        'pqr_pqr_tag' => 'asignaciones de etiquetas',
        'documentos' => 'documentos',
        'documento_versiones' => 'versiones de documentos',
        'documento_actuaciones' => 'actuaciones de documentos',
        'mantenimientos' => 'mantenimientos',
        'unidades_privadas' => 'unidades privadas',
        'vinculos_unidad' => 'vínculos de personas',
        'membresias_copropiedad' => 'membresías',
        'membresia_copropiedad_rol' => 'asignaciones de roles',
    ];

    /** Relaciones auxiliares propias de una Copropiedad vacía; su limpieza es segura. */
    private const AUXILIARES = ['configuraciones_copropiedad', 'site_settings'];

    public function ejecutar(User $operador, Copropiedad $copropiedad): void
    {
        if (! $operador->esAdministradorSistema()) {
            throw new AuthorizationException('Solo la autoridad de plataforma puede eliminar Copropiedades.');
        }

        DB::transaction(function () use ($operador, $copropiedad): void {
            // Bloqueo de la fila: serializa con creaciones de datos concurrentes.
            $copropiedad = Copropiedad::query()->whereKey($copropiedad->id)->lockForUpdate()->firstOrFail();

            $bloqueos = [];
            foreach (self::DEPENDENCIAS as $tabla => $etiqueta) {
                $total = $this->consultar($tabla, $copropiedad)->count();
                if ($total > 0) {
                    $bloqueos[] = "{$etiqueta} ({$total})";
                }
            }

            if ($bloqueos !== []) {
                throw ValidationException::withMessages([
                    'copropiedad' => 'La Copropiedad contiene información operativa y no puede eliminarse: '
                        .implode(', ', $bloqueos).'.',
                ]);
            }

            foreach (self::AUXILIARES as $tabla) {
                $this->consultar($tabla, $copropiedad)->delete();
            }

            $identidad = [
                'id' => $copropiedad->id,
                'nombre' => $copropiedad->nombre,
                'nit' => $copropiedad->nit,
                'organizacion_id' => $copropiedad->organizacion_id,
            ];
            $copropiedad->delete();

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'copropiedad.delete',
                'auditable_type' => Copropiedad::class,
                'auditable_id' => $identidad['id'],
                'ip_address' => request()->ip(),
                'metadata' => $identidad,
            ]);
        });
    }

    private function consultar(string $tabla, Copropiedad $copropiedad)
    {
        return DB::table($tabla)
            ->where('organizacion_id', $copropiedad->organizacion_id)
            ->where('copropiedad_id', $copropiedad->id);
    }
}
