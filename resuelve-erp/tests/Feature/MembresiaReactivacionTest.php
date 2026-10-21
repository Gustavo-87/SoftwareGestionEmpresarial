<?php

namespace Tests\Feature;

use App\Application\Membresias\FinalizarMembresia;
use App\Application\Membresias\ReactivarMembresia;
use App\Application\Membresias\SuspenderMembresia;
use App\Application\Usuarios\DesactivarUsuarioGlobal;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Reactivación de Membresías suspendidas (decisión del Product Owner,
 * 21 de octubre de 2026): suspendidas manualmente y por desactivación de la
 * cuenta; nunca finalizadas ni vencidas, con motivos concretos de bloqueo.
 */
class MembresiaReactivacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_una_suspension_manual_se_reactiva_restituyendo_la_vigencia_anterior(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => now()->addDays(30),
        ]);

        // La suspensión manual corta la vigencia (reproducción del caso 1).
        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.suspend', $membresia), ['motivo' => 'Licencia administrativa'])
            ->assertRedirect(route('admin.membresias.show', $membresia));
        $this->assertSame('suspendida', $membresia->fresh()->estado);
        $this->assertFalse($membresia->fresh()->vigente_hasta->isFuture());

        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertRedirect(route('admin.membresias.show', $membresia))
            ->assertSessionHas('success');

        $reactivada = $membresia->fresh();
        $this->assertSame('activa', $reactivada->estado);
        $this->assertNull($reactivada->motivo_terminacion);
        $this->assertTrue($reactivada->vigente_hasta->isFuture());
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $reactivada->vigente_hasta->timestamp, 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membresia.reactivate', 'auditable_id' => $membresia->id]);
    }

    public function test_una_suspension_manual_sin_auditoria_reabre_la_vigencia_indefinida(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
        ]);

        app(SuspenderMembresia::class)->ejecutar($membresia, 'Motivo previo a la regla');
        app(ReactivarMembresia::class)->ejecutar($membresia->fresh());

        $this->assertSame('activa', $membresia->fresh()->estado);
        $this->assertNull($membresia->fresh()->vigente_hasta);
    }

    public function test_la_suspension_por_desactivacion_se_reactiva_con_cuenta_activa(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => now()->addDays(10),
        ]);

        app(DesactivarUsuarioGlobal::class)->ejecutar($user, $plataforma->id);
        $suspendida = $membresia->fresh();
        $this->assertSame('suspendida', $suspendida->estado);
        $this->assertSame(DesactivarUsuarioGlobal::MOTIVO_SUSPENSION, $suspendida->motivo_terminacion);

        // Con el usuario desactivado la membresía no puede reactivarse.
        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertRedirect()
            ->assertSessionHasErrors('estado');
        $this->assertStringContainsString('el usuario está desactivado', session('errors')->first('estado'));

        // La reactivación de la cuenta restaura esta suspensión (regla HU-USR-08).
        app(DesactivarUsuarioGlobal::class)->reactivar($user);
        $restaurada = $membresia->fresh();
        $this->assertSame('activa', $restaurada->estado);
        $this->assertNull($restaurada->motivo_terminacion);
        $this->assertTrue($restaurada->vigente_hasta->isFuture());
    }

    public function test_una_suspension_por_desactivacion_tambien_se_puede_reactivar_manualmente(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => now()->addDays(10),
        ]);

        app(DesactivarUsuarioGlobal::class)->ejecutar($user, $plataforma->id);
        app(DesactivarUsuarioGlobal::class)->reactivar($user);

        // La membresía quedó suspendida y el usuario ya está activo.
        $membresia->update(['estado' => 'suspendida', 'motivo_terminacion' => DesactivarUsuarioGlobal::MOTIVO_SUSPENSION]);

        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertRedirect(route('admin.membresias.show', $membresia))
            ->assertSessionHas('success');

        $this->assertSame('activa', $membresia->fresh()->estado);
        $this->assertTrue($membresia->fresh()->vigente_hasta->isFuture());
    }

    public function test_no_se_reactiva_una_membresia_finalizada_y_se_explica_el_motivo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
        ]);

        app(FinalizarMembresia::class)->ejecutar($membresia, 'Fin de contrato');

        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertRedirect()
            ->assertSessionHasErrors('estado');
        $motivo = session('errors')->first('estado');
        $this->assertStringContainsString('está finalizada', $motivo);
        $this->assertStringContainsString('Fin de contrato', $motivo);
        $this->assertSame('finalizada', $membresia->fresh()->estado);
    }

    public function test_no_se_convierte_en_activa_una_membresia_vencida(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDays(60),
            'vigente_hasta' => now()->subDays(10),
        ]);

        app(DesactivarUsuarioGlobal::class)->ejecutar($user, $plataforma->id);
        app(DesactivarUsuarioGlobal::class)->reactivar($user);
        $membresia->update(['estado' => 'suspendida', 'motivo_terminacion' => DesactivarUsuarioGlobal::MOTIVO_SUSPENSION]);

        $this->actingAs($plataforma)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertRedirect()
            ->assertSessionHasErrors('estado');
        $this->assertStringContainsString('la vigencia de la membresía finalizó', session('errors')->first('estado'));
        $this->assertSame('suspendida', $membresia->fresh()->estado);
    }

    public function test_la_reactivacion_valida_organizacion_copropiedad_y_estado_del_usuario(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
        ]);
        app(SuspenderMembresia::class)->ejecutar($membresia, 'Motivo de prueba');
        $membresia = $membresia->fresh();

        $copropiedad->update(['estado' => 'inactiva']);
        try {
            app(ReactivarMembresia::class)->ejecutar($membresia);
            $this->fail('Debía bloquearse por Copropiedad inactiva.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('la Copropiedad', $e->getMessage());
            $this->assertStringContainsString('está inactiva', $e->getMessage());
        }
        $copropiedad->update(['estado' => 'activa']);

        $organizacion->update(['estado' => 'inactiva']);
        try {
            app(ReactivarMembresia::class)->ejecutar($membresia->fresh());
            $this->fail('Debía bloquearse por Organización inactiva.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('la Organización', $e->getMessage());
        }
        $organizacion->update(['estado' => 'activa']);

        $user->update(['estado' => 'inactivo']);
        try {
            app(ReactivarMembresia::class)->ejecutar($membresia->fresh());
            $this->fail('Debía bloquearse por usuario desactivado.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('el usuario está desactivado', $e->getMessage());
        }
    }

    public function test_la_reactivacion_conserva_los_roles_asignados(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        [$membresia] = $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar']);
        $asignacionesAntes = \Illuminate\Support\Facades\DB::table('model_has_roles')->count();

        app(SuspenderMembresia::class)->ejecutar($membresia, 'Motivo de prueba');
        app(ReactivarMembresia::class)->ejecutar($membresia->fresh());

        $this->assertSame('activa', $membresia->fresh()->estado);
        $this->assertSame($asignacionesAntes, \Illuminate\Support\Facades\DB::table('model_has_roles')->count());
    }

    public function test_la_reactivacion_es_solo_para_autoridad_de_plataforma_y_muestra_la_accion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
        ]);
        app(SuspenderMembresia::class)->ejecutar($membresia, 'Motivo de prueba');

        $sinAutoridad = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($sinAutoridad, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $this->actingAs($sinAutoridad)
            ->patch(route('admin.membresias.reactivate', $membresia))
            ->assertForbidden();

        $this->actingAs($plataforma)
            ->get(route('admin.membresias.show', $membresia))
            ->assertOk()
            ->assertSee('Reactivar membresía')
            ->assertSee('Motivo de prueba');

        app(FinalizarMembresia::class)->ejecutar($membresia->fresh(), 'Fin de contrato');
        $this->actingAs($plataforma)
            ->get(route('admin.membresias.show', $membresia))
            ->assertOk()
            ->assertDontSee('Reactivar membresía')
            ->assertSee('no permite reactivar membresías finalizadas')
            ->assertSee('Fin de contrato');
    }

    public function test_la_reactivacion_de_la_cuenta_no_restaura_suspensiones_manuales(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $user = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subDay(),
        ]);

        app(SuspenderMembresia::class)->ejecutar($membresia, 'Suspensión manual');
        app(DesactivarUsuarioGlobal::class)->ejecutar($user, $plataforma->id);
        app(DesactivarUsuarioGlobal::class)->reactivar($user);

        $conservada = $membresia->fresh();
        $this->assertSame('suspendida', $conservada->estado);
        $this->assertSame('Suspensión manual', $conservada->motivo_terminacion);
    }
}
