<?php

namespace App\Models;

use App\Domain\Residencia\Enums\TipoPersonaEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $table = 'personas';

    /**
     * organizacion_id se excluye estrictamente de $fillable para impedir
     * manipulación de contexto por HTTP. Se asigna en backend.
     */
    protected $fillable = [
        'usuario_id',
        'tipo_persona',
        'nombre_razon_social',
        'identificacion',
        'email',
        'telefono',
        'estado',
        'desactivada_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo_persona' => TipoPersonaEnum::class,
            'desactivada_at' => 'datetime',
        ];
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(VinculoUnidad::class, 'persona_id');
    }

    public function unidadesPrivadas(): BelongsToMany
    {
        // Acceso de lectura. Las altas y cambios se realizan mediante VinculoUnidad.
        return $this->belongsToMany(UnidadPrivada::class, 'vinculos_unidad', 'persona_id', 'unidad_privada_id')
            ->withPivot(['tipo_vinculo', 'estado', 'vigente_desde', 'vigente_hasta'])
            ->where('unidades_privadas.organizacion_id', $this->organizacion_id)
            ->wherePivot('organizacion_id', $this->organizacion_id);
    }
}
