<?php

namespace App\Application\Notificaciones;

use App\Application\Contexto\ContextResolver;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation;
use App\Enums\PqrCommunicationOperationStatus;
use App\Jobs\EnviarCorreoNotificacionPqrs;
use App\Models\PqrCommunicationOperation as OperationModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class ReconciliarNotificacionRespuestaPqrs
{
    public function __construct(
        private readonly ContextResolver $contextos,
        private readonly ResolverDestinatariosNotificacionPqrs $destinatarios,
        private readonly EmitirNotificacionPqrs $emisor,
    ) {}

    public function ejecutar(OperationModel|int $operation): PqrCommunicationNotificationStatus
    {
        $id = $operation instanceof OperationModel ? $operation->getKey() : $operation;

        try {
            return DB::transaction(function () use ($id): PqrCommunicationNotificationStatus {
                $locked = OperationModel::query()->lockForUpdate()->with('pqr.user')->findOrFail($id);
                if ($locked->status !== PqrCommunicationOperationStatus::Completed
                    || ! in_array($locked->operation, [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply], true)
                    || $locked->notification_status !== PqrCommunicationNotificationStatus::Pending) {
                    return $locked->notification_status;
                }

                $pqr = $locked->pqr;
                $contexto = $this->contextos->resolverExplicito($pqr->organizacion_id, $pqr->copropiedad_id);
                $destinatario = $this->destinatarios->resolver($contexto, $pqr, 'pqr_respuesta_enviada')->first(fn (User $user) => $user->id === $pqr->user_id);
                if (! $destinatario) {
                    $locked->forceFill(['notification_status' => PqrCommunicationNotificationStatus::NoRecipient])->save();

                    return PqrCommunicationNotificationStatus::NoRecipient;
                }

                $alreadyNotified = $destinatario->notifications()
                    ->where('data->operation_id', $locked->id)
                    ->exists();
                if (! $alreadyNotified) {
                    $destinatario->notify($this->emisor->paraOperacion($pqr, 'pqr_respuesta_enviada', $locked->id));
                }
                Queue::connection('database')->push(new EnviarCorreoNotificacionPqrs(
                    $pqr->organizacion_id,
                    $pqr->copropiedad_id,
                    $pqr->id,
                    $destinatario->id,
                    'pqr_respuesta_enviada',
                    $locked->id,
                ));
                $locked->forceFill(['notification_status' => PqrCommunicationNotificationStatus::Completed])->save();

                return PqrCommunicationNotificationStatus::Completed;
            });
        } catch (Throwable) {
            return PqrCommunicationNotificationStatus::Pending;
        }
    }

    public function procesarCorreo(int $operationId): void
    {
        $operation = OperationModel::query()->with('pqr')->find($operationId);
        if (! $operation
            || $operation->status !== PqrCommunicationOperationStatus::Completed
            || ! in_array($operation->operation, [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply], true)
            || $operation->notification_status !== PqrCommunicationNotificationStatus::Completed) {
            return;
        }
        $pqr = $operation->pqr;
        if (! $this->emisor->enviarCorreo($pqr->organizacion_id, $pqr->copropiedad_id, $pqr->id, $pqr->user_id, 'pqr_respuesta_enviada')) {
            $this->marcarSinDestinatario($operationId);
        }
    }

    public function marcarPendiente(int $operationId): void
    {
        DB::transaction(function () use ($operationId): void {
            $operation = OperationModel::query()->lockForUpdate()->find($operationId);
            if ($operation && $operation->notification_status === PqrCommunicationNotificationStatus::Completed) {
                $operation->forceFill(['notification_status' => PqrCommunicationNotificationStatus::Pending])->save();
            }
        });
    }

    private function marcarSinDestinatario(int $operationId): void
    {
        DB::transaction(function () use ($operationId): void {
            $operation = OperationModel::query()->lockForUpdate()->find($operationId);
            if ($operation && $operation->notification_status === PqrCommunicationNotificationStatus::Completed) {
                $operation->forceFill(['notification_status' => PqrCommunicationNotificationStatus::NoRecipient])->save();
            }
        });
    }
}
