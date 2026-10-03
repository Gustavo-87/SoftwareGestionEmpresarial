<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

final class CrearRol
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    /**
     * Crea un Rol global (autoridad de plataforma) o personalizado de la
     * Copropiedad activa (autoridad contextual). Opcionalmente copia la matriz
     * de permisos de un Rol existente visible para el operador.
     */
    public function ejecutar(User $operador, ?ContextoOperativo $contexto, array $datos): SpatieRole
    {
        $this->autorizacion->autorizarGestion($operador, $contexto);

        $datos = Validator::make($datos, [
            'nombre' => 'required|string|max:100',
            'permisos' => 'nullable|array',
            'permisos.*' => 'string',
            'copiar_desde_rol_id' => 'nullable|integer',
        ])->validate();

        $nombre = trim($datos['nombre']);
        $equipoId = $this->autorizacion->esAutoridadPlataforma($operador)
            ? null
            : (int) $contexto->copropiedad->id;

        $duplicado = SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->where('name', $nombre)
            ->where(fn ($query) => $equipoId === null
                ? $query->whereNull('copropiedad_id')
                : $query->where('copropiedad_id', $equipoId))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un Rol con ese nombre en este alcance.']);
        }

        $claves = $this->resolverPermisos($operador, $contexto, $datos);

        setPermissionsTeamId($equipoId);
        $rol = SpatieRole::create(['name' => $nombre, 'guard_name' => AutorizacionGestionRoles::GUARD]);
        if ($claves !== []) {
            $rol->syncPermissions($claves);
        }

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'rol.store',
            'auditable_type' => SpatieRole::class,
            'auditable_id' => $rol->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'nombre' => $nombre,
                'alcance' => $equipoId === null ? 'global' : 'copropiedad',
                'copropiedad_id' => $equipoId,
                'permisos' => $claves,
            ],
        ]);

        return $rol;
    }

    /** @return list<string> */
    private function resolverPermisos(User $operador, ?ContextoOperativo $contexto, array $datos): array
    {
        $claves = $datos['permisos'] ?? [];

        if (isset($datos['copiar_desde_rol_id'])) {
            $origen = SpatieRole::query()
                ->where('guard_name', AutorizacionGestionRoles::GUARD)
                ->where('id', $datos['copiar_desde_rol_id'])
                ->first();

            if ($origen === null) {
                throw ValidationException::withMessages(['copiar_desde_rol_id' => 'El Rol de origen no existe.']);
            }
            if (! $this->autorizacion->esAutoridadPlataforma($operador)
                && $origen->copropiedad_id !== null
                && (int) $origen->copropiedad_id !== (int) $contexto->copropiedad->id) {
                throw new AuthorizationException('El Rol de origen pertenece a otro contexto.');
            }

            $claves = array_merge($claves, $origen->permissions()->pluck('name')->all());
        }

        $claves = array_values(array_unique($claves));
        $conocidas = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->whereIn('name', $claves)
            ->pluck('name')
            ->all();
        $desconocidas = array_values(array_diff($claves, $conocidas));
        if ($desconocidas !== []) {
            throw ValidationException::withMessages(['permisos' => 'Permisos desconocidos: '.implode(', ', $desconocidas).'.']);
        }

        $this->autorizacion->autorizarPermisosOtorgables($operador, $contexto, $claves);

        return $claves;
    }
}
