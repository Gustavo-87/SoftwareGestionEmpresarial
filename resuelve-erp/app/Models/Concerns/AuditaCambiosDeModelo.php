<?php

namespace App\Models\Concerns;

use App\Models\Copropiedad;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\UnidadPrivada;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait AuditaCambiosDeModelo
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $campos = match (static::class) {
            Copropiedad::class => ['nombre', 'estado', 'desactivada_at'],
            UnidadPrivada::class => ['codigo', 'numero_nombre', 'torre_bloque', 'tipo', 'estado', 'desactivada_at'],
            PqrTag::class => ['name', 'color', 'activo'],
            Mantenimiento::class => ['estado', 'responsable_id', 'fecha_programada', 'finalizado_at'],
            Pqr::class => ['estado', 'prioridad', 'assigned_to_id', 'tipo_pqr_id', 'fecha_limite_respuesta'],
            default => [],
        };

        return LogOptions::defaults()->useLogName('modelos')
            ->logOnly($campos)->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $event): void
    {
        $activity->organizacion_id = $this->organizacion_id;
        $activity->copropiedad_id = $this instanceof Copropiedad ? $this->getKey() : $this->copropiedad_id;
    }
}
