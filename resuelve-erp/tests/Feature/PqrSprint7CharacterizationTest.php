<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrSprint7CharacterizationTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_presentation_uses_the_active_context_and_preserves_attachments_and_history(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        Notification::fake();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $this->actingAs($resident)->post(route('pqrs.store'), [
            'asunto' => 'Solicitud caracterizada',
            'descripcion' => 'Descripción de la presentación caracterizada.',
            'fecha_radicacion' => now()->toDateString(),
            'tipo_pqr_id' => $tipo->id,
            'adjuntos' => [UploadedFile::fake()->image('soporte.jpg')],
        ])->assertRedirect();

        $pqr = Pqr::query()->where('asunto', 'Solicitud caracterizada')->firstOrFail();
        $attachment = $pqr->attachments()->firstOrFail();
        $this->assertSame($resident->id, $pqr->user_id);
        $this->assertSame($organizacion->id, $pqr->organizacion_id);
        $this->assertSame($copropiedad->id, $pqr->copropiedad_id);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'created']);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_editing_rejects_an_assignee_from_another_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [, $otraCopropiedad] = $this->createOtherContext();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'gestor']);
        $externalAssignee = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($externalAssignee, $otraCopropiedad->organizacion, $otraCopropiedad, 'gestor', ['pqrs.gestionar']);
        $tipo = TipoPqr::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['tipo_pqr_id' => $tipo->id]);

        $this->actingAs($manager)->put(route('pqrs.update', $pqr), [
            'asunto' => 'Solicitud editada',
            'descripcion' => 'Descripción editada.',
            'fecha_radicacion' => $pqr->fecha_radicacion->toDateString(),
            'fecha_limite_respuesta' => $pqr->fecha_limite_respuesta?->toDateString(),
            'estado' => 'en_revision',
            'tipo_pqr_id' => $tipo->id,
            'assigned_to_id' => $externalAssignee->id,
        ])->assertRedirect()
            ->assertSessionHasErrors('assigned_to_id');

        $this->assertDatabaseHas('pqrs', [
            'id' => $pqr->id,
            'asunto' => $pqr->asunto,
            'assigned_to_id' => null,
        ]);
        $this->assertDatabaseCount('pqr_activities', 0);
    }

    public function test_quick_action_updates_the_pqr_and_records_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['estado' => 'radicada']);

        $this->actingAs($manager)->patch(route('pqrs.quick-update', $pqr), [
            'estado' => 'en_revision',
            'assigned_to_id' => $manager->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('pqrs', ['id' => $pqr->id, 'estado' => 'en_revision', 'assigned_to_id' => $manager->id]);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'quick_action']);
    }

    public function test_reply_and_internal_comment_keep_their_current_persistence_contracts(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'gestor']);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $resident->id, 'estado' => 'radicada']);

        $this->actingAs($manager)->post(route('pqrs.replies.store', $pqr), [
            'body' => 'Respuesta enviada al residente.',
            'action' => 'send',
            'send_reply_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->post(route('pqrs.comments.store', $pqr), [
            'body' => 'Comentario interno caracterizado.',
        ])->assertRedirect();

        $this->assertDatabaseHas('pqr_replies', ['pqr_id' => $pqr->id, 'is_draft' => false]);
        $this->assertSame('respondida', $pqr->fresh()->estado);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'sent_reply']);
        $this->assertDatabaseHas('pqr_internal_comments', ['pqr_id' => $pqr->id, 'body' => 'Comentario interno caracterizado.']);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'internal_comment']);
    }

    public function test_tags_accept_local_resources_and_reject_an_external_tag(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $localTag = PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create();
        $externalTag = PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->actingAs($manager)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$localTag->id]])
            ->assertRedirect();
        $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $localTag->id]);

        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$externalTag->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('tags.0');
        $this->assertDatabaseMissing('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $externalTag->id]);
    }

    public function test_external_pqrs_are_not_found_for_each_mutating_pqrs_flow(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $tipo = TipoPqr::factory()->create();
        $externalPqr = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create(['tipo_pqr_id' => $tipo->id]);

        $this->actingAs($manager)->put(route('pqrs.update', $externalPqr), $this->updatePayload($externalPqr, $tipo))
            ->assertNotFound();
        $this->post(route('pqrs.replies.store', $externalPqr), ['body' => 'No debe guardarse.', 'action' => 'draft'])
            ->assertNotFound();
        $this->post(route('pqrs.comments.store', $externalPqr), ['body' => 'No debe guardarse.'])
            ->assertNotFound();
        $this->patch(route('pqrs.quick-update', $externalPqr), ['estado' => 'cerrada'])
            ->assertNotFound();
        $this->patch(route('pqrs.tags.sync', $externalPqr), ['tags' => []])
            ->assertNotFound();

        $this->assertDatabaseCount('pqr_replies', 0);
        $this->assertDatabaseCount('pqr_internal_comments', 0);
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

    private function updatePayload(Pqr $pqr, TipoPqr $tipo): array
    {
        return [
            'asunto' => $pqr->asunto,
            'descripcion' => $pqr->descripcion,
            'fecha_radicacion' => $pqr->fecha_radicacion->toDateString(),
            'fecha_limite_respuesta' => $pqr->fecha_limite_respuesta?->toDateString(),
            'estado' => $pqr->estado,
            'tipo_pqr_id' => $tipo->id,
        ];
    }
}
