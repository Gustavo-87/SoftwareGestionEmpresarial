<?php

namespace App\Application\Pqrs;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Configuracion\ResolverDiasRespuestaContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Domain\Pqrs\CalendarioLaboralColombia;
use App\Models\AutomationRule;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PresentarPqrs
{
    public function __construct(
        private readonly AutorizacionContextual $autorizacion,
        private readonly RegistrarActuacionPqrs $actuaciones,
        private readonly EmitirNotificacionPqrs $notificaciones,
        private readonly CalendarioLaboralColombia $calendario,
        private readonly ResolverDiasRespuestaContextual $diasRespuesta,
    ) {}

    /** @param iterable<object> $adjuntos */
    public function ejecutar(ContextoOperativo $contexto, User $usuario, array $datos, iterable $adjuntos = []): Pqr
    {
        if (! $this->autorizacion->tienePermiso($contexto, 'pqrs.crear')) {
            throw new AuthorizationException();
        }

        $fechaRadicacion = $this->calendario->hoy();
        $datosPermitidos = array_intersect_key($datos, array_flip(['asunto', 'descripcion', 'tipo_pqr_id']));
        $datosPermitidos['fecha_radicacion'] = $fechaRadicacion->toDateString();
        $datosPermitidos['fecha_limite_respuesta'] = $this->calendario
            ->sumarDiasHabiles($fechaRadicacion, $this->diasRespuesta->resolver($contexto))
            ->toDateString();

        $pqr = DB::transaction(function () use ($contexto, $usuario, $datosPermitidos, $adjuntos): Pqr {
            if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
                throw new RuntimeException('No es posible radicar la PQR porque el contexto institucional es inconsistente.');
            }

            $pqr = new Pqr($datosPermitidos);
            $pqr->prioridad = 'media';
            $pqr->user()->associate($usuario);
            $pqr->organizacion()->associate($contexto->organizacion);
            $pqr->copropiedad()->associate($contexto->copropiedad);
            $pqr->save();

            if ($rule = AutomationRule::where('active', true)
                ->where(fn ($query) => $query->whereNull('tipo_pqr_id')->orWhere('tipo_pqr_id', $pqr->tipo_pqr_id))
                ->first()) {
                $pqr->update(['assigned_to_id' => $rule->assign_to_id, 'estado' => $rule->set_status]);
            }

            $this->guardarAdjuntos($pqr, $adjuntos);
            $this->actuaciones->registrar($pqr, $usuario, 'created', 'Radicó la solicitud.');

            return $pqr;
        });

        $this->notificaciones->ejecutar($contexto, $pqr, 'pqr_creada');

        return $pqr;
    }

    /** @param iterable<object> $adjuntos */
    private function guardarAdjuntos(Pqr $pqr, iterable $adjuntos): void
    {
        foreach ($adjuntos as $adjunto) {
            $path = $adjunto->store("pqrs/{$pqr->id}");
            $pqr->attachments()->create([
                'original_name' => $adjunto->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $adjunto->getMimeType() ?: 'application/octet-stream',
                'size' => $adjunto->getSize(),
            ]);
        }
    }

}
