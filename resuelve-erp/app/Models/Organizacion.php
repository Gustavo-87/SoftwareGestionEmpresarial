<?php

namespace App\Models;

use Database\Factories\OrganizacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organizacion extends Model
{
    use HasFactory;

    protected $table = 'organizaciones';

    protected $fillable = [
        'nombre',
        'identificacion_tributaria',
        'email',
        'telefono',
        'estado',
        'desactivada_at',
    ];

    protected function casts(): array
    {
        return [
            'desactivada_at' => 'datetime',
        ];
    }

    public function copropiedades(): HasMany
    {
        return $this->hasMany(Copropiedad::class);
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(ConfiguracionOrganizacion::class);
    }

    public function membresiasOrganizacion(): HasMany
    {
        return $this->hasMany(MembresiaOrganizacion::class);
    }

    public function membresiasCopropiedad(): HasMany
    {
        return $this->hasMany(MembresiaCopropiedad::class);
    }

    public function pqrs(): HasMany
    {
        return $this->hasMany(Pqr::class);
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function unidadesPrivadas(): HasMany
    {
        return $this->hasMany(UnidadPrivada::class);
    }

    public function vinculosUnidad(): HasMany
    {
        return $this->hasMany(VinculoUnidad::class);
    }
}
