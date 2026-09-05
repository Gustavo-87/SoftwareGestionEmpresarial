<?php

namespace App\Console\Commands;

use App\Application\Notificaciones\ReconciliarNotificacionRespuestaPqrs;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationOperation;
use App\Enums\PqrCommunicationOperationStatus;
use App\Models\PqrCommunicationOperation as OperationModel;
use Illuminate\Console\Command;

final class ReconciliarNotificacionesRespuestaPqrs extends Command
{
    protected $signature = 'pqrs:reconcile-response-notifications {--limit=100}';

    protected $description = 'Reconcilia avisos pendientes de respuestas PQRS respaldados por el ledger.';

    public function handle(ReconciliarNotificacionRespuestaPqrs $reconciliador): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $counts = ['processed' => 0, 'completed' => 0, 'no_recipient' => 0, 'pending' => 0];
        $ids = OperationModel::query()
            ->where('status', PqrCommunicationOperationStatus::Completed)
            ->whereIn('operation', [PqrCommunicationOperation::SendDraft, PqrCommunicationOperation::SendReply])
            ->where('notification_status', PqrCommunicationNotificationStatus::Pending)
            ->orderBy('id')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            $status = $reconciliador->ejecutar((int) $id);
            $counts['processed']++;
            $counts[$status->value]++;
        }

        $this->line(sprintf(
            'processed=%d completed=%d no_recipient=%d pending=%d',
            $counts['processed'], $counts['completed'], $counts['no_recipient'], $counts['pending'],
        ));

        return self::SUCCESS;
    }
}
