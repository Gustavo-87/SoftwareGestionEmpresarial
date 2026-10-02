<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos_contextuales';

    protected $fillable = [
        'clave',
        'modulo',
        'accion',
        'descripcion',
        'ambito_aplicable',
        'estado',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso_contextual', 'permiso_id', 'rol_id')
            ->withPivot('ambito_aplicable');
    }
}
