<?php

namespace Tests\Feature;

use App\Application\Roles\ConsultaMatrizPermisosUsuario;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-06 — Matriz global de permisos "ver como usuario" (Sprint 18).
 */
class MatrizPermisosHuUsr06Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_la_matriz_indica_el_origen_de_cada_permiso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $rolExtra = $this->asignarRolExtra($objetivo, $copropiedad, 'auditor', ['auditoria.ver']);
        $this->asignarDirecto($objetivo, $copropiedad, 'informes.exportar');

        $matriz = app(ConsultaMatrizPermisosUsuario::class)->para($operador, $this->contexto($operador), $objetivo);

        $this->assertTrue($matriz['membresiaVigente']);
        $this->assertSame(['gestor', 'auditor'], $matriz['rolesAsignados']);
        $this->assertSame(['rol gestor'], $this->fila($matriz, 'pqrs.listar')['origenes']);
        $this->assertSame(['rol auditor'], $this->fila($matriz, 'auditoria.ver')['origenes']);
        $this->assertSame(['directo'], $this->fila($matriz, 'informes.exportar')['origenes']);
        $this->assertTrue($this->fila($matriz, 'pqrs.listar')['efectivo']);
        $this->assertTrue($this->fila($matriz, 'informes.exportar')['efectivo']);
    }

    public function test_el_rol_activo_del_usuario_consultado_restringe_lo_efectivo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $rolExtra = $this->asignarRolExtra($objetivo, $copropiedad, 'auditor', ['auditoria.ver']);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);
        app(\App\Application\Roles\GestionarRolActivo::class)->ejecutar($objetivo, $membresia, (int) $rolExtra->id);

        $matriz = app(ConsultaMatrizPermisosUsuario::class)->para($operador, $this->contexto($operador), $objetivo);

        $this->assertSame('auditor', $matriz['rolActivo']);
        $this->assertSame(['rol activo'], $this->fila($matriz, 'auditoria.ver')['origenes']);
        $this->assertTrue($this->fila($matriz, 'auditoria.ver')['efectivo']);
        $this->assertSame(['rol gestor'], $this->fila($matriz, 'pqrs.listar')['origenes']);
        $this->assertFalse($this->fila($matriz, 'pqrs.listar')['efectivo']);
    }

    public function test_un_permiso_desactivado_no_es_efectivo_aunque_este_asignado(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);

        SpatiePermission::query()->where('name', 'pqrs.listar')->update(['estado' => 'inactivo']);

        $matriz = app(ConsultaMatrizPermisosUsuario::class)->para($operador, $this->contexto($operador), $objetivo);

        $this->assertSame('inactivo', $this->fila($matriz, 'pqrs.listar')['estado']);
        $this->assertSame([], $this->fila($matriz, 'pqrs.listar')['origenes']);
        $this->assertFalse($this->fila($matriz, 'pqrs.listar')['efectivo']);
    }

    public function test_requiere_usuarios_gestionar_o_autoridad_de_plataforma(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $sinPermiso = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);

        $this->expectException(AuthorizationException::class);
        app(ConsultaMatrizPermisosUsuario::class)->para($sinPermiso, $this->contexto($sinPermiso), $objetivo);
    }

    public function test_la_web_responde_403_sin_permiso_y_presenta_la_matriz_con_autorizacion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $sinPermiso = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);

        $this->actingAsContextual($sinPermiso)->get(route('users.permisos', $objetivo))->assertForbidden();

        $respuesta = $this->actingAsContextual($operador)->get(route('users.permisos', $objetivo))
            ->assertOk()
            ->assertSee('Matriz de permisos de')
            ->assertSee('rol gestor')
            ->assertSee('Ver como usuario');

        // Solo lectura: la matriz no expone mutaciones de permisos.
        $this->assertStringNotContainsString('users.permisos-directos', $respuesta->content());
    }

    public function test_sin_membresia_vigente_la_matriz_lo_expone_sin_permisos_efectivos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);
        $membresia->forceFill(['estado' => 'suspendida'])->save();

        $matriz = app(ConsultaMatrizPermisosUsuario::class)->para($operador, $this->contexto($operador), $objetivo);

        $this->assertFalse($matriz['membresiaVigente']);
        $this->assertFalse($this->fila($matriz, 'pqrs.listar')['efectivo']);
    }

    /** @return array{0: User, 1: User} */
    private function operadorYObjetivo($organizacion, $copropiedad): array
    {
        $operador = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($operador, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $objetivo = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        return [$operador, $objetivo];
    }

    private function asignarRolExtra(User $user, $copropiedad, string $clave, array $permisos): SpatieRole
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

    private function asignarDirecto(User $user, $copropiedad, string $clave): void
    {
        setPermissionsTeamId(null);
        $permiso = SpatiePermission::findOrCreate($clave, 'web');
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->givePermissionTo($permiso);
    }

    private function membresiaDe(User $user, $copropiedad)
    {
        return \App\Models\MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
    }

    private function contexto(User $user)
    {
        return app(\App\Application\Contexto\ContextResolver::class)
            ->resolverExplicito(
                \App\Models\Organizacion::query()->value('id'),
                \App\Models\Copropiedad::query()->value('id'),
                $user->id,
            );
    }

    private function fila(array $matriz, string $clave): array
    {
        foreach ($matriz['modulos'] as $permisos) {
            foreach ($permisos as $fila) {
                if ($fila['clave'] === $clave) {
                    return $fila;
                }
            }
        }

        $this->fail("El permiso {$clave} no aparece en la matriz.");
    }
}
