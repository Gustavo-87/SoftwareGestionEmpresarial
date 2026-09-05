<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Notificaciones\ReconciliarNotificacionRespuestaPqrs;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationCleanupStatus;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation;
use App\Enums\PqrCommunicationOperationStatus;
use App\Enums\PqrCommunicationResultCode;
use App\Models\Pqr;
use App\Models\PqrCommunicationOperation as OperationModel;
use App\Models\PqrReply;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class GestionarCicloRespuestaPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly ConsultaPqrsContextuales $consulta,
        private readonly VisibilidadBorradoresPqrs $visibilidad,
        private readonly RegistrarActuacionPqrs $actuaciones,
        private readonly PqrCommunicationOperationRepository $operations,
        private readonly ReconciliarNotificacionRespuestaPqrs $notificaciones,
    ) {}

    public function autorizarCreacion(ContextoOperativo $contexto, Pqr $pqr): Pqr
    {
        $pqr = $this->consulta->resolver($contexto, $pqr->getKey());
        if (! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException;
        }

        return $pqr;
    }

    /** @return array{Pqr,PqrReply} */
    public function autorizarMutacion(ContextoOperativo $contexto, Pqr $pqr, int $replyId): array
    {
        $pqr = $this->consulta->resolver($contexto, $pqr->getKey());
        $reply = PqrReply::withTrashed()->where('pqr_id', $pqr->id)->findOrFail($replyId);
        $state = $this->visibilidad->estadoMutacion($contexto, $pqr, $reply);
        abort_if($state === 'hidden', 404);
        if ($state !== 'allowed' || ! $this->autorizacion->puedeGestionarPqr($contexto, $pqr)) {
            throw new AuthorizationException;
        }

        return [$pqr, $reply];
    }

    /** @param list<UploadedFile> $files */
    public function create(ContextoOperativo $contexto, User $actor, Pqr $pqr, array $data, array $files, string $key, bool $draft): PqrReply
    {
        return $this->mutate(
            $contexto, $actor, $pqr, null, $data, $files, $key,
            $draft ? PqrCommunicationOperation::CreateDraft : PqrCommunicationOperation::SendReply,
            $draft ? PqrCommunicationResultCode::DraftCreated : PqrCommunicationResultCode::ReplySent,
        );
    }

    /** @param list<UploadedFile> $files */
    public function update(ContextoOperativo $contexto, User $actor, Pqr $pqr, PqrReply $reply, array $data, array $files, string $key): PqrReply
    {
        return $this->mutate($contexto, $actor, $pqr, $reply, $data, $files, $key, PqrCommunicationOperation::UpdateDraft, PqrCommunicationResultCode::DraftUpdated);
    }

    public function send(ContextoOperativo $contexto, User $actor, Pqr $pqr, PqrReply $reply, string $key): PqrReply
    {
        return $this->mutate($contexto, $actor, $pqr, $reply, [], [], $key, PqrCommunicationOperation::SendDraft, PqrCommunicationResultCode::DraftSent);
    }

    public function delete(ContextoOperativo $contexto, User $actor, Pqr $pqr, PqrReply $reply, string $key): PqrReply
    {
        return $this->mutate($contexto, $actor, $pqr, $reply, [], [], $key, PqrCommunicationOperation::DeleteDraft, PqrCommunicationResultCode::DraftDeleted);
    }

    public function lookup(Pqr $pqr, User $actor, PqrCommunicationOperation $operation, string $key): ?OperationModel
    {
        return $this->operations->lookupScope($pqr, $actor, $operation, $key);
    }

    /** @param list<UploadedFile> $files */
    public static function fingerprint(PqrCommunicationOperation $operation, int $pqrId, ?int $replyId, ?string $body, array $files, array $persistedAttachments = []): string
    {
        $normalize = static fn (?string $value): ?string => $value === null ? null : preg_replace('/\R/u', "\n", trim($value));
        $newAttachments = collect($files)->values()->map(function (object $file, int $position): array {
            $sha256 = hash_file('sha256', $file->getRealPath());
            if (! is_string($sha256)) throw new \RuntimeException('No fue posible calcular la huella técnica del adjunto.');

            return [
                'logical_id' => "attachment:{$position}",
                'size' => (int) $file->getSize(),
                'mime' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
                'sha256' => $sha256,
            ];
        })->values()->all();
        $persisted = collect($persistedAttachments)->values()->map(fn (array $file, int $position) => [
            'logical_id' => "attachment:{$position}",
            'size' => (int) ($file['size'] ?? 0),
            'mime' => $file['mime'] ?? null,
            'sha256' => $file['sha256'] ?? null,
        ])->values()->all();
        foreach ($persisted as $attachment) {
            if (! preg_match('/\A[a-f0-9]{64}\z/', $attachment['sha256'] ?? '')) {
                throw new \InvalidArgumentException('El adjunto persistido no dispone de una huella técnica válida.');
            }
        }

        return PqrCommunicationOperationRepository::fingerprint([
            'operation' => $operation->value, 'pqr_id' => $pqrId, 'reply_id' => $replyId,
            'body' => $normalize($body), 'attachments' => $newAttachments !== [] ? $newAttachments : $persisted,
        ]);
    }

    /** @param list<UploadedFile> $files */
    private function mutate(ContextoOperativo $contexto, User $actor, Pqr $pqr, ?PqrReply $reply, array $data, array $files, string $key, PqrCommunicationOperation $operation, PqrCommunicationResultCode $result): PqrReply
    {
        $createdPaths = [];
        $operationRecord = null;

        try {
            $answer = DB::transaction(function () use ($contexto, $actor, $pqr, $reply, $data, $files, $key, $operation, $result, &$createdPaths, &$operationRecord): PqrReply {
                $lockedPqr = Pqr::query()->lockForUpdate()->findOrFail($pqr->id);
                $lockedReply = $reply ? PqrReply::withTrashed()->lockForUpdate()->where('pqr_id', $lockedPqr->id)->findOrFail($reply->id) : null;
                $persisted = $lockedReply ? $this->ensureDurableAttachmentHashes($lockedReply) : [];
                $body = $operation === PqrCommunicationOperation::UpdateDraft ? ($data['body'] ?? null) : ($lockedReply?->body ?? ($data['body'] ?? null));
                $fingerprint = self::fingerprint($operation, $lockedPqr->id, $lockedReply?->id, $body, $files, $persisted);
                $claim = $this->operations->claim(
                    $lockedPqr, $actor, $operation, $key, $fingerprint, null,
                    in_array($operation, [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply], true)
                        ? PqrCommunicationNotificationStatus::Pending : PqrCommunicationNotificationStatus::NotRequired,
                );
                $operationRecord = $claim->operation;
                if (! $claim->claimed) {
                    if ($claim->operation->status !== PqrCommunicationOperationStatus::Completed) {
                        throw ValidationException::withMessages(['idempotency_key' => 'La operación continúa en curso.']);
                    }

                    return PqrReply::withTrashed()->findOrFail($claim->operation->pqr_reply_id);
                }
                if (! $this->autorizacion->puedeGestionarPqr($contexto, $lockedPqr)) {
                    throw new AuthorizationException;
                }
                if ($lockedReply && (! $lockedReply->is_draft || $lockedReply->trashed() || $lockedReply->user_id !== $actor->id)) {
                    throw ValidationException::withMessages(['reply' => 'El borrador ya no admite esta operación.']);
                }
                $sending = in_array($operation, [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply], true);
                if ($sending && ! in_array($lockedPqr->estado, ['radicada', 'en_revision'], true)) {
                    throw ValidationException::withMessages(['action' => 'El estado de la PQRS no permite responder.']);
                }
                if ($sending && PqrReply::query()->where('pqr_id', $lockedPqr->id)->where('is_draft', false)->exists()) {
                    throw ValidationException::withMessages(['action' => 'La PQRS ya tiene respuesta oficial.']);
                }

                $attachments = [];
                foreach ($files as $index => $file) {
                    $path = method_exists($file, 'storeAs')
                        ? $file->storeAs("pqrs/{$lockedPqr->id}/communications/{$key}", sprintf('%02d', $index), 'local')
                        : $file->store("pqrs/{$lockedPqr->id}/communications/{$key}");
                    $createdPaths[] = $path;
                    $sha256 = hash_file('sha256', $file->getRealPath());
                    if (! is_string($sha256)) throw new \RuntimeException('No fue posible calcular la huella técnica del adjunto.');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(), 'path' => $path,
                        'storage_key' => $key, 'position' => $index,
                        'size' => $file->getSize(),
                        'mime' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
                        'sha256' => $sha256,
                    ];
                }

                $cleanupManifest = [];
                if (! $lockedReply) {
                    $lockedReply = $lockedPqr->replies()->create(['user_id' => $actor->id, 'body' => $data['body'], 'is_draft' => ! $sending, 'attachments' => $attachments, 'sent_at' => $sending ? now() : null]);
                } elseif ($operation === PqrCommunicationOperation::DeleteDraft) {
                    $lockedReply->delete();
                } else {
                    $lockedReply->body = $operation === PqrCommunicationOperation::UpdateDraft ? $data['body'] : $lockedReply->body;
                    if ($files !== []) {
                        $cleanupManifest = $this->cleanupManifest($persisted);
                        $lockedReply->attachments = $attachments;
                    }
                    if ($sending) {
                        $lockedReply->is_draft = false;
                        $lockedReply->sent_at = now();
                    }
                    $lockedReply->save();
                }
                if ($sending) {
                    $lockedPqr->update(['estado' => 'respondida']);
                }
                $this->actuaciones->registrar($lockedPqr, $actor, match ($operation) {
                    PqrCommunicationOperation::CreateDraft => 'drafted_reply', PqrCommunicationOperation::UpdateDraft => 'updated_draft',
                    PqrCommunicationOperation::DeleteDraft => 'deleted_draft', default => 'sent_reply',
                }, match ($operation) {
                    PqrCommunicationOperation::CreateDraft => 'Guardó un borrador de respuesta.', PqrCommunicationOperation::UpdateDraft => 'Actualizó un borrador de respuesta.',
                    PqrCommunicationOperation::DeleteDraft => 'Eliminó un borrador de respuesta.', default => 'Envió una respuesta al residente.',
                });
                if ($cleanupManifest !== []) {
                    $this->operations->scheduleCleanup($claim->operation, $cleanupManifest);
                }
                $operationRecord = $this->operations->complete($claim->operation, $result, $lockedReply->id);

                return $operation === PqrCommunicationOperation::DeleteDraft ? $lockedReply : $lockedReply->refresh();
            });
        } catch (\Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        if ($operationRecord instanceof OperationModel && $operationRecord->cleanup_status === PqrCommunicationCleanupStatus::Pending) {
            $this->reconcileCleanup($pqr, $operationRecord);
        }
        if ($operationRecord instanceof OperationModel && in_array($operation, [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply], true)) {
            $this->notificaciones->ejecutar($operationRecord);
        }

        return $answer;
    }

    public function reconcileCleanup(Pqr $pqr, OperationModel $operation): void
    {
        $operation = OperationModel::query()->where('pqr_id', $pqr->id)->findOrFail($operation->id);
        if ($operation->cleanup_status !== PqrCommunicationCleanupStatus::Pending) {
            return;
        }
        foreach ($operation->cleanup_manifest ?? [] as $candidate) {
            $path = $this->deterministicPath($pqr->id, $candidate['storage_key'], (int) $candidate['position']);
            $referenced = PqrReply::withTrashed()->where('pqr_id', $pqr->id)->get(['attachments'])
                ->contains(fn (PqrReply $reply) => collect($reply->attachments ?? [])->contains(fn (array $attachment) => ($attachment['path'] ?? null) === $path));
            if ($referenced || ! Storage::disk('local')->exists($path)) {
                continue;
            }
            $actualHash = hash_file('sha256', Storage::disk('local')->path($path));
            if (! is_string($actualHash)) {
                throw new \RuntimeException('La limpieza quedó pendiente porque no fue posible verificar el archivo candidato.');
            }
            if (! hash_equals($candidate['sha256'], $actualHash)) {
                throw new \RuntimeException('La limpieza quedó pendiente porque el archivo candidato no coincide con su huella técnica.');
            }
            if (! Storage::disk('local')->delete($path) || Storage::disk('local')->exists($path)) {
                throw new \RuntimeException('La limpieza de adjuntos quedó pendiente para reintento.');
            }
        }
        $this->operations->completeCleanup($operation);
    }

    /** @return list<array<string,mixed>> */
    private function ensureDurableAttachmentHashes(PqrReply $reply): array
    {
        $attachments = $reply->attachments ?? [];
        $changed = false;
        foreach ($attachments as $position => &$attachment) {
            if (! preg_match('/\A[a-f0-9]{64}\z/', $attachment['sha256'] ?? '')) {
                $path = $attachment['path'] ?? null;
                if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                    throw ValidationException::withMessages(['attachments' => 'Un adjunto persistido no está disponible para verificar la operación.']);
                }
                $sha256 = hash_file('sha256', Storage::disk('local')->path($path));
                if (! is_string($sha256)) {
                    throw ValidationException::withMessages(['attachments' => 'No fue posible verificar un adjunto persistido.']);
                }
                $attachment['sha256'] = $sha256;
                $changed = true;
            }
            if (isset($attachment['storage_key'], $attachment['position'])
                && $attachment['path'] !== $this->deterministicPath($reply->pqr_id, $attachment['storage_key'], (int) $attachment['position'])) {
                throw ValidationException::withMessages(['attachments' => 'La referencia técnica del adjunto persistido no es coherente.']);
            }
        }
        unset($attachment);
        if ($changed) {
            $reply->attachments = $attachments;
            $reply->save();
        }

        return $attachments;
    }

    /** @param list<array<string,mixed>> $attachments
     *  @return list<array{storage_key:string,position:int,sha256:string}>
     */
    private function cleanupManifest(array $attachments): array
    {
        return collect($attachments)->filter(fn (array $attachment) => isset($attachment['storage_key'], $attachment['position'], $attachment['sha256']))
            ->map(fn (array $attachment) => [
                'storage_key' => (string) $attachment['storage_key'],
                'position' => (int) $attachment['position'],
                'sha256' => (string) $attachment['sha256'],
            ])->values()->all();
    }

    private function deterministicPath(int $pqrId, string $storageKey, int $position): string
    {
        return "pqrs/{$pqrId}/communications/{$storageKey}/".sprintf('%02d', $position);
    }
}
