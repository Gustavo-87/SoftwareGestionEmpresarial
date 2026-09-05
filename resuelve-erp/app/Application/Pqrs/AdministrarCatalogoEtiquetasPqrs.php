<?php

namespace App\Application\Pqrs;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdministrarCatalogoEtiquetasPqrs
{
    public static function normalizarNombre(string $nombre): string
    {
        return preg_replace('/\s+/u', ' ', trim($nombre)) ?? '';
    }

    public static function normalizarColor(string $color): string
    {
        return strtoupper(trim($color));
    }

    public function crear(ContextoOperativo $contexto, User $actor, array $datos): PqrTag
    {
        return DB::transaction(function () use ($contexto, $actor, $datos): PqrTag {
            try {
                $tag = new PqrTag(['name' => $datos['name'], 'color' => $datos['color']]);
                $tag->organizacion()->associate($contexto->organizacion);
                $tag->copropiedad()->associate($contexto->copropiedad);
                $tag->save();
            } catch (QueryException $e) {
                $this->conflictoNombre($e);
            }
            $this->auditar($contexto, $actor, $tag, 'etiqueta.creada', null, ['name' => $tag->name, 'color' => $tag->color, 'activo' => true]);
            return $tag;
        });
    }

    public function actualizar(ContextoOperativo $contexto, User $actor, PqrTag $tag, array $datos): bool
    {
        return DB::transaction(function () use ($contexto, $actor, $tag, $datos): bool {
            $antes = $tag->only(['name', 'color']);
            $tag->fill($datos);
            if (! $tag->isDirty(['name', 'color'])) { request()?->attributes->set('auditoria_especifica_registrada', true); return false; }
            try { $tag->save(); } catch (QueryException $e) { $this->conflictoNombre($e); }
            $eventos = [];
            if ($antes['name'] !== $tag->name) $eventos[] = 'nombre';
            if ($antes['color'] !== $tag->color) $eventos[] = 'color';
            $this->auditar($contexto, $actor, $tag, 'etiqueta.actualizada', $antes, $tag->only(['name', 'color']), ['campos' => $eventos]);
            return true;
        });
    }

    public function cambiarEstado(ContextoOperativo $contexto, User $actor, PqrTag $tag, bool $activo): bool
    {
        if ($tag->activo === $activo) { request()?->attributes->set('auditoria_especifica_registrada', true); return false; }
        return DB::transaction(function () use ($contexto, $actor, $tag, $activo): bool {
            $anterior = $tag->activo;
            $tag->forceFill(['activo' => $activo])->save();
            $this->auditar($contexto, $actor, $tag, $activo ? 'etiqueta.reactivada' : 'etiqueta.desactivada', ['activo' => $anterior], ['activo' => $activo]);
            return true;
        });
    }

    private function auditar(ContextoOperativo $contexto, User $actor, PqrTag $tag, string $accion, ?array $antes, array $despues, array $extra = []): void
    {
        AuditLog::create(['user_id' => $actor->id, 'action' => $accion, 'auditable_type' => PqrTag::class, 'auditable_id' => $tag->id, 'ip_address' => request()?->ip(), 'metadata' => $extra + ['organizacion_id' => $contexto->organizacion->id, 'copropiedad_id' => $contexto->copropiedad->id, 'antes' => $antes, 'despues' => $despues]]);
        request()?->attributes->set('auditoria_especifica_registrada', true);
    }

    private function conflictoNombre(QueryException $e): never
    {
        if (in_array((string) $e->getCode(), ['23000', '23505'], true)) throw ValidationException::withMessages(['name' => 'Ya existe una etiqueta con este nombre en la Copropiedad. Reactívala si está desactivada.']);
        throw $e;
    }
}
