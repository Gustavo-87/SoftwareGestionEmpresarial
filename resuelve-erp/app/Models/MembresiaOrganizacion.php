<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MembresiaOrganizacion extends Model
{
    protected $table = 'membresias_organizacion';

    protected $fillable = [
        'usuario_id',
        'organizacion_id',
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

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'membresia_organizacion_rol',
            'membresia_organizacion_id',
            'rol_id'
        )->withPivot([
            'organizacion_id',
            'ambito_rol',
            'estado',
            'vigente_desde',
            'vigente_hasta',
            'asignado_por',
        ])->withTimestamps();
    }
}
