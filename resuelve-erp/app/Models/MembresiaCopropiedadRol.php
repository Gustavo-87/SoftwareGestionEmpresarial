<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MembresiaCopropiedadRol extends Pivot
{
    protected $casts = [
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];
}
