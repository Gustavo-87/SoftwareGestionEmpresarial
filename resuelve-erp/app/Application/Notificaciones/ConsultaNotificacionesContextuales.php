<?php

namespace App\Application\Notificaciones;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class ConsultaNotificacionesContextuales
{
    public function __construct(private readonly AutorizacionContextual $autorizacion) {}

    public function para(ContextoOperativo $contexto, User $usuario): MorphMany
    {
        if ($contexto->usuario?->id !== $usuario->id || ! $this->autorizacion->tienePermiso($contexto, 'notificaciones.consultar')) {
            throw new AuthorizationException();
        }

        return $usuario->notifications()
            ->where('data->organizacion_id', $contexto->organizacion->id)
            ->where('data->copropiedad_id', $contexto->copropiedad->id);
    }

    public function paginar(ContextoOperativo $contexto, User $usuario, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->para($contexto, $usuario)
            ->latest()
            ->paginate($porPagina);
    }

    public function contarNoLeidas(ContextoOperativo $contexto, User $usuario): int
    {
        return $this->para($contexto, $usuario)
            ->whereNull('read_at')
            ->count();
    }

    public function resolver(ContextoOperativo $contexto, User $usuario, string $id): DatabaseNotification
    {
        return $this->para($contexto, $usuario)
            ->whereKey($id)
            ->firstOrFail();
    }
}
