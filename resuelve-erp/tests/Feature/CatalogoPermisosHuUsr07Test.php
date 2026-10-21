<?php

namespace Tests\Feature;

use App\Application\Roles\CrearRol;
use App\Application\Roles\GestionarCatalogoPermisos;
use App\Application\Roles\GestionarPermisosDirectos;
use App\Application\Roles\SincronizarPermisosRol;
use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-07 — Administración de permisos (CRUD) del catálogo (Sprint 18).
 *
 * Eliminación lógica y auditada según la decisión del Product Owner del
 * 20 de octubre de 2026: permisos base protegidos, bloqueo por uso en otros
 * Roles o usuarios (con mensaje identificando el uso), herencia automática al
 * Rol base `admin` que no cuenta como uso y retiro en la misma transacción,
 * conservación del registro y del permiso inactivo sin acceso ni nuevas
 * asignaciones.
 */
class CatalogoPermisosHuUsr07Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_crear_permiso_registra_los_datos_y_hereda_al_admin_base(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $adminBase = $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);

        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', 'Genera copias del expediente.', 'documentos');

        $this->assertSame('documentos.exportar', $permiso->name);
        $this->assertSame('Exportar documentos', $permiso->nombre);
        $this->assertSame('documentos', $permiso->modulo);
        $this->assertSame('activo', $permiso->estado);
        $this->assertTrue($adminBase->fresh()->permissions()->where('name', 'documentos.exportar')->exists());
        $this->assertSame('permiso.crear', AuditLog::query()->where('action', 'permiso.crear')->value('action'));
    }

    public function test_la_validacion_exige_formato_unicidad_y_modulo_coherente(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $servicio = app(GestionarCatalogoPermisos::class);

        try {
            $servicio->crear($autoridad, 'Documentos.Exportar', 'Nombre', null, 'documentos');
            $this->fail('Se esperaba el rechazo del formato de clave.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('clave', $e->errors());
        }

        try {
            $servicio->crear($autoridad, 'documentos.exportar', 'Nombre', null, 'pqrs');
            $this->fail('Se esperaba el rechazo del módulo incoherente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('modulo', $e->errors());
        }

        $servicio->crear($autoridad, 'documentos.exportar', 'Nombre', null, 'documentos');
        try {
            $servicio->crear($autoridad, 'documentos.exportar', 'Otro', null, 'documentos');
            $this->fail('Se esperaba el rechazo de la clave duplicada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('clave', $e->errors());
        }
    }

    public function test_los_permisos_base_quedan_protegidos_contra_eliminacion(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();
        $permiso = Permission::findOrCreate('pqrs.listar', 'web');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('permisos base del catálogo inicial están protegidos');
        app(GestionarCatalogoPermisos::class)->desactivar($autoridad, $permiso);
    }

    public function test_no_se_desactiva_un_permiso_asignado_a_otros_roles(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');

        $rol = SpatieRole::findOrCreate('gestor', 'web');
        $rol->givePermissionTo($permiso);

        try {
            $servicio->desactivar($autoridad, $permiso);
            $this->fail('Se esperaba el bloqueo por uso en otros roles.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('roles: gestor', $e->getMessage());
        }

        $this->assertSame('activo', $permiso->fresh()->estado);
    }

    public function test_no_se_desactiva_un_permiso_asignado_directamente_a_usuarios(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');

        $usuario = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);
        setPermissionsTeamId($copropiedad->id);
        $usuario->unsetRelation('permissions');
        $usuario->givePermissionTo($permiso);

        try {
            $servicio->desactivar($autoridad, $permiso);
            $this->fail('Se esperaba el bloqueo por uso en usuarios.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('usuarios: '.$usuario->email, $e->getMessage());
        }

        $this->assertSame('activo', $permiso->fresh()->estado);
    }

    public function test_la_heredencia_automatica_no_bloquea_y_se_retira_al_desactivar(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $adminBase = $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');

        $servicio->desactivar($autoridad, $permiso);

        // La fila se conserva (eliminación lógica) y se audita.
        $this->assertDatabaseHas('permissions', ['name' => 'documentos.exportar', 'estado' => 'inactivo']);
        $this->assertFalse($adminBase->fresh()->permissions()->where('name', 'documentos.exportar')->exists());
        $this->assertSame('permiso.desactivar', AuditLog::query()->where('action', 'permiso.desactivar')->value('action'));
    }

    public function test_un_permiso_inactivo_no_concede_acceso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');

        $usuario = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        setPermissionsTeamId($copropiedad->id);
        $rolGestor = $usuario->roles()->firstOrFail();
        $rolGestor->givePermissionTo($permiso);

        $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
        $this->assertContains('documentos.exportar', $contexto->clavesPermisos());

        // Estado desactivado por canal directo (dato preexistente), sin acceso.
        Permission::query()->where('name', 'documentos.exportar')->update(['estado' => 'inactivo']);
        $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
        $this->assertNotContains('documentos.exportar', $contexto->clavesPermisos());
    }

    public function test_un_permiso_inactivo_no_admite_asignaciones_en_roles(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');
        $servicio->desactivar($autoridad, $permiso);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no admiten nuevas asignaciones');
        app(SincronizarPermisosRol::class)->ejecutar($autoridad, null, SpatieRole::findOrCreate('gestor', 'web')->id, ['documentos.exportar']);
    }

    public function test_reactivar_restaura_heredencia_admin_base_y_el_acceso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $autoridad = $this->autoridadDePlataforma();
        $adminBase = $this->rolAdminBase();
        $servicio = app(GestionarCatalogoPermisos::class);
        $permiso = $servicio->crear($autoridad, 'documentos.exportar', 'Exportar documentos', null, 'documentos');
        $servicio->desactivar($autoridad, $permiso);

        $servicio->reactivar($autoridad, $permiso);

        $this->assertSame('activo', $permiso->fresh()->estado);
        $this->assertTrue($adminBase->fresh()->permissions()->where('name', 'documentos.exportar')->exists());
        $this->assertSame('permiso.activar', AuditLog::query()->where('action', 'permiso.activar')->value('action'));
    }

    public function test_solo_la_autoridad_de_plataforma_administra_el_catalogo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->rolAdminBase();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);

        $this->actingAsContextual($gestor)->get(route('admin.permisos.index'))->assertForbidden();
        $this->actingAsContextual($gestor)->post(route('admin.permisos.store'), [
            'clave' => 'documentos.exportar',
            'nombre' => 'Exportar documentos',
            'modulo' => 'documentos',
        ])->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(GestionarCatalogoPermisos::class)->crear($gestor, 'documentos.exportar', 'Exportar documentos', null, 'documentos');
    }

    public function test_la_web_permite_crear_desactivar_y_reactivar_con_autoridad(): void
    {
        $autoridad = $this->autoridadDePlataforma();
        $this->rolAdminBase();

        $this->actingAs($autoridad)->get(route('admin.permisos.index'))->assertOk()->assertSee('Catálogo de permisos');
        $this->actingAs($autoridad)->get(route('admin.permisos.create'))->assertOk()->assertSee('Nuevo permiso');

        $this->actingAs($autoridad)->post(route('admin.permisos.store'), [
            'clave' => 'documentos.exportar',
            'nombre' => 'Exportar documentos',
            'descripcion' => 'Genera copias del expediente.',
            'modulo' => 'documentos',
        ])->assertRedirect(route('admin.permisos.index'));

        $permiso = Permission::query()->where('name', 'documentos.exportar')->firstOrFail();
        $this->actingAs($autoridad)->patch(route('admin.permisos.desactivar', $permiso))->assertRedirect();
        $this->assertDatabaseHas('permissions', ['name' => 'documentos.exportar', 'estado' => 'inactivo']);

        $this->actingAs($autoridad)->patch(route('admin.permisos.reactivar', $permiso))->assertRedirect();
        $this->assertDatabaseHas('permissions', ['name' => 'documentos.exportar', 'estado' => 'activo']);
    }

    private function autoridadDePlataforma(): User
    {
        return User::factory()->create(['role' => 'admin', 'es_administrador_sistema' => true]);
    }

    private function rolAdminBase(): SpatieRole
    {
        setPermissionsTeamId(null);

        return SpatieRole::findOrCreate('admin', 'web');
    }

    private function contexto($organizacion, $copropiedad, User $user)
    {
        return app(\App\Application\Contexto\ContextResolver::class)
            ->resolverExplicito($organizacion->id, $copropiedad->id, $user->id);
    }
}
