<?php

namespace Tests\Feature;

use App\Application\Roles\GestionarRolActivo;
use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-04 — Rol activo con segregación de funciones (Sprint 18; ADR-013).
 */
class RolActivoHuUsr04Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_sin_rol_activo_se_conserva_la_union_de_roles(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $contexto = $this->resolver($organizacion, $copropiedad, $user);

        $this->assertNull($contexto->rolActivoClave);
        $this->assertEqualsCanonicalizing(['gestor', 'auditor'], $contexto->clavesRoles());
        $this->assertEqualsCanonicalizing(['gestor', 'auditor'], $contexto->clavesRolesAsignados());
        $this->assertEqualsCanonicalizing(['pqrs.listar', 'auditoria.ver'], $contexto->clavesPermisos());
    }

    public function test_con_rol_activo_los_permisos_efectivos_son_los_del_rol_mas_los_directos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);
        $this->asignarPermisoDirecto($user, $copropiedad, 'informes.exportar');

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
        app(GestionarRolActivo::class)->ejecutar($user, $membresia, (int) $rolExtra->id);

        $contexto = $this->resolver($organizacion, $copropiedad, $user);

        $this->assertSame('auditor', $contexto->rolActivoClave);
        $this->assertTrue($contexto->tieneRolActivo());
        $this->assertSame(['auditor'], $contexto->clavesRoles());
        $this->assertEqualsCanonicalizing(['gestor', 'auditor'], $contexto->clavesRolesAsignados());
        $this->assertEqualsCanonicalizing(['auditoria.ver', 'informes.exportar'], $contexto->clavesPermisos());
    }

    public function test_la_seleccion_caducada_se_revalida_y_recupera_la_union(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
        $membresia->forceFill(['rol_activo_id' => $rolExtra->id])->save();

        // El Rol deja de estar asignado: la selección caduca al resolver.
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->removeRole($rolExtra);

        $contexto = $this->resolver($organizacion, $copropiedad, $user);

        $this->assertNull($contexto->rolActivoClave);
        $this->assertSame(['gestor'], $contexto->clavesRoles());
        $this->assertSame(['pqrs.listar'], $contexto->clavesPermisos());
        $this->assertNull($membresia->fresh()->rol_activo_id);
    }

    public function test_la_seleccion_persiste_por_membresia_y_no_contamina_otra_copropiedad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $membresiaA = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();
        app(GestionarRolActivo::class)->ejecutar($user, $membresiaA, (int) $rolExtra->id);

        $membresiaB = MembresiaCopropiedad::query()->create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $otra->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
        setPermissionsTeamId(null);
        $rolSpatie = SpatieRole::findOrCreate('gestor', 'web');
        setPermissionsTeamId($otra->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole($rolSpatie);

        $contextoB = $this->resolver($organizacion, $otra, $user);

        $this->assertNull($contextoB->rolActivoClave);
        $this->assertSame(['gestor'], $contextoB->clavesRoles());
        $this->assertSame('auditor', $this->resolver($organizacion, $copropiedad, $user)->rolActivoClave);
        $this->assertNotNull($membresiaA->fresh()->rol_activo_id);
        $this->assertNull($membresiaB->fresh()->rol_activo_id);
    }

    public function test_solo_se_puede_seleccionar_el_propio_rol_activo_y_un_rol_asignado(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $otro = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        [$membresiaOtro] = $this->createContextualIdentity($otro, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        app(GestionarRolActivo::class)->ejecutar($user, $membresiaOtro, (int) $rolExtra->id);
    }

    public function test_el_rol_debe_estar_asignado_en_la_copropiedad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolAjeno = $this->asignarRolExtra(User::factory()->create(['role' => 'auditor']), $copropiedad, 'auditor', ['auditoria.ver']);

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(GestionarRolActivo::class)->ejecutar($user, $membresia, (int) $rolAjeno->id);
    }

    public function test_cambiar_contexto_integra_la_seleccion_y_limpia_con_valor_vacio(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $this->actingAsContextual($user)->post(route('contexto.cambiar'), [
            'copropiedad_id' => $copropiedad->id,
            'rol_activo_id' => $rolExtra->id,
        ])->assertRedirect(route('panel'));

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();
        $this->assertSame((int) $rolExtra->id, (int) $membresia->rol_activo_id);

        $this->actingAsContextual($user)->post(route('contexto.cambiar'), [
            'copropiedad_id' => $copropiedad->id,
            'rol_activo_id' => '',
        ])->assertRedirect(route('panel'));
        $this->assertNull($membresia->fresh()->rol_activo_id);
    }

    public function test_la_interfaz_muestra_el_selector_y_el_rol_actual(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);
        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();
        app(GestionarRolActivo::class)->ejecutar($user, $membresia, (int) $rolExtra->id);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('rol_activo_selector', false)
            ->assertSee('Todos los roles')
            ->assertSee('Rol activo: auditor');
    }

    public function test_la_seleccion_se_audita(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolExtra = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);
        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)->where('copropiedad_id', $copropiedad->id)->firstOrFail();

        app(GestionarRolActivo::class)->ejecutar($user, $membresia, (int) $rolExtra->id);
        app(GestionarRolActivo::class)->ejecutar($user, $membresia, null);

        $acciones = AuditLog::query()->where('action', 'usuario.rol_activo.seleccionar')->pluck('metadata')->all();
        $this->assertCount(2, $acciones);
        $this->assertSame('auditor', $acciones[0]['rol']);
        $this->assertNull($acciones[1]['rol']);
    }

    private function resolver($organizacion, $copropiedad, User $user)
    {
        return app(\App\Application\Contexto\ContextResolver::class)
            ->resolverExplicito($organizacion->id, $copropiedad->id, $user->id);
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

    private function asignarPermisoDirecto(User $user, Copropiedad $copropiedad, string $clave): void
    {
        setPermissionsTeamId(null);
        $permiso = SpatiePermission::findOrCreate($clave, 'web');
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->givePermissionTo($permiso);
    }
}
