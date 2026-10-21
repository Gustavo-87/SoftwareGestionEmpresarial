<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Selección de Copropiedad y Rol activo al ingresar (ampliación del Sprint 18).
 */
class AccesoSeleccionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_un_rol_unico_se_selecciona_solo_y_accede_al_panel(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        [$membresia] = $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('panel'));

        $this->assertNotNull($membresia->fresh()->rol_activo_id);
        $this->get(route('panel'))->assertOk();
    }

    public function test_varios_roles_exigen_seleccion_antes_de_operar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        [$membresia] = $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['pqrs.listar']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('acceso.seleccion'));
        $this->assertNull($membresia->fresh()->rol_activo_id);

        // No se permite operar omitiendo la selección.
        $this->get(route('pqrs.index'))->assertRedirect(route('acceso.seleccion'));

        $this->get(route('acceso.seleccion'))
            ->assertOk()
            ->assertSee('Selecciona el Rol activo')
            ->assertSee('gestor')
            ->assertSee('auditor');

        $this->post(route('acceso.rol'), ['rol_id' => $rolExtra->id])
            ->assertRedirect(route('pqrs.index'));

        $this->assertSame((int) $rolExtra->id, (int) $membresia->fresh()->rol_activo_id);
        $this->get(route('pqrs.index'))->assertOk();
    }

    public function test_varias_copropiedades_exigen_seleccion_primero(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Conjunto Las Acacias', 'estado' => 'activa']);
        $user = User::factory()->create(['password' => '12345']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        $membresiaB = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $otra->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
        setPermissionsTeamId(null);
        $rolB = SpatieRole::findOrCreate('auditor', 'web');
        SpatiePermission::findOrCreate('pqrs.listar', 'web');
        $rolB->syncPermissions(['pqrs.listar']);
        setPermissionsTeamId($otra->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole($rolB);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('acceso.seleccion'));

        $respuesta = $this->get(route('acceso.seleccion'))
            ->assertOk()
            ->assertSee('Selecciona la Copropiedad')
            ->assertSee('Conjunto Las Acacias');

        // Toda la información de roles queda fuera hasta elegir Copropiedad.
        $this->assertStringNotContainsString('Selecciona el Rol activo', $respuesta->content());

        // Con un solo Rol en la Copropiedad elegida, se selecciona automáticamente.
        $this->post(route('acceso.copropiedad'), ['copropiedad_id' => $otra->id])
            ->assertRedirect(route('panel'));

        $this->assertSame((int) $otra->id, (int) session('copropiedad_activa_id'));
        $this->assertSame((int) $rolB->id, (int) $membresiaB->fresh()->rol_activo_id);
    }

    public function test_el_rol_debe_pertenecer_a_los_roles_autorizados(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['pqrs.listar']);
        $rolAjeno = $this->asignarRolExtra(User::factory()->create(), $copropiedad, 'residente', ['pqrs.listar']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('acceso.seleccion'));

        $this->post(route('acceso.rol'), ['rol_id' => $rolAjeno->id])
            ->assertSessionHasErrors('rol_id');

        $this->get(route('acceso.seleccion'))->assertOk();
        $this->post(route('acceso.rol'), ['rol_id' => $rolExtra->id])->assertRedirect(route('panel'));
    }

    public function test_un_rol_activo_revocado_vuelve_a_exigir_seleccion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        [$membresia] = $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['pqrs.listar']);
        $this->asignarRolExtra($user, $copropiedad, 'apoyo', ['pqrs.listar']);
        $membresia->forceFill(['rol_activo_id' => $rolExtra->id])->save();

        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->removeRole($rolExtra);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('acceso.seleccion'));
        $this->get(route('pqrs.index'))->assertRedirect(route('acceso.seleccion'));
    }

    public function test_no_se_muestra_informacion_de_la_cuenta_antes_de_validar_las_credenciales(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $this->asignarRolExtra($user, $copropiedad, 'RolConfidencialXYZ', ['pqrs.listar']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('RolConfidencialXYZ')
            ->assertDontSee($user->email);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mala'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString('RolConfidencialXYZ', session('errors')->toJson());
    }

    public function test_administrador_de_plataforma_conserva_su_flujo(): void
    {
        $user = User::factory()->create(['password' => '12345', 'es_administrador_sistema' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('admin.index'));
        $this->get(route('admin.index'))->assertOk();
    }

    public function test_usuario_sin_membresia_operativa_conserva_su_flujo(): void
    {
        $this->createInstitutionalContext();
        $user = User::factory()->create(['password' => '12345']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => '12345'])
            ->assertRedirect(route('panel'));
        $this->get(route('panel'))->assertOk();
    }

    public function test_las_rutas_de_seleccion_exigen_autenticacion(): void
    {
        $this->get(route('acceso.seleccion'))->assertRedirect(route('login'));
        $this->post(route('acceso.rol'), ['rol_id' => 1])->assertRedirect(route('login'));
        $this->post(route('acceso.copropiedad'), ['copropiedad_id' => 1])->assertRedirect(route('login'));
    }

    private function asignarRolExtra(User $user, Copropiedad $copropiedad, string $clave, array $permisos): SpatieRole
    {
        setPermissionsTeamId(null);
        foreach ($permisos as $permiso) {
            SpatiePermission::findOrCreate($permiso, 'web');
        }
        $rol = SpatieRole::findOrCreate($clave, 'web');
        $rol->syncPermissions($permisos);

        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole($rol);

        return $rol;
    }
}
