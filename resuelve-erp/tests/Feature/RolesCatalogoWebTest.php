<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class RolesCatalogoWebTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function operador($organizacion, Copropiedad $copropiedad, array $permisos = ['roles.gestionar', 'pqrs.listar', 'pqrs.crear'], string $rol = 'admin'): array
    {
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, $rol, $permisos);

        return [$usuario, $contexto];
    }

    public function test_flujo_web_completo_de_administracion_de_roles(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->operador($organizacion, $copropiedad);

        $this->actingAsContextual($operador)->get(route('roles.index'))
            ->assertOk()->assertSee('Roles y permisos');
        $this->get(route('roles.create'))->assertOk()->assertSee('Permisos por módulo');

        $this->post(route('roles.store'), ['nombre' => 'Coordinador web', 'permisos' => ['pqrs.listar']])
            ->assertRedirect(route('roles.index'))->assertSessionHas('success');
        $rol = \Spatie\Permission\Models\Role::query()->where('name', 'Coordinador web')->firstOrFail();
        $this->assertSame($copropiedad->id, (int) $rol->copropiedad_id);

        $this->get(route('roles.edit', $rol->id))->assertOk()->assertSee('Coordinador web');
        $this->put(route('roles.update', $rol->id), ['nombre' => 'Coordinador web editado'])
            ->assertRedirect(route('roles.edit', $rol->id))->assertSessionHas('success');
        $this->assertSame('Coordinador web editado', $rol->fresh()->name);

        $this->put(route('roles.permisos.update', $rol->id), ['permisos' => ['pqrs.listar', 'pqrs.crear']])
            ->assertRedirect(route('roles.edit', $rol->id))->assertSessionHas('success');
        $this->assertSame(['pqrs.crear', 'pqrs.listar'], $rol->fresh()->permissions()->pluck('name')->sort()->values()->all());

        $this->get(route('roles.index'))->assertSee('data-confirm', false);
        $this->delete(route('roles.destroy', $rol->id))
            ->assertRedirect(route('roles.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);

        // Auditoría específica registrada y sin duplicado genérico del middleware.
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.store']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'rol.permissions.sync']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'POST roles.store']);
    }

    public function test_sin_permiso_roles_gestionar_la_interfaz_responde_403(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->operador($organizacion, $copropiedad);
        $this->actingAsContextual($operador);
        $this->post(route('roles.store'), ['nombre' => 'Rol propio', 'permisos' => ['pqrs.listar']]);
        $rol = \Spatie\Permission\Models\Role::query()->where('name', 'Rol propio')->firstOrFail();

        [$sinPermiso] = $this->operador($organizacion, $copropiedad, ['pqrs.listar'], 'lector');
        $this->actingAsContextual($sinPermiso);

        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('roles.create'))->assertForbidden();
        $this->get(route('roles.edit', $rol->id))->assertForbidden();
        $this->post(route('roles.store'), ['nombre' => 'Prohibido'])->assertForbidden();
        $this->put(route('roles.update', $rol->id), ['nombre' => 'Prohibido'])->assertForbidden();
        $this->put(route('roles.permisos.update', $rol->id), ['permisos' => []])->assertForbidden();
        $this->delete(route('roles.destroy', $rol->id))->assertForbidden();
    }

    public function test_aislamiento_web_entre_copropiedades(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        [$operadorA] = $this->operador($organizacion, $copropiedadA, ['roles.gestionar', 'pqrs.listar', 'pqrs.crear'], 'admin');
        [$operadorB] = $this->operador($organizacion, $copropiedadB, ['roles.gestionar', 'pqrs.listar', 'pqrs.crear'], 'gestor');

        $this->actingAsContextual($operadorA)->post(route('roles.store'), ['nombre' => 'Rol de A', 'permisos' => ['pqrs.listar']]);
        $rolA = \Spatie\Permission\Models\Role::query()->where('name', 'Rol de A')->firstOrFail();

        $this->actingAsContextual($operadorB);
        $this->get(route('roles.index'))->assertOk()->assertDontSee('Rol de A');
        $this->get(route('roles.edit', $rolA->id))->assertForbidden();
        $this->put(route('roles.update', $rolA->id), ['nombre' => 'Invadido'])->assertForbidden();
        $this->delete(route('roles.destroy', $rolA->id))->assertForbidden();
        $this->assertSame('Rol de A', $rolA->fresh()->name);
    }

    public function test_validaciones_web_y_eliminacion_segura(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->operador($organizacion, $copropiedad);

        $this->actingAsContextual($operador)->post(route('roles.store'), [])
            ->assertSessionHasErrors('nombre');
        $this->post(route('roles.store'), ['nombre' => 'Rol con permiso desconocido', 'permisos' => ['no.existe']])
            ->assertSessionHasErrors('permisos');

        $this->post(route('roles.store'), ['nombre' => 'Rol en uso', 'permisos' => ['pqrs.listar']]);
        $rol = \Spatie\Permission\Models\Role::query()->where('name', 'Rol en uso')->firstOrFail();
        $asignado = User::factory()->create();
        setPermissionsTeamId($copropiedad->id);
        $asignado->assignRole($rol);

        $this->delete(route('roles.destroy', $rol->id))->assertSessionHasErrors('rol');
        $this->assertDatabaseHas('roles', ['id' => $rol->id]);
    }

    public function test_navegacion_se_condiciona_por_permisos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$conPermiso] = $this->operador($organizacion, $copropiedad, ['roles.gestionar', 'pqrs.listar'], 'admin');
        [$sinPermiso] = $this->operador($organizacion, $copropiedad, ['pqrs.listar'], 'lector');

        $this->actingAsContextual($conPermiso)->get(route('panel'))
            ->assertOk()->assertSee('Catálogo de roles');
        $this->actingAsContextual($sinPermiso)->get(route('panel'))
            ->assertOk()->assertDontSee('Catálogo de roles');
    }

    public function test_autoridad_de_plataforma_administra_roles_globales(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);

        $this->actingAs($plataforma)->get(route('roles.index'))->assertOk()->assertSee('Roles y permisos');
        $this->post(route('roles.store'), ['nombre' => 'Rol base nuevo'])
            ->assertRedirect(route('roles.index'))->assertSessionHas('success');

        $rol = \Spatie\Permission\Models\Role::query()->where('name', 'Rol base nuevo')->firstOrFail();
        $this->assertNull($rol->copropiedad_id);
        $this->get(route('roles.index'))->assertSee('Global');
    }
}
