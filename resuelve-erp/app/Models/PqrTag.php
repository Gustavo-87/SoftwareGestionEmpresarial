<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PqrTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function copropiedad(): BelongsTo
    {
        return $this->belongsTo(Copropiedad::class);
    }

    public function pqrs(): BelongsToMany
    {
        return $this->belongsToMany(Pqr::class)
            ->withPivot(['organizacion_id', 'copropiedad_id'])
            ->where('pqrs.organizacion_id', $this->organizacion_id)
            ->where('pqrs.copropiedad_id', $this->copropiedad_id);
    }
}
