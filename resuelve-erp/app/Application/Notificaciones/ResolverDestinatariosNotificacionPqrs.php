<?php

namespace App\Application\Notificaciones;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Support\Collection;

final class ResolverDestinatariosNotificacionPqrs
{
    public function __construct(private readonly ContextResolver $contextos, private readonly AutorizacionContextual $autorizacion) {}

    /** @return Collection<int, User> */
    public function resolver(ContextoOperativo $contexto, Pqr $pqr, string $event): Collection
    {
        $candidatos = match ($event) {
            'pqr_creada' => User::query()->whereHas('membresiasCopropiedad', fn ($q) => $q->where('organizacion_id', $contexto->organizacion->id)->where('copropiedad_id', $contexto->copropiedad->id))->get(),
            'pqr_asignada' => collect([$pqr->assignee]),
            'pqr_estado_actualizado', 'pqr_respuesta_enviada' => collect([$pqr->user]),
            'pqr_recordatorio_vencimiento' => collect([$pqr->user, $pqr->assignee]),
            default => collect(),
        };

        return $candidatos->filter()->filter(function (User $usuario) use ($contexto, $event): bool {
            $destino = $this->contextos->resolverExplicito($contexto->organizacion->id, $contexto->copropiedad->id, $usuario->id);
            return $this->autorizacion->tienePermiso($destino, 'notificaciones.consultar')
                && ($event !== 'pqr_creada' || $this->autorizacion->tienePermiso($destino, 'pqrs.gestionar'));
        })->unique('id')->values();
    }
}
