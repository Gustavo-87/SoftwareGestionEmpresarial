<?php

namespace Tests\Feature\Api;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-API-01 — Autenticación de la API REST v1 con Sanctum (Sprint 21).
 */
class ApiAuthTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_login_valido_emite_token_y_usa_el_rol_activo_de_la_membresia(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolAuditor = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $membresia = $this->membresia($user, $copropiedad->id);
        $membresia->forceFill(['rol_activo_id' => $rolAuditor->id])->save();

        $respuesta = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('usuario.id', $user->id)
            ->assertJsonPath('membresia.id', $membresia->id)
            ->assertJsonPath('rol_activo.id', $rolAuditor->id)
            ->assertJsonPath('rol_activo.name', $rolAuditor->name);
        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertNotEmpty($respuesta->json('roles'));
    }

    public function test_login_con_rol_asignado_persiste_la_seleccion_del_rol_activo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $rolAuditor = $this->asignarRolExtra($user, $copropiedad, 'auditor', ['auditoria.ver']);

        $membresia = $this->membresia($user, $copropiedad->id);

        $respuesta = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'rol_id' => $rolAuditor->id,
        ]);

        $respuesta->assertOk()->assertJsonPath('rol_activo.id', $rolAuditor->id);
        $this->assertSame($rolAuditor->id, $membresia->fresh()->rol_activo_id);
    }

    public function test_login_con_credenciales_invalidas_responde_401(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ])->assertStatus(401)->assertJsonMissing(['token']);

        $this->postJson('/api/v1/login', [
            'email' => 'no-existe@example.com',
            'password' => 'password',
        ])->assertStatus(401)->assertJsonMissing(['token']);
    }

    public function test_usuario_inactivo_no_recibe_token(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor', 'estado' => 'inactivo', 'desactivado_at' => now()]);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonMissing(['token']);
    }

    public function test_seleccion_de_rol_no_autorizada_no_concede_permisos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        // Rol existente en el catálogo, pero no asignado al usuario.
        $rolAjeno = SpatieRole::findOrCreate('auditor', 'web');

        $respuesta = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'rol_id' => $rolAjeno->id,
        ]);

        $respuesta->assertStatus(422)->assertJsonMissing(['token']);
        $this->assertNull($this->membresia($user, $copropiedad->id)->fresh()->rol_activo_id);
    }

    public function test_logout_revoca_el_token(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);

        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/v1/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Cada petición HTTP parte de guards nuevos (como en producción):
        // en las pruebas el guard conserva el usuario entre peticiones.
        auth()->forgetGuards();

        // Un token basura no autentica.
        $this->postJson('/api/v1/logout', [], ['Authorization' => 'Bearer token-basura'])
            ->assertStatus(401);

        auth()->forgetGuards();

        // El token revocado ya no autentica.
        $this->postJson('/api/v1/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
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

    private function membresia(User $user, int $copropiedadId): MembresiaCopropiedad
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)
            ->where('copropiedad_id', $copropiedadId)
            ->firstOrFail();
    }
}
