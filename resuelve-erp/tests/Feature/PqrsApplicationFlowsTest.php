<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\PresentarPqrs;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrsApplicationFlowsTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_presentation_preserves_its_http_contract_and_creates_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $this->actingAs($resident)->post(route('pqrs.store'), [
            'asunto' => 'Presentación correcta',
            'descripcion' => 'La solicitud conserva su contrato HTTP.',
            'fecha_radicacion' => now()->toDateString(),
            'tipo_pqr_id' => $tipo->id,
            'organizacion_id' => 999999,
            'copropiedad_id' => 999999,
            'user_id' => 999999,
        ])->assertRedirect()
            ->assertSessionHas('success', 'PQR radicada correctamente. Ya no puede ser modificada.');

        $pqr = Pqr::query()->where('asunto', 'Presentación correcta')->firstOrFail();
        $this->assertSame($resident->id, $pqr->user_id);
        $this->assertSame($organizacion->id, $pqr->organizacion_id);
        $this->assertSame($copropiedad->id, $pqr->copropiedad_id);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'created']);
    }

    public function test_update_accepts_a_current_local_assignee_and_creates_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'gestor']);
        $localAssignee = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($localAssignee, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $tipo = TipoPqr::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['tipo_pqr_id' => $tipo->id]);

        $this->actingAs($manager)->put(route('pqrs.update', $pqr), $this->updateData($pqr, $tipo, $localAssignee->id))
            ->assertRedirect(route('pqrs.index'))
            ->assertSessionHas('success', 'PQR actualizada correctamente.');

        $this->assertDatabaseHas('pqrs', ['id' => $pqr->id, 'assigned_to_id' => $localAssignee->id, 'estado' => 'en_revision']);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'updated']);
    }

    public function test_quick_action_preserves_its_http_contract_and_creates_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['estado' => 'radicada']);

        $this->actingAs($manager)->patch(route('pqrs.quick-update', $pqr), ['estado' => 'en_revision'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Solicitud actualizada.');

        $this->assertSame('en_revision', $pqr->fresh()->estado);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'quick_action']);
    }

    public function test_update_and_quick_action_reject_external_assignees_like_unknown_assignees(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [, $otraCopropiedad] = $this->createOtherContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $externalAssignee = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($externalAssignee, $otraCopropiedad->organizacion, $otraCopropiedad, 'gestor', ['pqrs.gestionar']);
        $tipo = TipoPqr::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['tipo_pqr_id' => $tipo->id]);

        $this->actingAs($manager)->put(route('pqrs.update', $pqr), $this->updateData($pqr, $tipo, $externalAssignee->id))
            ->assertRedirect()
            ->assertSessionHasErrors('assigned_to_id');
        $externalMessage = session('errors')->first('assigned_to_id');

        $this->from(route('pqrs.edit', $pqr))->put(route('pqrs.update', $pqr), $this->updateData($pqr, $tipo, 999999))
            ->assertRedirect()
            ->assertSessionHasErrors('assigned_to_id');
        $this->assertSame($externalMessage, session('errors')->first('assigned_to_id'));

        $this->patch(route('pqrs.quick-update', $pqr), ['assigned_to_id' => $externalAssignee->id])
            ->assertRedirect()
            ->assertSessionHasErrors('assigned_to_id');
        $this->assertDatabaseHas('pqrs', ['id' => $pqr->id, 'assigned_to_id' => null]);
        $this->assertDatabaseCount('pqr_activities', 0);
    }

    public function test_presentation_rolls_back_when_an_attachment_cannot_be_stored(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $resident->id);
        $tipo = TipoPqr::factory()->create();
        $adjuntoFallido = new class {
            public function store(string $path): never
            {
                throw new RuntimeException('No se pudo almacenar el adjunto.');
            }
        };

        try {
            app(PresentarPqrs::class)->ejecutar($contexto, $resident, [
                'asunto' => 'Debe revertirse',
                'descripcion' => 'La transacción debe revertir todos los cambios.',
                'fecha_radicacion' => now()->toDateString(),
                'tipo_pqr_id' => $tipo->id,
            ], [$adjuntoFallido]);
            $this->fail('La presentación debía fallar al almacenar el adjunto.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo almacenar el adjunto.', $exception->getMessage());
        }

        $this->assertDatabaseCount('pqrs', 0);
        $this->assertDatabaseCount('pqr_activities', 0);
    }

    private function updateData(Pqr $pqr, TipoPqr $tipo, int $assignedToId): array
    {
        return [
            'asunto' => 'Solicitud actualizada',
            'descripcion' => 'Descripción actualizada.',
            'fecha_radicacion' => $pqr->fecha_radicacion->toDateString(),
            'fecha_limite_respuesta' => $pqr->fecha_limite_respuesta?->toDateString(),
            'estado' => 'en_revision',
            'tipo_pqr_id' => $tipo->id,
            'assigned_to_id' => $assignedToId,
        ];
    }

    /** @return array{Organizacion, Copropiedad} */
    private function createOtherContext(): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Organización externa', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad externa',
            'estado' => 'activa',
        ]);

        return [$organizacion, $copropiedad];
    }
}
