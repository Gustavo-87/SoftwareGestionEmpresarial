<?php

namespace App\Jobs;

use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Application\Notificaciones\ReconciliarNotificacionRespuestaPqrs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EnviarCorreoNotificacionPqrs implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    private ?int $operationId = null;

    public function __construct(
        public readonly int $organizacionId,
        public readonly int $copropiedadId,
        public readonly int $pqrId,
        public readonly int $usuarioId,
        public readonly string $event,
        ?int $operationId = null,
    ) {
        $this->operationId = $operationId;
    }

    public function uniqueId(): string
    {
        $operationId = $this->safeOperationId();

        return $operationId === null
            ? implode(':', [$this->event, 'pqr', $this->pqrId, $this->usuarioId, 'mail'])
            : "operation:{$operationId}:mail";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(EmitirNotificacionPqrs $emitir, ?ReconciliarNotificacionRespuestaPqrs $reconciliador = null): void
    {
        $operationId = $this->safeOperationId();
        if ($operationId !== null) {
            ($reconciliador ?? app(ReconciliarNotificacionRespuestaPqrs::class))->procesarCorreo($operationId);

            return;
        }
        $emitir->enviarCorreo(
            $this->organizacionId,
            $this->copropiedadId,
            $this->pqrId,
            $this->usuarioId,
            $this->event,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $operationId = $this->safeOperationId();
        if ($operationId !== null) {
            app(ReconciliarNotificacionRespuestaPqrs::class)->marcarPendiente($operationId);
        }
        Log::error('Falló definitivamente el envío de correo de una notificación PQRS.', [
            'organizacion_id' => $this->organizacionId,
            'copropiedad_id' => $this->copropiedadId,
            'pqr_id' => $this->pqrId,
            'usuario_id' => $this->usuarioId,
            'event' => $this->event,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function safeOperationId(): ?int
    {
        return isset($this->operationId) ? $this->operationId : null;
    }
}
