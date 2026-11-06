<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function getModuleAttribute(): string
    {
        return match ($this->subject_type) {
            Copropiedad::class => 'Copropiedades',
            UnidadPrivada::class => 'Unidades privadas',
            PqrTag::class => 'Etiquetas',
            Mantenimiento::class => 'Mantenimiento',
            Pqr::class => 'Gestión PQRS',
            default => 'Operación',
        };
    }

    public function getHumanActionAttribute(): string
    {
        $accion = match ($this->event) {
            'created' => 'Creó',
            'updated' => 'Actualizó',
            'deleted' => 'Eliminó',
            'restored' => 'Restauró',
            default => 'Registró un cambio en',
        };

        return $accion.' '.$this->module.' #'.$this->subject_id;
    }

    /** Solo se presentan los campos incluidos en la lista de auditoría del modelo. */
    public function detalleCambios(): array
    {
        $nuevos = $this->properties?->get('attributes', []) ?? [];
        $anteriores = $this->properties?->get('old', []) ?? [];
        $etiquetas = [
            'nombre' => 'Nombre', 'codigo' => 'Código', 'numero_nombre' => 'Número',
            'torre_bloque' => 'Torre o bloque', 'tipo' => 'Tipo', 'estado' => 'Estado',
            'desactivada_at' => 'Fecha de desactivación', 'name' => 'Nombre',
            'color' => 'Color', 'activo' => 'Activo', 'responsable_id' => 'Responsable',
            'fecha_programada' => 'Fecha programada', 'finalizado_at' => 'Finalización',
            'prioridad' => 'Prioridad', 'assigned_to_id' => 'Responsable',
            'tipo_pqr_id' => 'Tipo de PQRS', 'fecha_limite_respuesta' => 'Fecha límite',
        ];
        $filas = [];
        foreach (array_unique([...array_keys($nuevos), ...array_keys($anteriores)]) as $campo) {
            if (! isset($etiquetas[$campo])) {
                continue;
            }
            $filas[] = [
                'campo' => $etiquetas[$campo],
                'antes' => $this->valorVisible($anteriores[$campo] ?? null),
                'despues' => $this->valorVisible($nuevos[$campo] ?? null),
            ];
        }

        return $filas;
    }

    private function valorVisible(mixed $valor): string
    {
        return is_bool($valor) ? ($valor ? 'Sí' : 'No') : (is_scalar($valor) ? (string) $valor : 'Sin valor');
    }
}
