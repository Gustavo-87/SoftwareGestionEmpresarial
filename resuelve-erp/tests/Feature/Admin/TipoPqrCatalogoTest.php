<?php

namespace Tests\Feature\Admin;

use App\Models\AutomationRule;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Administración global del catálogo de Tipos de PQRS: CRUD con validaciones,
 * eliminación protegida si está en uso, auditoría y solo autoridad de plataforma.
 */
class TipoPqrCatalogoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function plataforma(): User
    {
        return User::factory()->create(['es_administrador_sistema' => true]);
    }

    public function test_ciclo_completo_con_validaciones_y_auditoria(): void
    {
        $plataforma = $this->plataforma();

        $this->actingAs($plataforma)->get(route('admin.tipos-pqr.index'))
            ->assertOk()->assertSee('Nuevo tipo');

        $this->post(route('admin.tipos-pqr.store'), ['nombre' => 'Petición', 'descripcion' => 'Solicitud general'])
            ->assertRedirect(route('admin.tipos-pqr.index'))->assertSessionHas('success');
        $tipo = TipoPqr::where('nombre', 'Petición')->firstOrFail();
        $this->assertSame('Solicitud general', $tipo->descripcion);

        // Nombre obligatorio y único.
        $this->post(route('admin.tipos-pqr.store'), ['descripcion' => 'sin nombre'])
            ->assertSessionHasErrors('nombre');
        $this->post(route('admin.tipos-pqr.store'), ['nombre' => 'Petición'])
            ->assertSessionHasErrors('nombre');

        // Edición con unicidad excluyéndose a sí mismo.
        $this->put(route('admin.tipos-pqr.update', $tipo), ['nombre' => 'Petición especial'])
            ->assertRedirect(route('admin.tipos-pqr.index'))->assertSessionHas('success');
        $this->assertSame('Petición especial', $tipo->fresh()->nombre);
        $otro = TipoPqr::create(['nombre' => 'Queja']);
        $this->put(route('admin.tipos-pqr.update', $tipo), ['nombre' => 'Queja'])
            ->assertSessionHasErrors('nombre');

        // Eliminación segura del tipo sin uso.
        $this->delete(route('admin.tipos-pqr.destroy', $otro))
            ->assertRedirect(route('admin.tipos-pqr.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('tipo_pqrs', ['id' => $otro->id]);

        foreach (['tipo_pqr.store', 'tipo_pqr.update', 'tipo_pqr.delete'] as $accion) {
            $this->assertDatabaseHas('audit_logs', ['user_id' => $plataforma->id, 'action' => $accion]);
        }
    }

    public function test_eliminacion_bloqueada_si_una_pqrs_lo_utiliza(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $tipo = TipoPqr::create(['nombre' => 'Reclamo']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['tipo_pqr_id' => $tipo->id]);

        $this->actingAs($this->plataforma())
            ->delete(route('admin.tipos-pqr.destroy', $tipo))
            ->assertSessionHasErrors('tipo');

        $this->assertStringContainsString('en uso', session('errors')->first('tipo'));
        $this->assertDatabaseHas('tipo_pqrs', ['id' => $tipo->id]);
        $this->assertDatabaseHas('pqrs', ['id' => $pqr->id]);
    }

    public function test_eliminacion_bloqueada_si_una_regla_automatica_lo_utiliza(): void
    {
        $tipo = TipoPqr::create(['nombre' => 'Sugerencia']);
        AutomationRule::create(['name' => 'Regla con tipo', 'tipo_pqr_id' => $tipo->id]);

        $this->actingAs($this->plataforma())
            ->delete(route('admin.tipos-pqr.destroy', $tipo))
            ->assertSessionHasErrors('tipo');

        $this->assertStringContainsString('reglas automáticas', session('errors')->first('tipo'));
        $this->assertDatabaseHas('tipo_pqrs', ['id' => $tipo->id]);
    }

    public function test_403_sin_autoridad_de_plataforma(): void
    {
        $comun = User::factory()->create(['es_administrador_sistema' => false]);

        $this->actingAs($comun)->get(route('admin.tipos-pqr.index'))->assertForbidden();
        $this->post(route('admin.tipos-pqr.store'), ['nombre' => 'Bloqueado'])->assertForbidden();
        $this->assertDatabaseMissing('tipo_pqrs', ['nombre' => 'Bloqueado']);
    }
}
