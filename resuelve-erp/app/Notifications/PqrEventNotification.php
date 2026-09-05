<?php
namespace App\Notifications;
use App\Models\Pqr;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class PqrEventNotification extends Notification {
    use Queueable;
    public string $event;
    public string $title;
    public string $message;

    public function __construct(public Pqr $pqr, string $event, ?string $title = null, ?string $message = null, public ?int $operationId = null) {
        $this->event = $message === null ? 'pqr_evento_heredado' : $event;
        $this->title = $message === null ? $event : (string) $title;
        $this->message = $message === null ? (string) $title : $message;
    }
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array { return array_filter(['resource_type' => 'pqr', 'resource_id' => $this->pqr->id, 'event' => $this->event, 'organizacion_id' => $this->pqr->organizacion_id, 'copropiedad_id' => $this->pqr->copropiedad_id, 'title' => $this->title, 'message' => $this->message, 'operation_id' => $this->operationId], fn ($value) => $value !== null); }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject($this->title)->greeting("Hola {$notifiable->name}")->line($this->message)->line('Este mensaje fue generado automáticamente por Resuelve.'); }
}
