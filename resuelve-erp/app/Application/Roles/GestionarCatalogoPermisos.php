<?php

namespace App\Application\Roles;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Administración del catálogo de permisos (HU-USR-07, Sprint 18).
 *
 * Solo autoridad de plataforma. Alta con clave `modulo.accion`, nombre,
 * descripción y módulo; el Rol base `admin` hereda los permisos nuevos
 * (invariante del catálogo base). La eliminación es lógica y auditada
 * (decisión del Product Owner, 20 de octubre de 2026):
 *
 * 1. Los permisos base necesarios para las funcionalidades del sistema quedan
 *    protegidos contra eliminación.
 * 2. Un permiso personalizado no puede desactivarse mientras esté asignado a
 *    otros Roles o directamente a usuarios; el mensaje identifica su uso.
 * 3. La asignación automática al Rol base `admin` no cuenta como uso.
 * 4. Si únicamente conserva esa asignación automática, se desactiva y se
 *    retira en la misma transacción.
 * 5. El registro y su auditoría se conservan: nunca se borra la fila.
 * 6. Un permiso inactivo no concede acceso ni admite nuevas asignaciones.
 */
final class GestionarCatalogoPermisos
{
    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_INACTIVO = 'inactivo';

    public const FORMATO_CLAVE = '/^[a-z0-9_]+\.[a-z0-9_]+$/';

    public function __construct(private readonly AutorizacionGestionRoles $autorizacion) {}

    public function crear(User $operador, string $clave, string $nombre, ?string $descripcion, string $modulo): Permission
    {
        $this->autorizarPlataforma($operador);

        return DB::transaction(function () use ($operador, $clave, $nombre, $descripcion, $modulo): Permission {
            $datos = validator(
                ['clave' => $clave, 'nombre' => $nombre, 'descripcion' => $descripcion, 'modulo' => $modulo],
                [
                    'clave' => ['required', 'string', 'max:150', 'regex:'.self::FORMATO_CLAVE],
                    'nombre' => ['required', 'string', 'max:150'],
                    'descripcion' => ['nullable', 'string', 'max:500'],
                    'modulo' => ['required', 'string', 'max:100'],
                ]
            )->validate();

            $claveNormalizada = CatalogoPermisosBase::normalizar($datos['clave']);
            if ($claveNormalizada !== trim($datos['clave'])) {
                throw ValidationException::withMessages([
                    'clave' => 'La clave debe estar en minúsculas y sin espacios (formato modulo.accion).',
                ]);
            }
            if ($datos['modulo'] !== Str::before($claveNormalizada, '.')) {
                throw ValidationException::withMessages([
                    'modulo' => 'El módulo debe coincidir con el prefijo de la clave (modulo.accion).',
                ]);
            }
            if (Permission::query()
                ->where('guard_name', AutorizacionGestionRoles::GUARD)
                ->where('name', $claveNormalizada)
                ->exists()) {
                throw ValidationException::withMessages([
                    'clave' => 'Ya existe un permiso con esa clave en el catálogo.',
                ]);
            }

            $permiso = new Permission(['name' => $claveNormalizada, 'guard_name' => AutorizacionGestionRoles::GUARD]);
            $permiso->nombre = $datos['nombre'];
            $permiso->modulo = $datos['modulo'];
            $permiso->descripcion = $datos['descripcion'] ?? null;
            $permiso->estado = self::ESTADO_ACTIVO;
            $permiso->save();

            $this->heredarEnAdminBase($permiso);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'permiso.crear',
                'auditable_type' => Permission::class,
                'auditable_id' => $permiso->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'clave' => $permiso->name,
                    'nombre' => $permiso->nombre,
                    'modulo' => $permiso->modulo,
                ],
            ]);

            return $permiso;
        });
    }

    public function desactivar(User $operador, Permission $permiso): void
    {
        $this->autorizarPlataforma($operador);

        if (CatalogoPermisosBase::esBase($permiso)) {
            throw ValidationException::withMessages([
                'permiso' => 'Los permisos base del catálogo inicial están protegidos y no pueden eliminarse.',
            ]);
        }

        DB::transaction(function () use ($operador, $permiso): void {
            $rolesNoAutomaticos = $permiso->roles()
                ->get()
                ->reject(fn (SpatieRole $rol) => CatalogoRolesBase::esAdminBase($rol));
            $usuariosDirectos = $this->usuariosConPermiso($permiso);

            if ($rolesNoAutomaticos->isNotEmpty() || $usuariosDirectos->isNotEmpty()) {
                $detalle = [];
                if ($rolesNoAutomaticos->isNotEmpty()) {
                    $detalle[] = 'roles: '.$rolesNoAutomaticos->pluck('name')->implode(', ');
                }
                if ($usuariosDirectos->isNotEmpty()) {
                    $detalle[] = 'usuarios: '.$usuariosDirectos->pluck('email')->implode(', ');
                }
                throw ValidationException::withMessages([
                    'permiso' => 'El permiso está en uso y no puede eliminarse ('.implode('; ', $detalle).').',
                ]);
            }

            // Regla 4: si solo conserva la asignación automática al Rol base
            // admin, se retira en la misma transacción de la desactivación.
            foreach ($permiso->roles()->get() as $rol) {
                $rol->revokePermissionTo($permiso);
            }

            $permiso->forceFill(['estado' => self::ESTADO_INACTIVO])->save();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'permiso.desactivar',
                'auditable_type' => Permission::class,
                'auditable_id' => $permiso->id,
                'ip_address' => request()->ip(),
                'metadata' => ['clave' => $permiso->name],
            ]);
        });
    }

    public function reactivar(User $operador, Permission $permiso): void
    {
        $this->autorizarPlataforma($operador);

        DB::transaction(function () use ($operador, $permiso): void {
            $permiso->forceFill(['estado' => self::ESTADO_ACTIVO])->save();
            $this->heredarEnAdminBase($permiso);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            AuditLog::create([
                'user_id' => $operador->id,
                'action' => 'permiso.activar',
                'auditable_type' => Permission::class,
                'auditable_id' => $permiso->id,
                'ip_address' => request()->ip(),
                'metadata' => ['clave' => $permiso->name],
            ]);
        });
    }

    /** Uso visible del permiso: Roles no automáticos, usuarios directos y herencia admin base. */
    public function usoDe(Permission $permiso): array
    {
        $roles = $permiso->roles()->get();
        $usuarios = $this->usuariosConPermiso($permiso);

        return [
            'roles' => $roles->reject(fn (SpatieRole $rol) => CatalogoRolesBase::esAdminBase($rol))->pluck('name')->values()->all(),
            'usuarios' => $usuarios->pluck('email')->values()->all(),
            'heredado_admin' => $roles->contains(fn (SpatieRole $rol) => CatalogoRolesBase::esAdminBase($rol)),
        ];
    }

    /**
     * Usuarios con el permiso asignado directamente en cualquier equipo
     * (Copropiedad). La consulta no depende del equipo activo para no ocultar
     * uso en otros contextos.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function usuariosConPermiso(Permission $permiso)
    {
        return User::query()
            ->whereIn('id', DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('permission_id', $permiso->id)
                ->pluck('model_id'))
            ->get();
    }

    /** Invariante del catálogo base: el Rol admin base hereda los permisos. */
    private function heredarEnAdminBase(Permission $permiso): void
    {
        foreach ($this->rolesAdminBase() as $rol) {
            $rol->givePermissionTo($permiso);
        }
    }

    /** @return list<SpatieRole> */
    private function rolesAdminBase(): array
    {
        return SpatieRole::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->get()
            ->filter(fn (SpatieRole $rol) => CatalogoRolesBase::esAdminBase($rol))
            ->values()
            ->all();
    }

    private function autorizarPlataforma(User $operador): void
    {
        if (! $this->autorizacion->esAutoridadPlataforma($operador)) {
            throw new AuthorizationException('Solo la autoridad de plataforma puede administrar el catálogo de permisos.');
        }
    }
}
