<?php

namespace Tests\Feature;

use App\Application\Roles\CapacidadAdministrativa;
use App\Application\Usuarios\DesactivarUsuarioGlobal;
use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-USR-08 (Sprint 18): usuarios inactivos — bloqueo de inicio de sesión,
 * expulsión de sesión y "recordarme", desactivación/reactivación contextual con
 * guardas de capacidad administrativa, auditoría contextual y restauración
 * selectiva de Membresías (decisión del Product Owner).
 */
class UsuarioDesactivacionHuUsr08Test extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create(array_merge(
            ['password' => Hash::make('ClaveSegura123')],
            $atributos,
        ));
    }

    /** @return array{0: User, 1: \App\Application\Contexto\ContextoOperativo} */
    private function identidadConGestion($organizacion, Copropiedad $copropiedad, string $rol = 'admin'): array
    {
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, $rol, ['usuarios.gestionar']);

        return [$usuario, $contexto];
    }

    // ---------------------------------------------------------- CA1: acceso

    public function test_un_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $usuario = $this->usuario(['estado' => 'inactivo', 'desactivado_at' => now()]);

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'ClaveSegura123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('desactivada', (string) session('errors')->first('email'));
    }

    public function test_un_usuario_activo_conserva_su_inicio_de_sesion(): void
    {
        $usuario = $this->usuario(['estado' => 'activo']);

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'ClaveSegura123'])
            ->assertRedirect(route('panel'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_recordarme_de_un_inactivo_no_deja_acceso_persistente(): void
    {
        $usuario = $this->usuario([
            'estado' => 'inactivo',
            'desactivado_at' => now(),
            'remember_token' => 'token-original',
        ]);

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'ClaveSegura123',
            'remember' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        // El intento rotó el remember_token: la cookie "recordarme" no es usable.
        $this->assertNotSame('token-original', $usuario->fresh()->remember_token);
    }

    // ------------------------------------------------- CA2: expulsión de sesión

    public function test_la_sesion_abierta_de_un_usuario_desactivado_se_expulsa(): void
    {
        $usuario = $this->usuario(['estado' => 'activo', 'remember_token' => 'token-original']);
        $this->actingAs($usuario);

        // Desactivación posterior con sesión ya abierta.
        $usuario->update(['estado' => 'inactivo', 'desactivado_at' => now()]);

        $this->get(route('panel'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame('token-original', $usuario->fresh()->remember_token);
    }

    // ------------------------------- CA3/CA4: desactivación contextual y guardas

    public function test_no_puede_desactivar_su_propia_cuenta_desde_el_contexto(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->identidadConGestion($organizacion, $copropiedad);

        $this->actingAsContextual($operador)
            ->patch(route('users.desactivar', $operador))
            ->assertStatus(422);

        $this->assertSame('activo', $operador->fresh()->estado);
    }

    public function test_no_se_desactiva_si_deja_a_una_copropiedad_sin_administrador_efectivo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->identidadConGestion($organizacion, $copropiedad);

        // El objetivo es el único titular de usuarios.gestionar en otra
        // Copropiedad. Como users.estado es global, la desactivación se bloquea.
        $otraOrganizacion = Organizacion::factory()->create(['estado' => 'activa']);
        $otraCopropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $otraOrganizacion->id,
            'estado' => 'activa',
        ]);
        $objetivo = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'auditor', []);
        $this->createContextualIdentity($objetivo, $otraOrganizacion, $otraCopropiedad, 'gestor', ['usuarios.gestionar']);

        $this->actingAsContextual($operador)
            ->patch(route('users.desactivar', $objetivo))
            ->assertStatus(422);

        $this->assertSame('activo', $objetivo->fresh()->estado);
        $this->assertSame('activa', MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('copropiedad_id', $otraCopropiedad->id)
            ->value('estado'));
    }

    public function test_desactivacion_contextual_suspende_membresias_con_motivo_y_audita(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->identidadConGestion($organizacion, $copropiedad);
        [$objetivo] = $this->identidadConGestion($organizacion, $copropiedad, 'gestor');

        $this->actingAsContextual($operador)
            ->patch(route('users.desactivar', $objetivo))
            ->assertRedirect();

        $this->assertSame('inactivo', $objetivo->fresh()->estado);
        $this->assertNotNull($objetivo->fresh()->desactivado_at);

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
        $this->assertSame('suspendida', $membresia->estado);
        $this->assertSame(DesactivarUsuarioGlobal::MOTIVO_SUSPENSION, $membresia->motivo_terminacion);

        $this->assertTrue(AuditLog::query()
            ->where('action', 'usuario.desactivar')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $objetivo->id)
            ->exists());
    }

    public function test_un_usuario_desactivado_no_cuenta_para_la_capacidad_administrativa(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->identidadConGestion($organizacion, $copropiedad);
        [$objetivo] = $this->identidadConGestion($organizacion, $copropiedad, 'gestor');

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->firstOrFail();
        $capacidad = app(CapacidadAdministrativa::class);
        $membresias = $capacidad->membresiasBloqueadas($membresia);
        $antes = $capacidad->titularesRestantes($membresias, $membresia);

        $this->actingAsContextual($operador)
            ->patch(route('users.desactivar', $objetivo))
            ->assertRedirect();

        // Se releen las Membresías tras la escritura (el estado vigente cambió).
        $membresias = $capacidad->membresiasBloqueadas($membresia);
        $this->assertSame($antes - 1, $capacidad->titularesRestantes($membresias, $membresia));
    }

    public function test_la_desactivacion_contextual_requiere_usuarios_gestionar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $objetivo = User::factory()->create();
        $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'residente', []);
        $intruso = User::factory()->create();
        $this->createContextualIdentity($intruso, $organizacion, $copropiedad, 'auditor', []);

        $this->actingAsContextual($intruso)
            ->patch(route('users.desactivar', $objetivo))
            ->assertForbidden();

        $this->assertSame('activo', $objetivo->fresh()->estado);
    }

    // ----------------------------- CA5/CA7: restauración selectiva al reactivar

    public function test_la_reactivacion_restaura_solo_las_membresias_suspendidas_por_desactivacion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador] = $this->identidadConGestion($organizacion, $copropiedad);

        $objetivo = User::factory()->create(['role' => 'residente']);
        [$membresiaA] = $this->createContextualIdentity($objetivo, $organizacion, $copropiedad, 'residente', []);

        $otraOrganizacion = Organizacion::factory()->create(['estado' => 'activa']);
        $otraCopropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $otraOrganizacion->id,
            'estado' => 'activa',
        ]);
        $copropiedadAntigua = Copropiedad::factory()->create([
            'organizacion_id' => $otraOrganizacion->id,
            'estado' => 'activa',
        ]);
        $this->createContextualIdentity($objetivo, $otraOrganizacion, $otraCopropiedad, 'residente', []);
        $this->createContextualIdentity($objetivo, $otraOrganizacion, $copropiedadAntigua, 'residente', []);

        $membresiaB = MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('copropiedad_id', $otraCopropiedad->id)
            ->firstOrFail();
        $membresiaB->update(['estado' => 'suspendida', 'motivo_terminacion' => 'Mudanza']);

        // Suspensión anterior a la regla, sin motivo demostrable: no se restaura.
        $membresiaC = MembresiaCopropiedad::query()
            ->where('usuario_id', $objetivo->id)
            ->where('copropiedad_id', $copropiedadAntigua->id)
            ->firstOrFail();
        $membresiaC->update(['estado' => 'suspendida', 'motivo_terminacion' => null]);

        $this->actingAsContextual($operador)
            ->patch(route('users.desactivar', $objetivo))
            ->assertRedirect();

        $this->assertSame('suspendida', $membresiaA->fresh()->estado);
        $this->assertSame(DesactivarUsuarioGlobal::MOTIVO_SUSPENSION, $membresiaA->fresh()->motivo_terminacion);

        $this->actingAsContextual($operador)
            ->patch(route('users.reactivar', $objetivo))
            ->assertRedirect();

        $this->assertSame('activo', $objetivo->fresh()->estado);
        $this->assertNull($objetivo->fresh()->desactivado_at);

        // Restaurada: la suspendida por la desactivación.
        $this->assertSame('activa', $membresiaA->fresh()->estado);
        $this->assertNull($membresiaA->fresh()->motivo_terminacion);

        // Conservadas: suspensiones por otros motivos y las sin motivo demostrable.
        $this->assertSame('suspendida', $membresiaB->fresh()->estado);
        $this->assertSame('Mudanza', $membresiaB->fresh()->motivo_terminacion);
        $this->assertSame('suspendida', $membresiaC->fresh()->estado);

        $this->assertTrue(AuditLog::query()
            ->where('action', 'usuario.activar')
            ->where('auditable_id', $objetivo->id)
            ->exists());
    }
}
