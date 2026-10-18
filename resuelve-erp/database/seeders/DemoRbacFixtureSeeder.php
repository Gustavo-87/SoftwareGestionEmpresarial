<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Fixture de demostración RBAC — HU-USR-09 (Sprint 18).
 *
 * Crea un usuario de demostración con 2 roles y 1 permiso directo por equipo
 * (Copropiedad) para la evidencia académica de multi-rol y permisos directos:
 * - roles: `gestor` y `auditor` (model_has_roles con `copropiedad_id`);
 * - permiso directo: `auditoria.ver` (model_has_permissions con
 *   `copropiedad_id`), elegido para que su origen sea "directo": no pertenece
 *   a la matriz de ninguno de los dos roles.
 *
 * OPT-IN: se ejecuta únicamente de forma explícita
 * (`php artisan db:seed --class=DemoRbacFixtureSeeder`). No se registra en
 * DatabaseSeeder ni se ejecuta en pruebas automáticas, y no debe ejecutarse
 * contra la BD local de desarrollo. No altera usuarios ni asignaciones
 * existentes: solo gestiona su propio usuario de demostración.
 */
class DemoRbacFixtureSeeder extends Seeder
{
    public const EMAIL_DEMO = 'demo.rbac@example.test';

    /** @var list<string> */
    public const ROLES_DEMO = ['gestor', 'auditor'];

    public const PERMISO_DIRECTO_DEMO = 'auditoria.ver';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'El fixture de demostración RBAC no puede crearse en el entorno production.'
            );
        }

        $demoPassword = config('resuelve.demo_password');
        if (! is_string($demoPassword) || strlen($demoPassword) < 12) {
            throw new RuntimeException(
                'Define RESUELVE_DEMO_PASSWORD con al menos 12 caracteres antes de ejecutar el fixture.'
            );
        }

        $siteSetting = SiteSetting::current();
        if (! $siteSetting->exists
            || $siteSetting->organizacion_id === null
            || $siteSetting->copropiedad_id === null) {
            throw new RuntimeException(
                'El fixture requiere un contexto inicial contextualizado. Ejecute resuelve:crear-contexto-inicial.'
            );
        }

        $copropiedadId = (int) $siteSetting->copropiedad_id;

        // El catálogo de Spatie es global (equipo nulo); las asignaciones son
        // por Copropiedad (model_has_roles / model_has_permissions con equipo).
        setPermissionsTeamId(null);
        foreach (self::ROLES_DEMO as $clave) {
            if (! Role::query()->where('name', $clave)->where('guard_name', 'web')->exists()) {
                throw new RuntimeException(
                    "Falta el Rol {$clave} del catálogo Spatie. Ejecute resuelve:migrar-rbac-spatie."
                );
            }
        }
        if (! Permission::query()->where('name', self::PERMISO_DIRECTO_DEMO)->where('guard_name', 'web')->exists()) {
            throw new RuntimeException(
                'Falta el Permiso '.self::PERMISO_DIRECTO_DEMO.' del catálogo Spatie. Ejecute resuelve:migrar-rbac-spatie.'
            );
        }

        DB::transaction(function () use ($demoPassword, $copropiedadId): void {
            $usuario = User::updateOrCreate(
                ['email' => self::EMAIL_DEMO],
                [
                    'name' => 'Demo RBAC multi-rol',
                    'role' => 'gestor',
                    'email_verified_at' => now(),
                    'password' => $demoPassword,
                ]
            );

            setPermissionsTeamId($copropiedadId);
            $usuario->unsetRelation('roles')->unsetRelation('permissions');
            $usuario->syncRoles(self::ROLES_DEMO);
            $usuario->givePermissionTo(self::PERMISO_DIRECTO_DEMO);
        });

        $this->command?->info(
            'Fixture RBAC de demostración aplicado: '.self::EMAIL_DEMO
            .' (2 roles + 1 permiso directo en la Copropiedad '.$copropiedadId.').'
        );
    }
}
