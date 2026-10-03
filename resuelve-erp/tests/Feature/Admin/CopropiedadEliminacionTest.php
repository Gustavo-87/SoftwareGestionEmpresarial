<?php

namespace Tests\Feature\Admin;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Documento;
use App\Models\Mantenimiento;
use App\Models\MembresiaCopropiedad;
use App\Models\Pqr;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Sprint 17 — Eliminación segura de Copropiedades: solo autoridad de
 * plataforma, solo sin información operativa, con limpieza de auxiliares,
 * auditoría y sin destrucción de datos relacionados.
 */
class CopropiedadEliminacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function plataforma(): User
    {
        return User::factory()->create(['es_administrador_sistema' => true]);
    }

    public function test_eliminacion_de_copropiedad_vacia_limpia_auxiliares_y_audita(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $config = ConfiguracionCopropiedad::forceCreate([
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
        ]);
        $plataforma = $this->plataforma();

        $this->actingAs($plataforma)
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertRedirect(route('admin.copropiedades.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('copropiedades', ['id' => $copropiedad->id]);
        $this->assertDatabaseMissing('configuraciones_copropiedad', ['id' => $config->id]);
        $this->assertDatabaseMissing('site_settings', [
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $plataforma->id, 'action' => 'copropiedad.delete',
            'auditable_type' => \App\Models\Copropiedad::class, 'auditable_id' => $copropiedad->id,
        ]);
    }

    public function test_bloquea_con_pqr_sin_eliminar_datos_relacionados(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();

        $this->actingAs($this->plataforma())
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertSessionHasErrors('copropiedad');

        $this->assertStringContainsString('PQRS', session('errors')->first('copropiedad'));
        $this->assertDatabaseHas('copropiedades', ['id' => $copropiedad->id]);
        $this->assertDatabaseHas('pqrs', ['id' => $pqr->id]);
        // Los auxiliares tampoco se eliminan cuando hay datos operativos.
        $this->assertDatabaseHas('site_settings', [
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
        ]);
    }

    public function test_bloquea_con_documentos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $documento = Documento::forceCreate([
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
            'ambito' => 'copropiedad', 'propietario_documental_user_id' => User::factory()->create()->id,
            'tipo' => 'reglamento', 'categoria' => 'normativo', 'titulo' => 'Doc bloqueante',
            'nivel_acceso' => 'comunidad', 'estado' => 'activo',
        ]);

        $this->actingAs($this->plataforma())
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertSessionHasErrors('copropiedad');

        $this->assertStringContainsString('documentos', session('errors')->first('copropiedad'));
        $this->assertDatabaseHas('copropiedades', ['id' => $copropiedad->id]);
        $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
    }

    public function test_bloquea_con_mantenimiento(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $mantenimiento = Mantenimiento::forceCreate([
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
            'solicitante_id' => User::factory()->create()->id,
            'titulo' => 'Mantenimiento bloqueante', 'descripcion' => 'x',
        ]);

        $this->actingAs($this->plataforma())
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertSessionHasErrors('copropiedad');

        $this->assertStringContainsString('mantenimientos', session('errors')->first('copropiedad'));
        $this->assertDatabaseHas('mantenimientos', ['id' => $mantenimiento->id]);
    }

    public function test_bloquea_con_unidades_y_membresias(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $unidad = UnidadPrivada::forceCreate([
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id, 'codigo' => 'UNI-1',
        ]);
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => User::factory()->create()->id,
            'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa', 'vigente_desde' => now()->subMinute(),
        ]);

        $this->actingAs($this->plataforma())
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertSessionHasErrors('copropiedad');

        $mensaje = session('errors')->first('copropiedad');
        $this->assertStringContainsString('unidades privadas', $mensaje);
        $this->assertStringContainsString('membresías', $mensaje);
        $this->assertDatabaseHas('unidades_privadas', ['id' => $unidad->id]);
        $this->assertDatabaseHas('membresias_copropiedad', ['id' => $membresia->id]);
    }

    public function test_403_sin_autoridad_de_plataforma(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $comun = User::factory()->create(['es_administrador_sistema' => false]);

        $this->actingAs($comun)
            ->delete(route('admin.copropiedades.destroy', $copropiedad))
            ->assertForbidden();

        $this->assertDatabaseHas('copropiedades', ['id' => $copropiedad->id]);
    }
}
