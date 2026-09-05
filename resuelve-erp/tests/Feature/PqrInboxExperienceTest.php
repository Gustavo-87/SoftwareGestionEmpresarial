<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrInboxExperienceTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_inbox_exposes_existing_filters_summary_and_authorized_quick_actions(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.crear', 'informes.exportar',
        ]);
        $type = TipoPqr::factory()->create(['nombre' => 'Queja']);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'asunto' => 'Caso visible en bandeja', 'tipo_pqr_id' => $type->id,
            'assigned_to_id' => $manager->id, 'estado' => 'radicada',
        ]);

        $this->actingAsContextual($manager)->get(route('pqrs.index'))
            ->assertOk()
            ->assertSee('Bandeja operativa')
            ->assertSee('Próximas a vencer')
            ->assertSee('Buscar por radicado, asunto o residente')
            ->assertSee('Acción rápida')
            ->assertSee('Caso visible en bandeja');
    }

    public function test_inbox_keeps_filter_values_when_paginating_and_reports_empty_filtered_results(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $type = TipoPqr::factory()->create();
        foreach (range(1, 11) as $number) {
            Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
                'asunto' => "Caso paginado {$number}", 'tipo_pqr_id' => $type->id, 'estado' => 'radicada',
            ]);
        }

        $this->actingAsContextual($manager)->get(route('pqrs.index', ['buscar' => 'Caso paginado']))
            ->assertOk()
            ->assertSee('Se muestran resultados con filtros activos.')
            ->assertSee('buscar=Caso%20paginado', false);

        $this->get(route('pqrs.index', ['buscar' => 'sin coincidencias']))
            ->assertOk()
            ->assertSee('No encontramos resultados con los filtros aplicados.')
            ->assertSee('Restablecer filtros');
    }

    public function test_resident_inbox_only_contains_own_requests_and_hides_management_quick_action(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $other = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.listar', 'pqrs.ver_propias']);
        $type = TipoPqr::factory()->create();
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['asunto' => 'Solicitud propia', 'user_id' => $resident->id, 'tipo_pqr_id' => $type->id]);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['asunto' => 'Solicitud ajena', 'user_id' => $other->id, 'tipo_pqr_id' => $type->id]);

        $this->actingAsContextual($resident)->get(route('pqrs.index'))
            ->assertOk()
            ->assertSee('Solicitud propia')
            ->assertDontSee('Solicitud ajena')
            ->assertDontSee('Acción rápida');
    }

    public function test_inbox_uses_neutral_past_due_language_for_completed_requests(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);

        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'asunto' => 'Solicitud respondida con plazo anterior',
            'estado' => 'respondida',
            'fecha_limite_respuesta' => now()->subDays(9)->toDateString(),
        ]);

        $this->actingAsContextual($manager)->get(route('pqrs.index'))
            ->assertOk()
            ->assertSee('Gestión finalizada')
            ->assertDontSee('Vence en -', false);
    }
}
