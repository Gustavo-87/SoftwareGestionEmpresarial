<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPqr extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion', 'desactivada_at'];

    protected function casts(): array
    {
        return [
            'desactivada_at' => 'datetime',
        ];
    }

    public function pqrs()
    {
        return $this->hasMany(Pqr::class);
    }

    /** Baja lógica (HU-CONF-03): los activos no tienen fecha de desactivación. */
    public function estaDesactivado(): bool
    {
        return $this->desactivada_at !== null;
    }
}
