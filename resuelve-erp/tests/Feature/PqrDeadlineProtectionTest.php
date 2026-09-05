<?php

namespace Tests\Feature;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrDeadlineProtectionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_creation_ignores_manipulated_dates_and_uses_contextual_business_days_in_bogota(): void
    {
        Carbon::setTestNow('2026-08-13 03:30:00 UTC');
        [$org, $cop] = $this->createInstitutionalContext();
        ConfiguracionCopropiedad::create(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'dias_respuesta' => 1]);
        Notification::fake();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $this->actingAsContextual($resident)->post(route('pqrs.store'), [
            'asunto' => 'Fechas protegidas', 'descripcion' => 'Payload manipulado.', 'tipo_pqr_id' => $tipo->id,
            'fecha_radicacion' => '1999-01-01', 'fecha_limite_respuesta' => '2099-12-31',
        ])->assertRedirect();

        $pqr = Pqr::query()->sole();
        $this->assertSame('2026-08-12', $pqr->fecha_radicacion->toDateString());
        $this->assertSame('2026-08-13', $pqr->fecha_limite_respuesta->toDateString());
    }

    public function test_contextual_settings_differ_and_missing_settings_use_official_default(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00 America/Bogota');
        [$orgA, $copA] = $this->createInstitutionalContext();
        [$orgB, $copB] = $this->createInstitutionalContext();
        ConfiguracionCopropiedad::create(['organizacion_id' => $orgA->id, 'copropiedad_id' => $copA->id, 'dias_respuesta' => 1]);
        $tipo = TipoPqr::factory()->create();

        foreach ([[$orgA, $copA, 'uno'], [$orgB, $copB, 'predeterminado']] as [$org, $cop, $asunto]) {
            $resident = User::factory()->create(['role' => 'residente']);
            $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.crear']);
            $contexto = app(\App\Application\Contexto\ContextResolver::class)->resolverExplicito($org->id, $cop->id, $resident->id);
            app(\App\Application\Pqrs\PresentarPqrs::class)->ejecutar($contexto, $resident, ['asunto' => $asunto, 'descripcion' => 'Plazo contextual.', 'tipo_pqr_id' => $tipo->id]);
        }

        $this->assertSame('2026-08-13', Pqr::where('asunto', 'uno')->sole()->fecha_limite_respuesta->toDateString());
        $this->assertSame('2026-09-03', Pqr::where('asunto', 'predeterminado')->sole()->fecha_limite_respuesta->toDateString());
    }

    public function test_edit_is_readonly_and_manipulated_update_preserves_dates_while_updating_management_fields(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $assignee = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($assignee, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $tipo = TipoPqr::factory()->create();
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['tipo_pqr_id' => $tipo->id, 'fecha_radicacion' => '2025-01-10', 'fecha_limite_respuesta' => '2025-02-03']);

        $this->actingAsContextual($manager)->get(route('pqrs.edit', $pqr))
            ->assertOk()->assertSee('Fechas de la radicación')->assertDontSee('name="fecha_radicacion"', false)->assertDontSee('name="fecha_limite_respuesta"', false);
        $this->put(route('pqrs.update', $pqr), [
            'asunto' => $pqr->asunto, 'descripcion' => $pqr->descripcion, 'tipo_pqr_id' => $tipo->id,
            'estado' => 'en_revision', 'assigned_to_id' => $assignee->id,
            'fecha_radicacion' => '2030-01-01', 'fecha_limite_respuesta' => '2030-12-31',
        ])->assertRedirect(route('pqrs.index'));

        $pqr->refresh();
        $this->assertSame('2025-01-10', $pqr->fecha_radicacion->toDateString());
        $this->assertSame('2025-02-03', $pqr->fecha_limite_respuesta->toDateString());
        $this->assertSame('en_revision', $pqr->estado);
        $this->assertSame($assignee->id, $pqr->assigned_to_id);
    }

    public function test_unauthorized_submission_is_forbidden_before_validation_details(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $org, $cop, 'residente', []);

        $this->actingAsContextual($resident)->post(route('pqrs.store'), [])->assertForbidden()->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('pqrs', 0);
    }
}
