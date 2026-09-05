<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionCopropiedad extends Model
{
    protected $table = 'configuraciones_copropiedad';

    protected $fillable = [
        'organizacion_id',
        'copropiedad_id',
        'color_principal',
        'logo_path',
        'dias_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'dias_respuesta' => 'integer',
        ];
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function copropiedad(): BelongsTo
    {
        return $this->belongsTo(Copropiedad::class);
    }
}
