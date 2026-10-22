<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mantenimiento extends Model
{

    protected $table = 'mantenimientos';

    protected $fillable = [
        'organizacion_id',
        'copropiedad_id',
        'pqr_id',
        'solicitante_id',
        'responsable_id',
        'titulo',
        'descripcion',
        'idempotency_key',
        'resultado_publico',
        'resultado_interno',
        'resultado_registrado_por_user_id',
        'resultado_registrado_at',
        'finalizado_at',
        'fecha_programada',
        'estado',
    ];

    protected function casts(): array
    {

        return [
            'fecha_programada' => 'date',
            'resultado_registrado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    public function pqr(): BelongsTo
    {

        return $this->belongsTo(Pqr::class, 'pqr_id');
    }

    public function evidencias(): HasMany
    {

        return $this->hasMany(MantenimientoDocumentoEvidencia::class, 'mantenimiento_id');
    }

    public function resultadoRegistrador(): BelongsTo
    {

        return $this->belongsTo(User::class, 'resultado_registrado_por_user_id');
    }

    public function solicitante(): BelongsTo
    {

        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function responsable(): BelongsTo
    {

        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function etiquetaEstado(): string
    {

        return match ($this->estado) {
            'pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'finalizado' => 'Finalizado'
        };
    }
}
