<?php

namespace Tests\Feature;

use App\Application\Roles\GestionarPermisosDirectos;
use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-05 — Permisos directos por usuario (Sprint 18; ADR-013).
 */
class PermisosDirectosHuUsr05Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_asignar_y_revocar_un_permiso_directo_cambia_los_permisos_efectivos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);
        $servicio = app(GestionarPermisosDirectos::class);

        $servicio->asignar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
        $this->assertContains('auditoria.ver', $this->contexto($objetivo)->clavesPermisos());
        $this->assertEqualsCanonicalizing(
            ['auditoria.ver'],
            $servicio->directos($membresia)->pluck('name')->all(),
        );

        $servicio->revocar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
        $this->assertNotContains('auditoria.ver', $this->contexto($objetivo)->clavesPermisos());
        $this->assertSame([], $servicio->directos($membresia)->pluck('name')->all());
    }

    public function test_la_asignacion_y_revocacion_se_auditan(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);
        $servicio = app(GestionarPermisosDirectos::class);

        $servicio->asignar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
        $servicio->revocar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');

        $acciones = AuditLog::query()->whereIn('action', ['usuario.permiso_directo.asignar', 'usuario.permiso_directo.revocar'])->get();
        $this->assertSame(['usuario.permiso_directo.asignar', 'usuario.permiso_directo.revocar'], $acciones->pluck('action')->all());
        $this->assertSame(['auditoria.ver', 'auditoria.ver'], $acciones->pluck('metadata.permiso')->all());
    }

    public function test_la_anti_escalada_impide_conceder_permisos_que_no_se_poseen(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad, ['usuarios.gestionar', 'pqrs.listar']);
        SpatiePermission::findOrCreate('auditoria.ver', 'web');
        $membresia = $this->membresiaDe($objetivo, $copropiedad);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('No puedes conceder permisos que no posees');
        app(GestionarPermisosDirectos::class)->asignar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
    }

    public function test_sin_usuarios_gestionar_no_se_administran_permisos_directos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad, ['pqrs.listar']);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);

        $this->expectException(AuthorizationException::class);
        app(GestionarPermisosDirectos::class)->asignar($operador, $this->contexto($operador), $membresia, 'pqrs.listar');
    }

    public function test_la_web_responde_403_sin_permiso_y_permite_la_gestion_con_autorizacion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad, ['usuarios.gestionar', 'pqrs.listar']);
        $sinPermiso = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);

        $this->actingAsContextual($sinPermiso)
            ->post(route('users.permisos-directos.store', $objetivo), ['permiso' => 'pqrs.listar'])
            ->assertForbidden();

        $this->actingAsContextual($operador)
            ->get(route('users.edit', $objetivo))
            ->assertOk()
            ->assertSee('Permisos directos en esta Copropiedad')
            ->assertSee('Ver matriz de permisos');

        $this->actingAsContextual($operador)
            ->post(route('users.permisos-directos.store', $objetivo), ['permiso' => 'pqrs.listar'])
            ->assertRedirect();

        $permiso = SpatiePermission::query()->where('name', 'pqrs.listar')->firstOrFail();
        $this->actingAsContextual($operador)
            ->delete(route('users.permisos-directos.destroy', [$objetivo, $permiso]))
            ->assertRedirect();
    }

    public function test_no_se_puede_revocar_usuarios_gestionar_si_deja_la_copropiedad_sin_capacidad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        // La autoridad de plataforma queda exenta de usuarios.gestionar, por lo
        // que puede revocar el único permiso directo titular de la capacidad.
        $autoridad = User::factory()->create(['role' => 'admin', 'es_administrador_sistema' => true]);
        $objetivo = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);
        SpatiePermission::findOrCreate('usuarios.gestionar', 'web');
        $membresia = $this->membresiaDe($objetivo, $copropiedad);
        $servicio = app(GestionarPermisosDirectos::class);

        $servicio->asignar($autoridad, $this->contexto($autoridad), $membresia, 'usuarios.gestionar');

        try {
            $servicio->revocar($autoridad, $this->contexto($autoridad), $membresia, 'usuarios.gestionar');
            $this->fail('Se esperaba la protección de capacidad administrativa.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('capacidad de administración', $e->getMessage());
        }

        // Con otro titular, la revocación ya no deja la Copropiedad sin capacidad.
        $otro = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($otro, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $servicio->revocar($autoridad, $this->contexto($autoridad), $membresia, 'usuarios.gestionar');
        $this->assertNotContains('usuarios.gestionar', $this->contexto($objetivo)->clavesPermisos());
    }

    public function test_un_permiso_desactivado_no_admite_nuevas_asignaciones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);

        SpatiePermission::query()->where('name', 'auditoria.ver')->update(['estado' => 'inactivo']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('está desactivado y no admite nuevas asignaciones');
        app(GestionarPermisosDirectos::class)->asignar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
    }

    public function test_revocar_un_permiso_no_asignado_es_un_error_validado(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $objetivo] = $this->operadorYObjetivo($organizacion, $copropiedad);
        $membresia = $this->membresiaDe($objetivo, $copropiedad);

        $this->expectException(ValidationException::class);
        app(GestionarPermisosDirectos::class)->revocar($operador, $this->contexto($operador), $membresia, 'auditoria.ver');
    }

    /** @return array{0: User, 1: User} */
    private function operadorYObjetivo($organizacion, $copropiedad, array $permisosOperador = ['usuarios.gestionar', 'pqrs.listar', 'auditoria.ver']): array
    {
        $operador = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($operador, $organizacion, $copropiedad, 'admin', $permisosOperador);
        $objetivo = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);

        return [$operador, $objetivo];
    }

    private function membresiaDe(User $user, $copropiedad): MembresiaCopropiedad
    {
        return MembresiaCopropiedad::query()
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
}
