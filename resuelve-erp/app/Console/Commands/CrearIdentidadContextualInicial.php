<?php

namespace App\Console\Commands;

use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CrearIdentidadContextualInicial extends Command
{
    protected $signature = 'resuelve:crear-identidad-contextual-inicial';

    protected $description = 'Crea el catálogo y las membresías contextuales compatibles con users.role';

    private const AMBITO = 'copropiedad';

    private const ROLES = [
        'admin' => ['nombre' => 'Administrador', 'descripcion' => 'Compatibilidad con users.role=admin.'],
        'gestor' => ['nombre' => 'Gestor', 'descripcion' => 'Compatibilidad con users.role=gestor.'],
        'apoyo' => ['nombre' => 'Apoyo', 'descripcion' => 'Compatibilidad con users.role=apoyo.'],
        'auditor' => ['nombre' => 'Auditor', 'descripcion' => 'Compatibilidad con users.role=auditor.'],
        'residente' => ['nombre' => 'Residente', 'descripcion' => 'Compatibilidad con users.role=residente.'],
    ];

    private const PERMISOS = [
        'pqrs.listar' => ['modulo' => 'pqrs', 'accion' => 'listar', 'descripcion' => 'Acceder al listado de PQRS.'],
        'pqrs.crear' => ['modulo' => 'pqrs', 'accion' => 'crear', 'descripcion' => 'Crear una PQRS.'],
        'pqrs.ver_propias' => ['modulo' => 'pqrs', 'accion' => 'ver_propias', 'descripcion' => 'Consultar las PQRS propias.'],
        'pqrs.ver_todas' => ['modulo' => 'pqrs', 'accion' => 'ver_todas', 'descripcion' => 'Consultar todas las PQRS.'],
        'pqrs.gestionar' => ['modulo' => 'pqrs', 'accion' => 'gestionar', 'descripcion' => 'Gestionar PQRS; apoyo conserva la condición de asignación actual.'],
        'pqrs.eliminar' => ['modulo' => 'pqrs', 'accion' => 'eliminar', 'descripcion' => 'Eliminar PQRS.'],
        'informes.exportar' => ['modulo' => 'informes', 'accion' => 'exportar', 'descripcion' => 'Exportar informes con la visibilidad de PQRS correspondiente al rol.'],
        'configuracion.gestionar' => ['modulo' => 'configuracion', 'accion' => 'gestionar', 'descripcion' => 'Consultar y actualizar la configuración.'],
        'gestion.carga_ver' => ['modulo' => 'gestion', 'accion' => 'carga_ver', 'descripcion' => 'Consultar la carga de trabajo.'],
        'gestion.herramientas_gestionar' => ['modulo' => 'gestion', 'accion' => 'herramientas_gestionar', 'descripcion' => 'Gestionar plantillas, etiquetas y reglas.'],
        'usuarios.gestionar' => ['modulo' => 'usuarios', 'accion' => 'gestionar', 'descripcion' => 'Crear, editar, eliminar y cambiar roles de Usuarios.'],
        'residentes.gestionar' => ['modulo' => 'residentes', 'accion' => 'gestionar', 'descripcion' => 'Consultar residentes y actualizar su unidad.'],
        'auditoria.ver' => ['modulo' => 'auditoria', 'accion' => 'ver', 'descripcion' => 'Consultar la auditoría.'],
        'notificaciones.consultar' => ['modulo' => 'notificaciones', 'accion' => 'consultar', 'descripcion' => 'Consultar Notificaciones autorizadas de la Copropiedad.'],
        'documentos.consultar' => ['modulo' => 'documentos', 'accion' => 'consultar', 'descripcion' => 'Consultar Documentos autorizados de la Copropiedad.'],
        'documentos.gestionar' => ['modulo' => 'documentos', 'accion' => 'gestionar', 'descripcion' => 'Crear y gestionar Documentos de la Copropiedad.'],
        'documentos.aprobar' => ['modulo' => 'documentos', 'accion' => 'aprobar', 'descripcion' => 'Aprobar o rechazar Versiones documentales.'],
        'documentos.archivar' => ['modulo' => 'documentos', 'accion' => 'archivar', 'descripcion' => 'Archivar Documentos de la Copropiedad.'],
    ];

    private const PERMISOS_POR_ROL = [
        'admin' => [
            'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas',
            'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar',
            'configuracion.gestionar', 'gestion.carga_ver',
            'gestion.herramientas_gestionar', 'usuarios.gestionar',
            'residentes.gestionar', 'auditoria.ver',
            'documentos.consultar', 'documentos.gestionar', 'documentos.aprobar', 'documentos.archivar', 'notificaciones.consultar',
        ],
        'gestor' => [
            'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas',
            'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar',
            'configuracion.gestionar', 'gestion.carga_ver',
            'gestion.herramientas_gestionar',
            'documentos.consultar', 'documentos.gestionar', 'notificaciones.consultar',
        ],
        'apoyo' => [
            'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas',
            'pqrs.gestionar', 'informes.exportar', 'notificaciones.consultar',
        ],
        'auditor' => [
            'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas',
            'informes.exportar', 'notificaciones.consultar',
        ],
        'residente' => [
            'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'informes.exportar',
            'documentos.consultar', 'notificaciones.consultar',
        ],
    ];

    public function handle(): int
    {
        try {
            $result = DB::transaction(function (): array {
                $contexto = $this->resolveContextoInicial();
                $conteos = $this->emptyCounts();
                $roles = $this->ensureRoles($conteos);
                $permisos = $this->ensurePermisos($conteos);

                $this->ensureRolePermissions($roles, $permisos, $conteos);
                $this->ensureUserMemberships($contexto, $roles, $conteos);

                return ['contexto' => $contexto, 'conteos' => $conteos];
            }, 3);
        } catch (Throwable $exception) {
            $this->error('No fue posible crear la identidad contextual inicial; se revirtieron los cambios.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Identidad contextual inicial procesada correctamente.');
        $this->line("Organización: {$result['contexto']['organizacion']->id}");
        $this->line("Copropiedad: {$result['contexto']['copropiedad']->id}");

        foreach ($result['conteos'] as $nombre => $valor) {
            $this->line(str_replace('_', ' ', ucfirst($nombre)).": {$valor}");
        }

        return self::SUCCESS;
    }

    private function resolveContextoInicial(): array
    {
        $current = SiteSetting::current();

        if (! $current->exists) {
            throw new RuntimeException(
                'No existe una fila persistida de site_settings. Ejecute resuelve:crear-contexto-inicial.'
            );
        }

        $siteSetting = SiteSetting::query()->whereKey($current->getKey())->lockForUpdate()->firstOrFail();

        if ($siteSetting->organizacion_id === null || $siteSetting->copropiedad_id === null) {
            throw new RuntimeException(
                'SiteSetting no está contextualizado. Ejecute resuelve:crear-contexto-inicial.'
            );
        }

        $organizacion = Organizacion::query()->lockForUpdate()->find($siteSetting->organizacion_id);
        $copropiedad = $siteSetting->copropiedad()->lockForUpdate()->first();

        if (! $organizacion || ! $copropiedad) {
            throw new RuntimeException('El contexto inicial referencia entidades inexistentes.');
        }

        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new RuntimeException('La Copropiedad inicial no pertenece a la Organización inicial.');
        }

        return compact('siteSetting', 'organizacion', 'copropiedad');
    }

    private function emptyCounts(): array
    {
        return [
            'roles_creados' => 0,
            'roles_existentes' => 0,
            'permisos_creados' => 0,
            'permisos_existentes' => 0,
            'relaciones_creadas' => 0,
            'relaciones_existentes' => 0,
            'membresias_creadas' => 0,
            'membresias_existentes' => 0,
            'asignaciones_creadas' => 0,
            'asignaciones_existentes' => 0,
            'usuarios_omitidos' => 0,
            'inconsistencias' => 0,
        ];
    }

    private function ensureRoles(array &$conteos): array
    {
        $roles = [];

        foreach (self::ROLES as $clave => $definition) {
            $rol = Rol::query()->where('clave', $clave)->lockForUpdate()->first();

            if ($rol && $rol->ambito_aplicable !== self::AMBITO) {
                throw new RuntimeException("El Rol {$clave} existe con un ámbito incompatible.");
            }

            if ($rol) {
                $conteos['roles_existentes']++;
                $rol->update([...$definition, 'estado' => 'activo']);
            } else {
                $conteos['roles_creados']++;
                $rol = Rol::create([
                    'clave' => $clave,
                    ...$definition,
                    'ambito_aplicable' => self::AMBITO,
                    'estado' => 'activo',
                ]);
            }

            $roles[$clave] = $rol;
        }

        return $roles;
    }

    private function ensurePermisos(array &$conteos): array
    {
        $permisos = [];

        foreach (self::PERMISOS as $clave => $definition) {
            $permiso = Permiso::query()->where('clave', $clave)->lockForUpdate()->first();

            if ($permiso && $permiso->ambito_aplicable !== self::AMBITO) {
                throw new RuntimeException("El Permiso {$clave} existe con un ámbito incompatible.");
            }

            if ($permiso) {
                $conteos['permisos_existentes']++;
                $permiso->update([...$definition, 'estado' => 'activo']);
            } else {
                $conteos['permisos_creados']++;
                $permiso = Permiso::create([
                    'clave' => $clave,
                    ...$definition,
                    'ambito_aplicable' => self::AMBITO,
                    'estado' => 'activo',
                ]);
            }

            $permisos[$clave] = $permiso;
        }

        return $permisos;
    }

    private function ensureRolePermissions(array $roles, array $permisos, array &$conteos): void
    {
        foreach (self::PERMISOS_POR_ROL as $rolClave => $permissionKeys) {
            foreach ($permissionKeys as $permisoClave) {
                $exists = DB::table('rol_permiso')
                    ->where('rol_id', $roles[$rolClave]->id)
                    ->where('permiso_id', $permisos[$permisoClave]->id)
                    ->exists();

                if ($exists) {
                    $conteos['relaciones_existentes']++;

                    continue;
                }

                DB::table('rol_permiso')->insert([
                    'rol_id' => $roles[$rolClave]->id,
                    'permiso_id' => $permisos[$permisoClave]->id,
                    'ambito_aplicable' => self::AMBITO,
                ]);
                $conteos['relaciones_creadas']++;
            }
        }
    }

    private function ensureUserMemberships(array $contexto, array $roles, array &$conteos): void
    {
        User::query()->orderBy('id')->lockForUpdate()->get()->each(function (User $usuario) use (
            $contexto,
            $roles,
            &$conteos
        ): void {
            $legacyRole = (string) $usuario->role;

            if (! array_key_exists($legacyRole, self::ROLES)) {
                $conteos['usuarios_omitidos']++;
                $conteos['inconsistencias']++;
                $this->warn("Usuario {$usuario->id} ({$usuario->email}) omitido: rol desconocido '{$legacyRole}'.");

                return;
            }

            $membresia = MembresiaCopropiedad::query()
                ->where('usuario_id', $usuario->id)
                ->where('copropiedad_id', $contexto['copropiedad']->id)
                ->lockForUpdate()
                ->first();

            if ($membresia && $membresia->organizacion_id !== $contexto['organizacion']->id) {
                throw new RuntimeException(
                    "La Membresía de Copropiedad del Usuario {$usuario->id} tiene un contexto inconsistente."
                );
            }

            if ($membresia) {
                $conteos['membresias_existentes']++;
            } else {
                $conteos['membresias_creadas']++;
                $membresia = MembresiaCopropiedad::create([
                    'usuario_id' => $usuario->id,
                    'organizacion_id' => $contexto['organizacion']->id,
                    'copropiedad_id' => $contexto['copropiedad']->id,
                    'estado' => 'activa',
                    'vigente_desde' => now(),
                ]);
            }

            $rol = $roles[$legacyRole];
            $activeAssignments = DB::table('membresia_copropiedad_rol')
                ->where('membresia_copropiedad_id', $membresia->id)
                ->where('estado', 'activa')
                ->whereNull('vigente_hasta')
                ->lockForUpdate()
                ->get();

            if ($activeAssignments->contains(
                fn (object $assignment) => (int) $assignment->rol_id !== $rol->id
            )) {
                throw new RuntimeException(
                    "El Usuario {$usuario->id} tiene una asignación activa diferente de users.role."
                );
            }

            if ($activeAssignments->where('rol_id', $rol->id)->count() > 1) {
                throw new RuntimeException(
                    "El Usuario {$usuario->id} tiene asignaciones activas duplicadas para el mismo Rol."
                );
            }

            if ($activeAssignments->where('rol_id', $rol->id)->isNotEmpty()) {
                $conteos['asignaciones_existentes']++;

                return;
            }

            DB::table('membresia_copropiedad_rol')->insert([
                'membresia_copropiedad_id' => $membresia->id,
                'rol_id' => $rol->id,
                'organizacion_id' => $contexto['organizacion']->id,
                'copropiedad_id' => $contexto['copropiedad']->id,
                'ambito_rol' => self::AMBITO,
                'estado' => 'activa',
                'vigente_desde' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $conteos['asignaciones_creadas']++;
        });
    }
}
