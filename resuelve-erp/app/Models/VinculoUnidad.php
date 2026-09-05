<?php

namespace App\Models;

use App\Domain\Residencia\Enums\TipoVinculoEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class VinculoUnidad extends Model
{
    protected $table = 'vinculos_unidad';

    /**
     * organizacion_id, copropiedad_id, persona_id y unidad_privada_id
     * se excluyen estrictamente de $fillable para impedir asignación insegura por HTTP.
     */
    protected $fillable = [
        'tipo_vinculo',
        'estado',
        'vigente_desde',
        'vigente_hasta',
        'fuente',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo_vinculo' => TipoVinculoEnum::class,
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function unidadPrivada(): BelongsTo
    {
        return $this->belongsTo(UnidadPrivada::class, 'unidad_privada_id');
    }

    /**
     * Asigna Persona y UnidadPrivada previa validación contextual.
     */
    public function asociarPartesContextuales(Persona $persona, UnidadPrivada $unidad): void
    {
        if ($persona->organizacion_id !== $unidad->organizacion_id) {
            throw new InvalidArgumentException('Inconsistencia de contexto: Persona y Unidad pertenecen a Organizaciones distintas.');
        }

        $this->persona_id = $persona->id;
        $this->unidad_privada_id = $unidad->id;
        $this->organizacion_id = $unidad->organizacion_id;
        $this->copropiedad_id = $unidad->copropiedad_id;
    }

    /**
     * Scope de conveniencia para consultar vínculos activos e históricamente vigentes.
     */
    public function scopeVigentes($query)
    {
        $hoy = now()->toDateString();

        return $query->where('estado', 'activo')
            ->where('vigente_desde', '<=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $hoy);
            });
    }

    /**
     * Concluye la vigencia del vínculo conservando la historia.
     */
    public function concluirVigencia(?string $fechaTerminacion = null): void
    {
        $fecha = CarbonImmutable::parse($fechaTerminacion ?? now())->startOfDay();

        if ($this->vigente_desde && $fecha->lt($this->vigente_desde)) {
            throw new InvalidArgumentException('La fecha de vigencia final no puede ser anterior a la fecha de inicio.');
        }

        $this->vigente_hasta = $fecha;
        $this->estado = 'concluido';
        $this->save();
    }
}
