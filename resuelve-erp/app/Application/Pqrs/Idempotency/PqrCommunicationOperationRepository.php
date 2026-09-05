<?php

namespace App\Application\Pqrs\Idempotency;

use App\Enums\PqrCommunicationCleanupStatus;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation;
use App\Enums\PqrCommunicationOperationStatus;
use App\Enums\PqrCommunicationResultCode;
use App\Models\Pqr;
use App\Models\PqrCommunicationOperation as OperationModel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PqrCommunicationOperationRepository
{
    public function lookupScope(Pqr $pqr, ?User $actor, PqrCommunicationOperation $operation, string $key, ?string $connection = null): ?OperationModel
    {
        if (! Str::isUuid($key)) {
            throw new InvalidArgumentException('La clave de idempotencia debe ser un UUID válido.');
        }
        $model = new OperationModel;
        $model->setConnection($connection);
        $record = $model->newQuery()->where('idempotency_key', $key)->first();
        if ($record && ($record->actor_id === null
            || (int) $record->actor_id !== (int) $actor?->getKey()
            || (int) $record->pqr_id !== (int) $pqr->getKey()
            || $record->operation !== $operation)) {
            throw new PqrCommunicationOperationConflict('La clave de idempotencia ya fue usada en un ámbito diferente.');
        }

        return $record;
    }

    public function claim(Pqr $pqr, ?User $actor, PqrCommunicationOperation $operation, string $key, string $payloadSha256, ?string $connection = null, PqrCommunicationNotificationStatus $notificationStatus = PqrCommunicationNotificationStatus::NotRequired): PqrCommunicationOperationClaim
    {
        $this->validateIdentifiers($key, $payloadSha256);
        $actorUuid = (string) Str::uuid();
        $database = DB::connection($connection);

        try {
            $record = $database->transaction(function () use ($pqr, $actor, $actorUuid, $operation, $key, $payloadSha256, $connection, $notificationStatus): OperationModel {
                $record = new OperationModel;
                $record->setConnection($connection);
                $record->forceFill([
                    'pqr_id' => $pqr->getKey(), 'actor_id' => $actor?->getKey(), 'actor_uuid' => $actorUuid,
                    'operation' => $operation, 'idempotency_key' => $key, 'payload_sha256' => $payloadSha256,
                    'status' => PqrCommunicationOperationStatus::InProgress,
                    'cleanup_status' => PqrCommunicationCleanupStatus::NotRequired,
                    'notification_status' => $notificationStatus,
                ])->save();

                return $record;
            });

            return new PqrCommunicationOperationClaim($record, true);
        } catch (QueryException $exception) {
            if (! $this->isExpectedIdempotencyCollision($exception, $database->getDriverName())) {
                throw $exception;
            }
            $model = new OperationModel;
            $model->setConnection($connection);
            $record = $model->newQuery()->where('idempotency_key', $key)->first();
            if (! $record) {
                throw $exception;
            }
            if ($record->actor_id === null
                || (int) $record->actor_id !== (int) $actor?->getKey()
                || (int) $record->pqr_id !== (int) $pqr->getKey()
                || $record->operation !== $operation
                || ! hash_equals($record->payload_sha256, $payloadSha256)) {
                throw new PqrCommunicationOperationConflict('La clave de idempotencia ya fue usada en un ámbito diferente.');
            }

            return new PqrCommunicationOperationClaim($record, false);
        }
    }

    public function complete(OperationModel $operation, PqrCommunicationResultCode $resultCode, ?int $referenceId = null): OperationModel
    {
        return DB::transaction(function () use ($operation, $resultCode, $referenceId): OperationModel {
            $locked = OperationModel::query()->lockForUpdate()->findOrFail($operation->getKey());
            if ($locked->status === PqrCommunicationOperationStatus::Completed) {
                return $locked;
            }
            $replyResults = [PqrCommunicationResultCode::ReplyRecorded, PqrCommunicationResultCode::DraftCreated, PqrCommunicationResultCode::DraftUpdated, PqrCommunicationResultCode::DraftSent, PqrCommunicationResultCode::DraftDeleted, PqrCommunicationResultCode::ReplySent];
            $isReply = $locked->operation !== PqrCommunicationOperation::CreateInternalComment;
            if (($isReply && $resultCode === PqrCommunicationResultCode::CommentRecorded) || (! $isReply && in_array($resultCode, $replyResults, true))) {
                throw new InvalidArgumentException('El código de resultado no corresponde a la operación.');
            }
            $locked->forceFill([
                'result_code' => $resultCode,
                'pqr_reply_id' => $isReply ? $referenceId : null,
                'pqr_internal_comment_id' => $isReply ? null : $referenceId,
                'status' => PqrCommunicationOperationStatus::Completed, 'completed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    /** @param list<array{storage_key:string,position:int,sha256:string}> $manifest */
    public function scheduleCleanup(OperationModel $operation, array $manifest): OperationModel
    {
        foreach ($manifest as $candidate) {
            if (! is_array($candidate)
                || ! is_string($candidate['storage_key'] ?? null)
                || ! Str::isUuid($candidate['storage_key'])
                || ! is_int($candidate['position'] ?? null)
                || $candidate['position'] < 0
                || ! preg_match('/\A[a-f0-9]{64}\z/', $candidate['sha256'] ?? '')) {
                throw new InvalidArgumentException('El manifiesto de limpieza contiene una referencia técnica inválida.');
            }
        }

        return DB::transaction(function () use ($operation, $manifest): OperationModel {
            $locked = OperationModel::query()->lockForUpdate()->findOrFail($operation->getKey());
            $locked->forceFill([
                'cleanup_status' => $manifest === []
                    ? PqrCommunicationCleanupStatus::NotRequired
                    : PqrCommunicationCleanupStatus::Pending,
                'cleanup_manifest' => $manifest === [] ? null : $manifest,
            ])->save();

            return $locked->refresh();
        });
    }

    public function completeCleanup(OperationModel $operation): OperationModel
    {
        return DB::transaction(function () use ($operation): OperationModel {
            $locked = OperationModel::query()->lockForUpdate()->findOrFail($operation->getKey());
            if ($locked->cleanup_status === PqrCommunicationCleanupStatus::Pending) {
                $locked->forceFill(['cleanup_status' => PqrCommunicationCleanupStatus::Completed])->save();
            }

            return $locked->refresh();
        });
    }

    public static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode(self::canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function validateIdentifiers(string $key, string $hash): void
    {
        if (! Str::isUuid($key)) {
            throw new InvalidArgumentException('La clave de idempotencia debe ser un UUID válido.');
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/', $hash)) {
            throw new InvalidArgumentException('La huella del payload debe ser SHA-256 hexadecimal en minúsculas.');
        }
    }

    private function isExpectedIdempotencyCollision(QueryException $exception, string $driver): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        return ($driver === 'mysql' && $driverCode === 1062 && str_contains($message, 'pqr_comm_idempotency_key_unique'))
            || ($driver === 'sqlite' && in_array($driverCode, [19, 2067], true) && str_contains($message, 'pqr_communication_operations.idempotency_key'));
    }

    private static function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::canonicalize($item);
            }
        }

        return $value;
    }
}
