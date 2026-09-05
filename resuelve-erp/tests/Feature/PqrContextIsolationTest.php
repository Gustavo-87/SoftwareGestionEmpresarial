<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrAttachment;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrContextIsolationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_index_filters_and_pagination_only_use_the_active_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', [
            'pqrs.listar',
            'pqrs.ver_todas',
        ]);

        foreach (range(1, 12) as $number) {
            Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
                'asunto' => "Activa {$number}",
                'estado' => $number === 12 ? 'respondida' : 'radicada',
            ]);
        }
        Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'asunto' => 'Caso de otro tenant',
            'estado' => 'respondida',
        ]);

        $firstPage = $this->actingAs($admin)->get(route('pqrs.index'));
        $firstPage->assertOk()->assertDontSee('Caso de otro tenant');
        $this->assertSame(10, $firstPage->viewData('pqrs')->count());
        $this->assertSame(12, $firstPage->viewData('pqrs')->total());

        $secondPage = $this->get(route('pqrs.index', ['page' => 2]));
        $secondPage->assertOk()->assertDontSee('Caso de otro tenant');
        $this->assertSame(2, $secondPage->viewData('pqrs')->count());

        $filtered = $this->get(route('pqrs.index', [
            'estado' => 'respondida',
            'organizacion_id' => $otraOrganizacion->id,
            'copropiedad_id' => $otraCopropiedad->id,
        ]));
        $filtered->assertOk()->assertSee('Activa 12')->assertDontSee('Caso de otro tenant');
        $this->assertSame(1, $filtered->viewData('pqrs')->total());
    }

    public function test_show_uses_contextual_binding_and_hides_other_tenant_like_a_missing_id(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['pqrs.ver_todas']);
        $local = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $foreign = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->actingAs($admin)->get(route('pqrs.show', $local))->assertOk();
        $foreignResponse = $this->get(route('pqrs.show', $foreign));
        $missingResponse = $this->get('/pqrs/999999999');

        $foreignResponse->assertNotFound();
        $missingResponse->assertNotFound();
        $this->assertSame($missingResponse->getContent(), $foreignResponse->getContent());
    }

    public function test_edit_update_and_destroy_reject_a_pqr_from_another_context(): void
    {
        $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $tipo = TipoPqr::factory()->create();
        $foreign = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'tipo_pqr_id' => $tipo->id,
        ]);

        $this->actingAs($admin)->get(route('pqrs.edit', $foreign))->assertNotFound();
        $this->put(route('pqrs.update', $foreign), [
            'asunto' => 'No debe cambiar',
            'descripcion' => $foreign->descripcion,
            'fecha_radicacion' => $foreign->fecha_radicacion->toDateString(),
            'fecha_limite_respuesta' => $foreign->fecha_limite_respuesta?->toDateString(),
            'estado' => 'cerrada',
            'tipo_pqr_id' => $tipo->id,
        ])->assertNotFound();
        $this->delete(route('pqrs.destroy', $foreign))->assertNotFound();

        $this->assertDatabaseHas('pqrs', [
            'id' => $foreign->id,
            'asunto' => $foreign->asunto,
            'estado' => $foreign->estado,
        ]);
    }

    public function test_related_actions_and_attachment_download_reject_another_context(): void
    {
        $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $foreign = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();
        $attachment = PqrAttachment::create([
            'pqr_id' => $foreign->id,
            'original_name' => 'privado.txt',
            'path' => 'pqrs/privado.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
        ]);

        $this->actingAs($admin)->post(route('pqrs.replies.store', $foreign), [
            'body' => 'No debe persistir',
            'action' => 'draft',
        ])->assertNotFound();
        $this->post(route('pqrs.comments.store', $foreign), [
            'body' => 'No debe persistir',
        ])->assertNotFound();
        $this->patch(route('pqrs.quick-update', $foreign), [
            'estado' => 'cerrada',
        ])->assertNotFound();
        $this->patch(route('pqrs.tags.sync', $foreign), [
            'tags' => [],
        ])->assertNotFound();
        $this->post(route('pqrs.survey.store', $foreign), [
            'rating' => 5,
        ])->assertNotFound();
        $this->get(route('attachments.download', $attachment))->assertNotFound();

        $this->assertDatabaseCount('pqr_replies', 0);
        $this->assertDatabaseCount('pqr_internal_comments', 0);
    }

    public function test_resident_only_lists_their_own_pqrs_inside_the_active_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $other = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar',
            'pqrs.ver_propias',
        ]);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $resident->id,
            'asunto' => 'Propia activa',
        ]);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $other->id,
            'asunto' => 'Ajena activa',
        ]);
        Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'user_id' => $resident->id,
            'asunto' => 'Propia en otro tenant',
        ]);

        $this->actingAs($resident)->get(route('pqrs.index'))
            ->assertOk()
            ->assertSee('Propia activa')
            ->assertDontSee('Ajena activa')
            ->assertDontSee('Propia en otro tenant');
    }

    /** @return array{Organizacion, Copropiedad} */
    private function createOtherContext(): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Otra Organización', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Otra Copropiedad',
            'estado' => 'activa',
        ]);

        return [$organizacion, $copropiedad];
    }
}
