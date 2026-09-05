<?php

namespace App\Models;

use App\Enums\PqrCommunicationCleanupStatus;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation as OperationType;
use App\Enums\PqrCommunicationOperationStatus;
use App\Enums\PqrCommunicationResultCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PqrCommunicationOperation extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['idempotency_key', 'payload_sha256', 'actor_uuid', 'result_code', 'cleanup_manifest'];

    private const IMMUTABLE = ['actor_uuid', 'idempotency_key', 'actor_id', 'pqr_id', 'operation', 'payload_sha256'];

    protected static function booted(): void
    {
        static::updating(function (self $operation): void {
            foreach (self::IMMUTABLE as $attribute) {
                if ($operation->isDirty($attribute)) {
                    throw new \LogicException("{$attribute} es inmutable después de crear la operación.");
                }
            }
            if ($operation->isDirty('cleanup_manifest')
                && ($operation->getRawOriginal('cleanup_manifest') !== null
                    || $operation->getOriginal('status') === PqrCommunicationOperationStatus::Completed)) {
                throw new \LogicException('El manifiesto de limpieza es inmutable después de registrarse.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'operation' => OperationType::class,
            'result_code' => PqrCommunicationResultCode::class,
            'status' => PqrCommunicationOperationStatus::class,
            'cleanup_status' => PqrCommunicationCleanupStatus::class,
            'cleanup_manifest' => 'array',
            'notification_status' => PqrCommunicationNotificationStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function pqr(): BelongsTo
    {
        return $this->belongsTo(Pqr::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(PqrReply::class, 'pqr_reply_id');
    }

    public function internalComment(): BelongsTo
    {
        return $this->belongsTo(PqrInternalComment::class, 'pqr_internal_comment_id');
    }
}
