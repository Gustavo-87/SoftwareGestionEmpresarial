<?php

namespace App\Models;

use Database\Factories\MembresiaCopropiedadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MembresiaCopropiedad extends Model
{
    use HasFactory;

    protected $table = 'membresias_copropiedad';

    protected $fillable = [
        'usuario_id',
        'organizacion_id',
        'copropiedad_id',
        'estado',
        'vigente_desde',
        'vigente_hasta',
        'creada_por',
        'motivo_terminacion',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function copropiedad(): BelongsTo
    {
        return $this->belongsTo(Copropiedad::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'membresia_copropiedad_rol',
            'membresia_copropiedad_id',
            'rol_id'
        )->withPivot([
            'organizacion_id',
            'copropiedad_id',
            'ambito_rol',
            'estado',
            'vigente_desde',
            'vigente_hasta',
            'asignado_por',
        ])->using(MembresiaCopropiedadRol::class)
          ->withTimestamps();
    }
}
