<?php

namespace App\Models;

use Database\Factories\RolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'clave',
        'nombre',
        'descripcion',
        'ambito_aplicable',
        'estado',
    ];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id')
            ->withPivot('ambito_aplicable');
    }

    public function membresiasOrganizacion(): BelongsToMany
    {
        return $this->belongsToMany(
            MembresiaOrganizacion::class,
            'membresia_organizacion_rol',
            'rol_id',
            'membresia_organizacion_id'
        )->withPivot([
            'organizacion_id',
            'ambito_rol',
            'estado',
            'vigente_desde',
            'vigente_hasta',
            'asignado_por',
        ])->withTimestamps();
    }

    public function membresiasCopropiedad(): BelongsToMany
    {
        return $this->belongsToMany(
            MembresiaCopropiedad::class,
            'membresia_copropiedad_rol',
            'rol_id',
            'membresia_copropiedad_id'
        )->withPivot([
            'organizacion_id',
            'copropiedad_id',
            'ambito_rol',
            'estado',
            'vigente_desde',
            'vigente_hasta',
            'asignado_por',
        ])->withTimestamps();
    }
}
