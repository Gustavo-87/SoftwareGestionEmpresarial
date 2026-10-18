<?php

namespace Tests\Feature;

use App\Application\Roles\CrearRol;
use App\Application\Roles\EditarRol;
use App\Application\Roles\EliminarRol;
use App\Application\Roles\SincronizarPermisosRol;
use App\Models\Copropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-09 (Sprint 18): endurecimiento de las reglas de Roles.
 */
class RolesEndurecimientoHuUsr09Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function operador($organizacion, Copropiedad $copropiedad, array $permisos = ['roles.gestionar']): array
    {
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'admin', $permisos);

        return [$usuario, $contexto];
    }

    private function rolesBaseGlobales(): void
    {
        setPermissionsTeamId(null);
        foreach (['admin', 'gestor', 'apoyo', 'auditor', 'residente'] as $clave) {
            SpatieRole::findOrCreate($clave, 'web');
        }
    }

    public function test_eliminar_rol_en_uso_informa_el_conteo_de_usuarios(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad);
        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Rol con usuarios']);

        setPermissionsTeamId($copropiedad->id);
        foreach (['uno@example.test', 'dos@example.test'] as $email) {
            User::factory()->create(['email' => $email])->assignRole($rol);
        }

        try {
            app(EliminarRol::class)->ejecutar($operador, $contexto, $rol->id);
            $this->fail('Debía bloquearse la eliminación del Rol en uso.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rol', $e->errors());
            $this->assertStringContainsString('2 usuarios', $e->errors()['rol'][0]);
        }

        $this->assertDatabaseHas('roles', ['id' => $rol->id]);
    }

    public function test_no_se_elimina_un_rol_base_del_catalogo_inicial(): void
    {
        $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $this->rolesBaseGlobales();

        foreach (['admin', 'gestor', 'apoyo', 'auditor', 'residente'] as $clave) {
            $rol = SpatieRole::query()
                ->where('name', $clave)
                ->where('guard_name', 'web')
                ->whereNull('copropiedad_id')
                ->firstOrFail();

            try {
                app(EliminarRol::class)->ejecutar($plataforma, null, $rol->id);
                $this->fail("Debía bloquearse la eliminación del Rol base {$clave}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('rol', $e->errors());
            }

            $this->assertDatabaseHas('roles', ['id' => $rol->id]);
        }
    }

    public function test_la_proteccion_del_catalogo_inicial_normaliza_el_nombre(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);

        // Variante con mayúsculas y espacios: equivalente a 'admin' ante la
        // colación *_ci y por la normalización canónica.
        setPermissionsTeamId(null);
        $variante = SpatieRole::create(['name' => '  AdMiN  ', 'guard_name' => 'web']);

        try {
            app(EliminarRol::class)->ejecutar($plataforma, null, $variante->id);
            $this->fail('Debía bloquearse la eliminación del Rol base con nombre equivalente.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('admin', mb_strtolower($e->errors()['rol'][0]));
        }
        $this->assertDatabaseHas('roles', ['id' => $variante->id]);

        // Un Rol personalizado de Copropiedad no es un Rol base del catálogo
        // inicial y conserva su ciclo de vida normal (aislamiento por alcance).
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad);
        $custom = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Coordinador']);
        app(EliminarRol::class)->ejecutar($operador, $contexto, $custom->id);
        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }

    public function test_admin_base_no_puede_renombrarse(): void
    {
        $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        setPermissionsTeamId(null);
        $adminBase = SpatieRole::findOrCreate('admin', 'web');

        try {
            app(EditarRol::class)->ejecutar($plataforma, null, $adminBase->id, ['nombre' => 'Administrador']);
            $this->fail('Debía bloquearse el renombre del Rol admin base.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nombre', $e->errors());
        }

        $this->assertSame('admin', $adminBase->fresh()->name);
    }

    public function test_admin_base_no_puede_editar_su_matriz_de_permisos(): void
    {
        $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        setPermissionsTeamId(null);
        Permission::findOrCreate('usuarios.gestionar', 'web');
        Permission::findOrCreate('pqrs.listar', 'web');
        $adminBase = SpatieRole::findOrCreate('admin', 'web');
        $adminBase->syncPermissions(['usuarios.gestionar']);

        try {
            app(SincronizarPermisosRol::class)->ejecutar($plataforma, null, $adminBase->id, ['pqrs.listar']);
            $this->fail('Debía bloquearse la edición de la matriz del Rol admin base.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permisos', $e->errors());
        }

        $this->assertSame(['usuarios.gestionar'], $adminBase->fresh()->permissions()->pluck('name')->all());
    }

    public function test_inmutabilidad_del_admin_base_aplica_tambien_en_la_web(): void
    {
        $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        setPermissionsTeamId(null);
        $adminBase = SpatieRole::findOrCreate('admin', 'web');
        Permission::findOrCreate('usuarios.gestionar', 'web');

        $this->actingAs($plataforma)
            ->put(route('roles.update', $adminBase->id), ['nombre' => 'Administrador'])
            ->assertSessionHasErrors('nombre');
        $this->put(route('roles.permisos.update', $adminBase->id), ['permisos' => ['usuarios.gestionar']])
            ->assertSessionHasErrors('permisos');

        $this->assertSame('admin', $adminBase->fresh()->name);
        $this->assertSame([], $adminBase->fresh()->permissions()->pluck('name')->all());
    }

    public function test_anti_escalada_de_la_matriz_verificada_y_endurecida(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);
        Permission::findOrCreate('usuarios.gestionar', 'web');
        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, [
            'nombre' => 'Rol propio',
            'permisos' => ['pqrs.listar'],
        ]);

        // No puede conceder un permiso que no posee.
        try {
            app(SincronizarPermisosRol::class)->ejecutar(
                $operador,
                $contexto,
                $rol->id,
                ['pqrs.listar', 'usuarios.gestionar'],
            );
            $this->fail('Debía bloquearse la autoescalada en la matriz.');
        } catch (AuthorizationException) {
            $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());
        }

        // Una clave no canónica tampoco escala (consistente con *_ci).
        try {
            app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, ['PQRS.LISTAR']);
            $this->fail('Debía rechazarse una clave no canónica.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permisos', $e->errors());
        }
        $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());

        // Con permisos poseídos, la sincronización procede con normalidad.
        app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, ['pqrs.listar']);
        $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());
    }

    /**
     * La normalización de claves es independiente del motor: espacios se
     * recortan y las variantes de mayúsculas se rechazan igual en SQLite y en
     * MySQL (la decisión se toma en PHP, con comparación exacta, no por la
     * colación *_ci de la base).
     */
    public function test_la_normalizacion_de_claves_recorta_espacios_y_rechaza_variantes_de_mayusculas(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);
        Permission::findOrCreate('pqrs.listar', 'web');
        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Rol normalizacion']);

        app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, [' pqrs.listar ', 'pqrs.listar']);
        $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());

        foreach (['PQRS.LISTAR', 'Pqrs.Listar'] as $variante) {
            try {
                app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, [$variante]);
                $this->fail("Debía rechazarse la clave no canónica {$variante}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('permisos', $e->errors());
            }
            $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());
        }
    }
}
