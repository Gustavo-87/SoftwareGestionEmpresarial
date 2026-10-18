<?php

namespace Tests\Feature;

use App\Application\Roles\CapacidadAdministrativa;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-09 (Sprint 18), hallazgo §9-5 de roles-y-permisos.md: el cambio de
 * users.role protege la existencia de ≥1 miembro con usuarios.gestionar,
 * reutilizando CapacidadAdministrativa con bloqueo (patrón de destroy).
 *
 * Nota de alcance: desde el Bloque 2B la autorización contextual se deriva de
 * los roles Spatie del equipo activo, por lo que un operador que modifica a
 * otro usuario conserva por sí mismo la capacidad de administración salvo
 * estados divergentes (p. ej. la clase documentada de divergencia entre
 * users.role y el contexto). El caso bloqueable reproducible es el auto-cambio
 * de rol con users.role divergente del contexto (clase de divergencia nº 1).
 */
class UserManagementRoleChangeProtectionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    /** Catálogo legado mínimo para la doble escritura de users.role. */
    private function catalogoLegado(): void
    {
        foreach (['admin', 'gestor', 'apoyo', 'auditor', 'residente', 'operador'] as $clave) {
            Rol::query()->updateOrCreate(
                ['clave' => $clave],
                ['nombre' => ucfirst($clave), 'ambito_aplicable' => 'copropiedad', 'estado' => 'activo'],
            );
        }
    }

    /**
     * Escenario del hallazgo: el usuario es el único titular de
     * usuarios.gestionar (vía Spatie, fuente efectiva) y su users.role diverge
     * del contexto ('gestor' con capacidad real de administración). Cambiar su
     * propio users.role a 'admin' conservaría el campo legado pero su espejo
     * Spatie 'admin' no otorga usuarios.gestionar: sin la protección, la
     * Copropiedad quedaría sin capacidad administrativa efectiva.
     *
     * @return array{0: User, 1: \App\Models\Organizacion, 2: \App\Models\Copropiedad}
     */
    private function escenarioUltimoTitular(): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->catalogoLegado();

        $titular = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($titular, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);

        setPermissionsTeamId(null);
        SpatieRole::findOrCreate('admin', 'web');

        return [$titular, $organizacion, $copropiedad];
    }

    public function test_cambio_de_rol_bloqueado_si_deja_la_copropiedad_sin_usuarios_gestionar(): void
    {
        [$titular] = $this->escenarioUltimoTitular();
        $rolesAntes = DB::table('model_has_roles')->count();
        $espejoAntes = DB::table('membresia_copropiedad_rol')->count();

        $this->actingAsContextual($titular)
            ->patch(route('users.role.update', $titular), ['role' => 'admin'])
            ->assertStatus(422);

        $this->assertSame('gestor', $titular->fresh()->role);
        // La operación bloqueada no altera asignaciones existentes.
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
        $this->assertSame($espejoAntes, DB::table('membresia_copropiedad_rol')->count());
    }

    public function test_update_completo_tambien_protege_la_capacidad_administrativa(): void
    {
        [$titular] = $this->escenarioUltimoTitular();

        $this->actingAsContextual($titular)
            ->put(route('users.update', $titular), [
                'name' => 'Usuario Editado',
                'email' => $titular->email,
                'role' => 'admin',
                'tower' => 'A',
                'unit' => '101',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertStatus(422);

        $this->assertSame('gestor', $titular->fresh()->role);
        $this->assertNotSame('Usuario Editado', $titular->fresh()->name);
    }

    public function test_cambio_de_rol_permitido_si_el_nuevo_rol_conserva_gestionar(): void
    {
        [$titular] = $this->escenarioUltimoTitular();
        setPermissionsTeamId(null);
        SpatieRole::findOrCreate('admin', 'web')->syncPermissions(['usuarios.gestionar']);

        $this->actingAsContextual($titular)
            ->patch(route('users.role.update', $titular), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame('admin', $titular->fresh()->role);
    }

    public function test_cambio_de_rol_de_otro_usuario_permitido_si_conserva_capacidad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->catalogoLegado();

        $operador = User::factory()->create(['role' => 'gestor']);
        $objetivo = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($operador, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);

        $this->actingAsContextual($operador)
            ->patch(route('users.role.update', $objetivo), ['role' => 'residente'])
            ->assertRedirect();

        $this->assertSame('residente', $objetivo->fresh()->role);
    }

    public function test_el_guardia_no_afecta_a_usuarios_sin_membresia_en_el_contexto(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->catalogoLegado();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $externo = User::factory()->create(['role' => 'residente']);

        $this->actingAsContextual($admin)
            ->patch(route('users.role.update', $externo), ['role' => 'auditor'])
            ->assertRedirect();

        $this->assertSame('auditor', $externo->fresh()->role);
    }

    /**
     * La protección mide el estado final real, no el resultado previsto del
     * nuevo nombre de rol: ante la divergencia legada (asignación activa sin
     * espejo Spatie) la escritura omite el espejo y el usuario conserva su rol
     * efectivo, por lo que predecir bloquearía falsamente la operación.
     */
    public function test_el_guardia_mide_el_estado_final_real_ante_divergencia_legada(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->catalogoLegado();

        $titular = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($titular, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);

        // Divergencia legada (clase documentada): asignación activa del Rol
        // 'admin' sin espejo en Spatie. El rol Spatie 'admin' no otorga
        // usuarios.gestionar.
        $this->asignacionLegado($titular, $organizacion, $copropiedad, 'admin');
        setPermissionsTeamId(null);
        SpatieRole::findOrCreate('admin', 'web');

        $this->actingAsContextual($titular)
            ->patch(route('users.role.update', $titular), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame('admin', $titular->fresh()->role);
        $this->assertGreaterThanOrEqual(1, $this->titularesGestionar($titular, $copropiedad));
    }

    /**
     * Ante Roles homónimos (global y de la Copropiedad con el mismo nombre,
     * alcanzables vía renombre desde EditarRol) Spatie no garantiza qué Rol
     * resuelve al asignar. La protección no depende de esa resolución: si la
     * operación se permite, la Copropiedad conserva al menos un titular
     * efectivo de usuarios.gestionar; si no, queda intacta.
     */
    public function test_la_proteccion_es_un_invariant_ante_roles_homonimos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->catalogoLegado();

        $titular = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($titular, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);

        setPermissionsTeamId(null);
        SpatieRole::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        setPermissionsTeamId($copropiedad->id);
        $rolPropio = SpatieRole::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $rolPropio->givePermissionTo('usuarios.gestionar');
        setPermissionsTeamId(null);

        $respuesta = $this->actingAsContextual($titular)
            ->patch(route('users.role.update', $titular), ['role' => 'admin']);

        if ($respuesta->isRedirection()) {
            $this->assertGreaterThanOrEqual(1, $this->titularesGestionar($titular, $copropiedad));
        } else {
            $respuesta->assertStatus(422);
            $this->assertSame('gestor', $titular->fresh()->role);
        }
    }

    /** Titulares efectivos de usuarios.gestionar en la Copropiedad del usuario. */
    private function titularesGestionar(User $usuario, Copropiedad $copropiedad): int
    {
        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
        $capacidad = app(CapacidadAdministrativa::class);

        return $capacidad->titularesRestantes($capacidad->membresiasBloqueadas($membresia), $membresia);
    }
}
