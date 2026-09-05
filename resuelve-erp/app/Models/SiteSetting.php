<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    const COLOR_INSTITUCIONAL = '#1e3a5f';

    protected $fillable = ['nombre_conjunto', 'nit', 'representante_legal', 'direccion', 'ciudad', 'telefono', 'email', 'color_principal', 'dias_respuesta', 'logo_path', 'logo_scale', 'logo_offset_x', 'logo_offset_y'];

    protected function casts(): array
    {
        return [
            'dias_respuesta' => 'integer',
            'logo_scale' => 'decimal:2',
            'logo_offset_x' => 'integer',
            'logo_offset_y' => 'integer',
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

    public static function current(): self
    {
        if (! Schema::hasTable('site_settings')) {
            return new self(self::defaults());
        }

        return self::first() ?? new self(self::defaults());
    }

    public static function defaults(): array
    {
        return ['nombre_conjunto' => 'Mi conjunto residencial', 'ciudad' => 'Colombia', 'color_principal' => self::COLOR_INSTITUCIONAL, 'dias_respuesta' => 15];
    }
}
