<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ContextualizarUnidadesYPersonasCommandTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_creates_people_and_units_but_omits_links_without_an_inferred_type(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create(['tower' => 'Torre A', 'unit' => '101']);

        $this->artisan('resuelve:contextualizar-unidades-y-personas')
            ->expectsOutput('Personas creadas: 1')
            ->expectsOutput('Unidades creadas: 1')
            ->expectsOutput('Vínculos omitidos sin tipo: 1')
            ->expectsOutput('Inconsistencias: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('personas', ['organizacion_id' => $organizacion->id, 'usuario_id' => $usuario->id, 'nombre_razon_social' => $usuario->name]);
        $this->assertDatabaseHas('unidades_privadas', ['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id, 'codigo' => 'Torre A-101']);
        $this->assertDatabaseCount('vinculos_unidad', 0);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $this->createInstitutionalContext();
        User::factory()->create(['tower' => 'A', 'unit' => '101']);

        $this->artisan('resuelve:contextualizar-unidades-y-personas --dry-run')
            ->expectsOutput('Personas creadas: 1')
            ->expectsOutput('Unidades creadas: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('personas', 0);
        $this->assertDatabaseCount('unidades_privadas', 0);
    }

    public function test_second_execution_is_idempotent_for_people_and_units(): void
    {
        $this->createInstitutionalContext();
        User::factory()->create(['tower' => 'A', 'unit' => '101']);

        $this->artisan('resuelve:contextualizar-unidades-y-personas')->assertSuccessful();
        $personas = Persona::query()->get()->toArray();
        $unidades = UnidadPrivada::query()->get()->toArray();

        $this->artisan('resuelve:contextualizar-unidades-y-personas')
            ->expectsOutput('Personas existentes: 1')
            ->expectsOutput('Unidades existentes: 1')
            ->assertSuccessful();

        $this->assertSame($personas, Persona::query()->get()->toArray());
        $this->assertSame($unidades, UnidadPrivada::query()->get()->toArray());
    }

    public function test_records_an_inconsistency_when_a_legacy_user_has_no_unit(): void
    {
        $this->createInstitutionalContext();
        $usuario = User::factory()->create(['tower' => 'A', 'unit' => null]);

        $this->artisan('resuelve:contextualizar-unidades-y-personas')
            ->expectsOutputToContain("Usuario {$usuario->id}: la unidad legada es obligatoria.")
            ->expectsOutput('Inconsistencias: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('personas', 0);
        $this->assertDatabaseCount('unidades_privadas', 0);
    }
}
