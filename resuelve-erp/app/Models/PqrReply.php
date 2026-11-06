<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PqrReply extends Model
{
    use SoftDeletes;

    protected $fillable = ['pqr_id', 'user_id', 'body', 'is_draft', 'attachments', 'sent_at', 'anulada_at', 'anulada_por_user_id', 'motivo_anulacion'];

    protected function casts(): array
    {
        return ['is_draft' => 'boolean', 'attachments' => 'array', 'sent_at' => 'datetime', 'deleted_at' => 'datetime', 'anulada_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $reply): void {
            // HU-PQRS-04: única mutación permitida sobre una respuesta oficial
            // (ya enviada y no anulada): fijar los campos de anulación. El
            // contenido, los adjuntos, el envío, la PQR y el autor siguen
            // inmutables; una respuesta anulada no admite ningún cambio.
            if ($reply->isDirty(['anulada_at', 'anulada_por_user_id', 'motivo_anulacion'])) {
                $soloAnulacion = ! collect(['pqr_id', 'user_id', 'body', 'is_draft', 'attachments', 'sent_at'])
                    ->contains(fn (string $campo): bool => $reply->isDirty($campo));
                $transicionValida = $reply->getOriginal('is_draft') === false
                    && $reply->getOriginal('sent_at') !== null
                    && $reply->getOriginal('anulada_at') === null
                    && $reply->anulada_at !== null
                    && $soloAnulacion;

                if ($transicionValida) {
                    return;
                }
            }

            if (! $reply->getOriginal('is_draft') || $reply->getOriginal('sent_at') !== null) {
                throw new \LogicException('Una respuesta oficial es inmutable.');
            }
            foreach (['pqr_id', 'user_id'] as $attribute) {
                if ($reply->isDirty($attribute)) {
                    throw new \LogicException('La identidad de la respuesta es inmutable.');
                }
            }
        });
        static::deleting(function (self $reply): void {
            if ($reply->isForceDeleting()) {
                throw new \LogicException('Las respuestas y borradores no admiten eliminación física.');
            }
            if (! $reply->is_draft || $reply->sent_at !== null) {
                throw new \LogicException('Una respuesta oficial no puede eliminarse.');
            }
        });
        static::restoring(fn () => throw new \LogicException('Un borrador eliminado no puede restaurarse.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function anuladaPor()
    {
        return $this->belongsTo(User::class, 'anulada_por_user_id');
    }

    /** Anulación trazable (HU-PQRS-04): sin efecto sobre el contenido original. */
    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    public function pqr()
    {
        return $this->belongsTo(Pqr::class);
    }

    public function communicationOperations()
    {
        return $this->hasMany(PqrCommunicationOperation::class);
    }
}
