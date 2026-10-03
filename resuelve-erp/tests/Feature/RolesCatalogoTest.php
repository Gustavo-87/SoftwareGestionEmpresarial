<?php

namespace Tests\Feature;

use App\Application\Roles\ConsultaPermisosCatalogo;
use App\Application\Roles\ConsultaRolesGestionables;
use App\Application\Roles\CrearRol;
use App\Application\Roles\EditarRol;
use App\Application\Roles\EliminarRol;
use App\Application\Roles\SincronizarPermisosRol;
use App\Models\Copropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class RolesCatalogoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function operador($organizacion, Copropiedad $copropiedad, array $permisos = ['roles.gestionar']): array
    {
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'admin', $permisos);

        return [$usuario, $contexto];
    }

    public function test_rol_personalizado_se_crea_edita_sincroniza_y_elimina(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar', 'pqrs.crear']);

        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, [
            'nombre' => 'Coordinador de obra',
            'permisos' => ['pqrs.listar'],
        ]);
        $this->assertSame('web', $rol->guard_name);
        $this->assertSame($copropiedad->id, (int) $rol->copropiedad_id);
        $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.store']);

        $rol = app(EditarRol::class)->ejecutar($operador, $contexto, $rol->id, ['nombre' => 'Coordinador de obra mayor']);
        $this->assertSame('Coordinador de obra mayor', $rol->name);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.update']);

        app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, ['pqrs.listar', 'pqrs.crear']);
        $this->assertSame(['pqrs.crear', 'pqrs.listar'], $rol->permissions()->pluck('name')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.permissions.sync']);

        $gestionables = app(ConsultaRolesGestionables::class)->ejecutar($operador, $contexto);
        $this->assertSame(['Coordinador de obra mayor'], $gestionables->pluck('name')->all());
        $this->assertSame(0, (int) $gestionables->first()->asignaciones);

        $porModulo = app(ConsultaPermisosCatalogo::class)->porModulo($operador, $contexto);
        $this->assertArrayHasKey('pqrs', $porModulo);
        $this->assertArrayHasKey('roles', $porModulo);

        app(EliminarRol::class)->ejecutar($operador, $contexto, $rol->id);
        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.delete']);
    }

    public function test_crear_rol_copiando_permisos_de_un_rol_existente(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar', 'pqrs.crear']);

        $origen = app(CrearRol::class)->ejecutar($operador, $contexto, [
            'nombre' => 'Base de coordinación',
            'permisos' => ['pqrs.listar', 'pqrs.crear'],
        ]);
        $copia = app(CrearRol::class)->ejecutar($operador, $contexto, [
            'nombre' => 'Copia de coordinación',
            'copiar_desde_rol_id' => $origen->id,
        ]);

        $this->assertSame(['pqrs.crear', 'pqrs.listar'], $copia->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_validaciones_de_nombre_permisos_y_unicidad_por_alcance(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);
        $crear = app(CrearRol::class);

        foreach ([['nombre' => ''], ['nombre' => str_repeat('x', 101)]] as $datos) {
            try {
                $crear->ejecutar($operador, $contexto, $datos);
                $this->fail('Debía fallar la validación del nombre.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('nombre', $e->errors());
            }
        }

        $crear->ejecutar($operador, $contexto, ['nombre' => 'Residente especial']);
        try {
            $crear->ejecutar($operador, $contexto, ['nombre' => 'Residente especial']);
            $this->fail('Debía fallar la unicidad del nombre en el alcance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nombre', $e->errors());
        }

        try {
            $crear->ejecutar($operador, $contexto, ['nombre' => 'Rol inválido', 'permisos' => ['no.existe']]);
            $this->fail('Debía fallar un permiso desconocido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permisos', $e->errors());
        }

        // La unicidad es por alcance: la plataforma puede usar el mismo nombre como rol global.
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $global = $crear->ejecutar($plataforma, $contexto, ['nombre' => 'Residente especial']);
        $this->assertNull($global->copropiedad_id);
    }

    public function test_autorizacion_exige_roles_gestionar_o_autoridad_de_plataforma(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$sinPermiso, $contexto] = $this->operador($organizacion, $copropiedad, ['pqrs.listar']);

        foreach ([
            fn () => app(CrearRol::class)->ejecutar($sinPermiso, $contexto, ['nombre' => 'Prohibido']),
            fn () => app(ConsultaRolesGestionables::class)->ejecutar($sinPermiso, $contexto),
            fn () => app(ConsultaPermisosCatalogo::class)->porModulo($sinPermiso, $contexto),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Debía exigirse el permiso roles.gestionar.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        // La autoridad de plataforma gestiona el catálogo global sin membresía.
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $global = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Rol base nuevo']);
        $this->assertNull($global->copropiedad_id);
    }

    public function test_aislamiento_entre_copropiedades_y_roles_globales(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        [$operadorA, $contextoA] = $this->operador($organizacion, $copropiedadA, ['roles.gestionar', 'pqrs.listar']);
        [$operadorB, $contextoB] = $this->operador($organizacion, $copropiedadB, ['roles.gestionar', 'pqrs.listar']);

        $rolA = app(CrearRol::class)->ejecutar($operadorA, $contextoA, ['nombre' => 'Rol de la Copropiedad A']);
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $global = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Rol global base']);

        $this->assertSame(
            ['Rol de la Copropiedad A'],
            app(ConsultaRolesGestionables::class)->ejecutar($operadorA, $contextoA)->pluck('name')->all()
        );
        $this->assertSame(
            [],
            app(ConsultaRolesGestionables::class)->ejecutar($operadorB, $contextoB)->pluck('name')->all()
        );

        foreach ([
            fn () => app(EditarRol::class)->ejecutar($operadorB, $contextoB, $rolA->id, ['nombre' => 'Invadido']),
            fn () => app(EliminarRol::class)->ejecutar($operadorB, $contextoB, $rolA->id),
            fn () => app(SincronizarPermisosRol::class)->ejecutar($operadorB, $contextoB, $rolA->id, ['pqrs.listar']),
            fn () => app(EditarRol::class)->ejecutar($operadorB, $contextoB, $global->id, ['nombre' => 'Invadido global']),
            fn () => app(EliminarRol::class)->ejecutar($operadorB, $contextoB, $global->id),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Debía rechazarse la operación fuera del contexto.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame('Rol de la Copropiedad A', $rolA->fresh()->name);
        $this->assertSame('Rol global base', $global->fresh()->name);
    }

    public function test_anti_escalada_no_concede_permisos_no_poseidos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);
        \Spatie\Permission\Models\Permission::findOrCreate('pqrs.eliminar', 'web');
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $poderoso = app(CrearRol::class)->ejecutar($plataforma, null, [
            'nombre' => 'Rol con permisos elevados',
            'permisos' => ['pqrs.eliminar', 'roles.gestionar'],
        ]);

        foreach ([
            fn () => app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Escalado', 'permisos' => ['pqrs.eliminar']]),
            fn () => app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Escalado por copia', 'copiar_desde_rol_id' => $poderoso->id]),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Debía bloquearse la autoescalada.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Rol propio', 'permisos' => ['pqrs.listar']]);
        try {
            app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, ['pqrs.listar', 'pqrs.eliminar']);
            $this->fail('Debía bloquearse la sincronización con permisos ajenos.');
        } catch (AuthorizationException) {
            $this->assertSame(['pqrs.listar'], $rol->permissions()->pluck('name')->all());
        }

        // La autoridad de plataforma no tiene límite.
        app(SincronizarPermisosRol::class)->ejecutar($plataforma, null, $rol->id, ['pqrs.listar', 'pqrs.eliminar']);
        $this->assertSame(['pqrs.eliminar', 'pqrs.listar'], $rol->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_eliminacion_segura_bloquea_roles_en_uso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);
        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Rol en uso']);

        $asignado = User::factory()->create();
        setPermissionsTeamId($copropiedad->id);
        $asignado->assignRole($rol);

        try {
            app(EliminarRol::class)->ejecutar($operador, $contexto, $rol->id);
            $this->fail('Debía bloquearse la eliminación de un Rol en uso.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rol', $e->errors());
        }
        $this->assertDatabaseHas('roles', ['id' => $rol->id]);

        $asignado->unsetRelation('roles')->unsetRelation('permissions');
        $asignado->removeRole($rol);
        app(EliminarRol::class)->ejecutar($operador, $contexto, $rol->id);
        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);
    }

    public function test_auditoria_registra_creacion_edicion_eliminacion_y_permisos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar']);

        $rol = app(CrearRol::class)->ejecutar($operador, $contexto, ['nombre' => 'Rol auditado', 'permisos' => ['pqrs.listar']]);
        app(EditarRol::class)->ejecutar($operador, $contexto, $rol->id, ['nombre' => 'Rol auditado editado']);
        app(SincronizarPermisosRol::class)->ejecutar($operador, $contexto, $rol->id, []);
        app(EliminarRol::class)->ejecutar($operador, $contexto, $rol->id);

        $acciones = \App\Models\AuditLog::query()
            ->where('user_id', $operador->id)
            ->whereIn('action', ['rol.store', 'rol.update', 'rol.permissions.sync', 'rol.delete'])
            ->pluck('action')
            ->all();
        sort($acciones);
        $this->assertSame(['rol.delete', 'rol.permissions.sync', 'rol.store', 'rol.update'], $acciones);
    }
}
