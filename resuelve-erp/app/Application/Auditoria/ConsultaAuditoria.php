<?php

namespace App\Application\Auditoria;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConsultaAuditoria
{
    public function ejecutar(User $actor, ?ContextoOperativo $contexto, Request $request, bool $plataforma = false): array
    {
        if ($plataforma) {
            if (! $actor->esAdministradorSistema()) {
                throw new AuthorizationException;
            }
        } elseif (! $contexto || ! app(AutorizacionContextual::class)->tienePermiso($contexto, 'auditoria.ver')) {
            throw new AuthorizationException;
        }

        $filtros = $request->validate([
            'registro' => ['nullable', Rule::in(['operacion', 'modelos', 'seguridad'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'actor' => ['nullable', 'integer', 'min:1'],
            'canal' => ['nullable', Rule::in(['web', 'api'])],
        ]);
        $registro = $filtros['registro'] ?? 'operacion';
        $modelos = $registro === 'modelos';
        $query = $modelos ? Activity::query()->where('log_name', 'modelos') : AuditLog::query();
        if (! $modelos) {
            $query->where('action', $registro === 'seguridad' ? 'like' : 'not like', 'auth.%');
        }
        if (! $plataforma) {
            $query->where('organizacion_id', $contexto->organizacion->id)
                ->where('copropiedad_id', $contexto->copropiedad->id);
        }
        $columnaActor = $modelos ? 'causer_id' : 'user_id';
        $actores = User::query()->whereIn('id', (clone $query)->select($columnaActor)->whereNotNull($columnaActor)->distinct())
            ->orderBy('name')->get(['id', 'name']);
        $query->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filtros['actor'] ?? null, fn (Builder $q, $v) => $q->where($modelos ? 'causer_id' : 'user_id', $v));
        if ($registro === 'seguridad' && ! empty($filtros['canal'])) {
            $query->where('metadata->canal', $filtros['canal']);
        }

        return [
            'logs' => $query->with('user')->latest('id')->paginate(30)->withQueryString(),
            'registro' => $registro,
            'filtros' => $filtros,
            'plataforma' => $plataforma,
            'ambito' => $plataforma ? 'Plataforma' : $contexto->copropiedad->nombre,
            'actores' => $actores,
        ];
    }
}
