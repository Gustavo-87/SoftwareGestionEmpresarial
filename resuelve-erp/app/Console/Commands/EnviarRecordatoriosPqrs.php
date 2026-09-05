<?php

namespace App\Console\Commands;

use App\Application\Contexto\ContextResolver;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Models\Copropiedad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnviarRecordatoriosPqrs extends Command
{
    protected $signature = 'pqrs:send-reminders';

    protected $description = 'Envía recordatorios contextuales de PQRS próximas a vencer';

    public function __construct(
        private readonly ContextResolver $contextResolver,
        private readonly ConsultaPqrsContextuales $consultaPqrs,
        private readonly EmitirNotificacionPqrs $notificaciones,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $enviados = 0;

        Copropiedad::query()->orderBy('id')->each(function (Copropiedad $copropiedad) use (&$enviados): void {
            $contexto = $this->contextResolver->resolverExplicito(
                $copropiedad->organizacion_id,
                $copropiedad->id
            );
            $pqrs = $this->consultaPqrs->para($contexto)
                ->whereIn('estado', ['radicada', 'en_revision'])
                ->whereBetween('fecha_limite_respuesta', [today(), today()->addDays(3)])
                ->where(fn ($query) => $query
                    ->whereNull('last_reminder_at')
                    ->orWhereDate('last_reminder_at', '<', today()))
                ->get();

            foreach ($pqrs as $pqr) {
                $reservado = DB::transaction(function () use ($contexto, $pqr): bool {
                    $actual = $this->consultaPqrs->para($contexto)
                        ->whereKey($pqr->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($actual->last_reminder_at?->isToday()) {
                        return false;
                    }

                    $actual->update(['last_reminder_at' => now()]);

                    return true;
                });

                if (! $reservado) {
                    continue;
                }

                $this->notificaciones->ejecutar($contexto, $pqr, 'pqr_recordatorio_vencimiento');
                $enviados++;
            }
        });

        $this->info("Recordatorios enviados: {$enviados}");

        return self::SUCCESS;
    }
}
