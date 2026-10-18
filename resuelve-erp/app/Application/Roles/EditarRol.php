<?php

namespace App\Application\Roles;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

final class EditarRol
{
    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    public function ejecutar(User $operador, ?ContextoOperativo $contexto, int $rolId, array $datos): SpatieRole
    {
        $rol = SpatieRole::query()->findOrFail($rolId);
        $this->autorizacion->autorizarGestion($operador, $contexto, $rol);

        // HU-USR-09: el Rol base admin es inmutable (no renombrable vía web),
        // para cualquier autoridad. Comparación con normalización canónica
        // consistente con la colación *_ci.
        if (CatalogoRolesBase::esAdminBase($rol)) {
            throw ValidationException::withMessages([
                'nombre' => 'El Rol admin base del catálogo inicial es inmutable: no puede renombrarse.',
            ]);
        }

        $datos = Validator::make($datos, ['nombre' => 'required|string|max:100'])->validate();
        $nombre = trim($datos['nombre']);

        $duplicado = SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->where('name', $nombre)
            ->where('id', '!=', $rol->id)
            ->where(fn ($query) => $rol->copropiedad_id === null
                ? $query->whereNull('copropiedad_id')
                : $query->where('copropiedad_id', $rol->copropiedad_id))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un Rol con ese nombre en este alcance.']);
        }

        $anterior = $rol->name;
        $rol->update(['name' => $nombre]);

        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'rol.update',
            'auditable_type' => SpatieRole::class,
            'auditable_id' => $rol->id,
            'ip_address' => request()->ip(),
            'metadata' => ['nombre_anterior' => $anterior, 'nombre' => $nombre],
        ]);

        return $rol->refresh();
    }
}
