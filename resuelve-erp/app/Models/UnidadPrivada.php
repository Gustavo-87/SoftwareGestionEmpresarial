<?php

namespace App\Models;

use App\Domain\Residencia\Enums\TipoUnidadEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadPrivada extends Model
{
    protected $table = 'unidades_privadas';

    /**
     * organizacion_id y copropiedad_id se excluyen estrictamente de $fillable
     * para impedir manipulación de contexto por HTTP.
     */
    protected $fillable = [
        'codigo',
        'numero_nombre',
        'torre_bloque',
        'tipo',
        'estado',
        'desactivada_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoUnidadEnum::class,
            'desactivada_at' => 'datetime',
        ];
    }

    public function copropiedad(): BelongsTo
    {
        return $this->belongsTo(Copropiedad::class, 'copropiedad_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(VinculoUnidad::class, 'unidad_privada_id');
    }

    public function personas(): BelongsToMany
    {
        // Acceso de lectura. Las altas y cambios se realizan mediante VinculoUnidad.
        return $this->belongsToMany(Persona::class, 'vinculos_unidad', 'unidad_privada_id', 'persona_id')
            ->withPivot(['tipo_vinculo', 'estado', 'vigente_desde', 'vigente_hasta'])
            ->where('personas.organizacion_id', $this->organizacion_id)
            ->wherePivot('organizacion_id', $this->organizacion_id)
            ->wherePivot('copropiedad_id', $this->copropiedad_id);
    }
}
