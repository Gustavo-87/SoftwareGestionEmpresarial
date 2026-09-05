<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PqrReply extends Model {
    use SoftDeletes;
    protected $fillable = ['pqr_id', 'user_id', 'body', 'is_draft', 'attachments', 'sent_at'];
    protected function casts(): array { return ['is_draft' => 'boolean', 'attachments' => 'array', 'sent_at' => 'datetime', 'deleted_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $reply): void {
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
    public function user() { return $this->belongsTo(User::class); }
    public function pqr() { return $this->belongsTo(Pqr::class); }
    public function communicationOperations() { return $this->hasMany(PqrCommunicationOperation::class); }
}
