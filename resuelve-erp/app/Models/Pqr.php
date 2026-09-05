<?php

namespace App\Models;

use App\Domain\Pqrs\CalendarioLaboralColombia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Pqr extends Model
{
    use HasFactory;

    public const PRIORIDADES = ['alta', 'media', 'baja'];

    protected static function booted(): void
    {
        static::saving(function (Pqr $pqr) {
            if ($pqr->prioridad !== null && ! in_array($pqr->prioridad, self::PRIORIDADES, true)) {
                throw new \InvalidArgumentException("Prioridad inválida: {$pqr->prioridad}. Valores permitidos: " . implode(', ', self::PRIORIDADES));
            }
        });
    }
    protected $fillable = [
        'asunto',
        'descripcion',
        'fecha_radicacion',
        'fecha_limite_respuesta',
        'estado',
        'prioridad',
        'user_id',
        'assigned_to_id',
        'tipo_pqr_id',
        'last_reminder_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_radicacion' => 'date',
            'fecha_limite_respuesta' => 'date', 'last_reminder_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function copropiedad(): BelongsTo
    {
        return $this->belongsTo(Copropiedad::class);
    }

    public function tipoPqr()
    {
        return $this->belongsTo(TipoPqr::class);
    }

    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function activities() { return $this->hasMany(PqrActivity::class)->latest(); }
    public function replies() { return $this->hasMany(PqrReply::class)->latest(); }
    public function internalComments() { return $this->hasMany(PqrInternalComment::class)->latest(); }
    public function communicationOperations() { return $this->hasMany(PqrCommunicationOperation::class); }
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PqrTag::class)
            ->withPivot(['organizacion_id', 'copropiedad_id']);
    }
    public function satisfactionSurvey() { return $this->hasOne(SatisfactionSurvey::class); }

    public function attachments()
    {
        return $this->hasMany(PqrAttachment::class);
    }

    public function scopeRespondidas($query)
    {
        return $query->where('estado', 'respondida');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', '!=', 'respondida');
    }

    public function scopeBuscar($query, $texto)
    {
        return $query->where(function ($query) use ($texto) {
            $query->where('asunto', 'LIKE', "%{$texto}%")
                ->orWhere('descripcion', 'LIKE', "%{$texto}%")
                ->orWhere('id', $texto)
                ->orWhereHas('user', fn ($user) => $user->where('name', 'LIKE', "%{$texto}%")->orWhere('email', 'LIKE', "%{$texto}%"))
                ->orWhereHas('assignee', fn ($user) => $user->where('name', 'LIKE', "%{$texto}%"));
        });
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'en_revision' => 'En revisión',
            'respondida' => 'Respondida',
            'cerrada' => 'Cerrada',
            default => 'Radicada',
        };
    }

    public function getPrioridadLabelAttribute(): string
    {
        return match ($this->prioridad) {
            'alta' => 'Alta',
            'baja' => 'Baja',
            default => 'Media',
        };
    }

    public function scopePrioridad($query, string $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->fecha_limite_respuesta || in_array($this->estado, ['respondida', 'cerrada'], true)) {
            return false;
        }

        return $this->fecha_limite_respuesta->toDateString() < app(CalendarioLaboralColombia::class)->hoy()->toDateString();
    }
    public function getElapsedDaysAttribute(): int
    {
        return max(0, app(CalendarioLaboralColombia::class)->diferenciaDiasHabiles(
            $this->fecha_radicacion,
            app(CalendarioLaboralColombia::class)->hoy(),
        ));
    }

    public function getRemainingDaysAttribute(): ?int
    {
        return $this->fecha_limite_respuesta
            ? app(CalendarioLaboralColombia::class)->diferenciaDiasHabiles(
                app(CalendarioLaboralColombia::class)->hoy(),
                $this->fecha_limite_respuesta,
            )
            : null;
    }
}
