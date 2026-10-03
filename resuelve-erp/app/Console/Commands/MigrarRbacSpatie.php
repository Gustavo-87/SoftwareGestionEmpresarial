<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MigrarRbacSpatie extends Command
{
    protected $signature = 'resuelve:migrar-rbac-spatie';

    protected $description = 'Migra de forma idempotente el RBAC contextual legado a Spatie: permisos, roles, matriz y asignaciones activas por Copropiedad.';

    private const GUARD = 'web';

    private const PERMISOS_NUEVOS = ['pqrs.gestionar_asignadas', 'pqrs.ver_borradores', 'roles.gestionar'];

    private const PERMISOS_NUEVOS_POR_ROL = [
        'apoyo' => ['pqrs.gestionar_asignadas'],
        'admin' => ['pqrs.ver_borradores', 'roles.gestionar'],
        'gestor' => ['pqrs.ver_borradores'],
    ];

    public function handle(): int
    {
        $conteos = [
            'permisos_creados' => 0, 'permisos_existentes' => 0,
            'roles_creados' => 0, 'roles_existentes' => 0,
            'matrices_sincronizadas' => 0,
            'asignaciones_creadas' => 0, 'asignaciones_existentes' => 0, 'asignaciones_omitidas' => 0,
        ];

        try {
            DB::transaction(function () use (&$conteos): void {
                $this->migrarPermisos($conteos);
                $this->migrarRolesYMatriz($conteos);
                $this->migrarAsignaciones($conteos);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Permisos creados: {$conteos['permisos_creados']}. Existentes: {$conteos['permisos_existentes']}.");
        $this->info("Roles creados: {$conteos['roles_creados']}. Existentes: {$conteos['roles_existentes']}.");
        $this->info("Matrices rol-permiso sincronizadas: {$conteos['matrices_sincronizadas']}.");
        $this->info("Asignaciones creadas: {$conteos['asignaciones_creadas']}. Existentes: {$conteos['asignaciones_existentes']}. Omitidas por inactivas o vencidas: {$conteos['asignaciones_omitidas']}.");
        $this->info('Migración RBAC a Spatie completada. Idempotente: puede ejecutarse nuevamente sin duplicar información.');

        return self::SUCCESS;
    }

    /** @param array<string,int> $conteos */
    private function migrarPermisos(array &$conteos): void
    {
        // El catálogo de Spatie es global (equipo nulo); las asignaciones son por Copropiedad.
        setPermissionsTeamId(null);

        $clavesLegado = DB::table('permisos_contextuales')->orderBy('id')->pluck('clave')->all();
        if ($clavesLegado === []) {
            throw new RuntimeException(
                'No existen permisos en permisos_contextuales. Ejecute primero resuelve:crear-identidad-contextual-inicial.'
            );
        }

        foreach (array_merge($clavesLegado, self::PERMISOS_NUEVOS) as $clave) {
            $existe = Permission::query()->where('name', $clave)->where('guard_name', self::GUARD)->exists();
            Permission::findOrCreate($clave, self::GUARD);
            $conteos[$existe ? 'permisos_existentes' : 'permisos_creados']++;
        }
    }

    /** @param array<string,int> $conteos */
    private function migrarRolesYMatriz(array &$conteos): void
    {
        setPermissionsTeamId(null);

        $rolesLegado = DB::table('roles_contextuales')
            ->where('ambito_aplicable', 'copropiedad')
            ->where('estado', 'activo')
            ->orderBy('clave')
            ->get();

        foreach ($rolesLegado as $rolLegado) {
            $existe = Role::query()->where('name', $rolLegado->clave)->where('guard_name', self::GUARD)->exists();
            $rol = Role::findOrCreate($rolLegado->clave, self::GUARD);
            $conteos[$existe ? 'roles_existentes' : 'roles_creados']++;

            $clavesLegado = DB::table('rol_permiso_contextual')
                ->join('permisos_contextuales', 'permisos_contextuales.id', '=', 'rol_permiso_contextual.permiso_id')
                ->where('rol_permiso_contextual.rol_id', $rolLegado->id)
                ->pluck('permisos_contextuales.clave')
                ->all();

            $claves = array_values(array_unique(array_merge(
                $clavesLegado,
                self::PERMISOS_NUEVOS_POR_ROL[$rolLegado->clave] ?? []
            )));
            sort($claves);

            $actuales = $rol->permissions()->pluck('name')->all();
            sort($actuales);
            if ($actuales !== $claves) {
                $rol->syncPermissions($claves);
            }
            $conteos['matrices_sincronizadas']++;
        }
    }

    /** @param array<string,int> $conteos */
    private function migrarAsignaciones(array &$conteos): void
    {
        $todas = DB::table('membresia_copropiedad_rol')->count();

        $activas = DB::table('membresia_copropiedad_rol')
            ->join('membresias_copropiedad', 'membresias_copropiedad.id', '=', 'membresia_copropiedad_rol.membresia_copropiedad_id')
            ->join('roles_contextuales', 'roles_contextuales.id', '=', 'membresia_copropiedad_rol.rol_id')
            ->where('membresia_copropiedad_rol.ambito_rol', 'copropiedad')
            ->where('membresia_copropiedad_rol.estado', 'activa')
            ->where('membresia_copropiedad_rol.vigente_desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('membresia_copropiedad_rol.vigente_hasta')
                ->orWhere('membresia_copropiedad_rol.vigente_hasta', '>', now()))
            ->where('membresias_copropiedad.estado', 'activa')
            ->where('membresias_copropiedad.vigente_desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('membresias_copropiedad.vigente_hasta')
                ->orWhere('membresias_copropiedad.vigente_hasta', '>', now()))
            ->where('roles_contextuales.ambito_aplicable', 'copropiedad')
            ->get([
                'membresias_copropiedad.usuario_id',
                'membresia_copropiedad_rol.copropiedad_id',
                'roles_contextuales.clave',
            ]);

        foreach ($activas->unique(fn (object $fila) => "{$fila->usuario_id}|{$fila->copropiedad_id}|{$fila->clave}") as $asignacion) {
            setPermissionsTeamId((int) $asignacion->copropiedad_id);
            $usuario = \App\Models\User::query()->find($asignacion->usuario_id);
            if ($usuario === null) {
                continue;
            }
            $usuario->unsetRelation('roles')->unsetRelation('permissions');

            if ($usuario->roles()->where('name', $asignacion->clave)->exists()) {
                $conteos['asignaciones_existentes']++;

                continue;
            }

            $usuario->assignRole($asignacion->clave);
            $conteos['asignaciones_creadas']++;
        }

        $conteos['asignaciones_omitidas'] = $todas - $activas->count();
    }
}
