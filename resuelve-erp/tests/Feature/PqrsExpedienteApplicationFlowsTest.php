<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\RegistrarRespuestaPqrs;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrsExpedienteApplicationFlowsTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_draft_reply_preserves_its_http_contract_and_records_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = $this->manager($organizacion, $copropiedad);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['estado' => 'radicada']);

        $this->actingAs($manager)->post(route('pqrs.replies.store', $pqr), [
            'body' => 'Borrador de respuesta.',
            'action' => 'draft',
            'create_draft_key' => (string) Str::uuid(),
        ])->assertRedirect()
            ->assertSessionHas('success', 'Borrador guardado.');

        $this->assertDatabaseHas('pqr_replies', ['pqr_id' => $pqr->id, 'body' => 'Borrador de respuesta.', 'is_draft' => true]);
        $this->assertSame('radicada', $pqr->fresh()->estado);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'drafted_reply']);
    }

    public function test_sent_reply_stores_attachments_marks_the_pqr_as_answered_and_records_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        Notification::fake();
        $manager = $this->manager($organizacion, $copropiedad);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['estado' => 'en_revision']);

        $this->actingAs($manager)->post(route('pqrs.replies.store', $pqr), [
            'body' => 'Respuesta final con soporte.',
            'action' => 'send',
            'send_reply_key' => (string) Str::uuid(),
            'attachments' => [UploadedFile::fake()->create('respuesta.pdf', 12, 'application/pdf')],
        ])->assertRedirect()
            ->assertSessionHas('success', 'Respuesta oficial registrada. No hay un destinatario habilitado para recibir el aviso.');

        $reply = $pqr->replies()->firstOrFail();
        $this->assertFalse($reply->is_draft);
        $this->assertNotNull($reply->sent_at);
        $this->assertSame('respondida', $pqr->fresh()->estado);
        $this->assertCount(1, $reply->attachments);
        Storage::disk('local')->assertExists($reply->attachments[0]['path']);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'sent_reply']);
    }

    public function test_internal_comment_preserves_its_http_contract_and_records_one_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = $this->manager($organizacion, $copropiedad);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();

        $this->actingAs($manager)->post(route('pqrs.comments.store', $pqr), ['body' => 'Comentario interno.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Comentario interno agregado.');

        $this->assertDatabaseHas('pqr_internal_comments', ['pqr_id' => $pqr->id, 'body' => 'Comentario interno.']);
        $this->assertDatabaseCount('pqr_activities', 1);
        $this->assertDatabaseHas('pqr_activities', ['pqr_id' => $pqr->id, 'action' => 'internal_comment']);
    }

    public function test_tag_sync_accepts_local_tags_and_rejects_external_tags(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $manager = $this->manager($organizacion, $copropiedad);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $localTag = PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create();
        $externalTag = PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->actingAs($manager)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$localTag->id]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Etiquetas actualizadas.');
        $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $localTag->id]);
        $this->assertDatabaseCount('pqr_activities', 1);

        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$externalTag->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('tags.0');
        $this->assertDatabaseMissing('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $externalTag->id]);
        $this->assertDatabaseCount('pqr_activities', 1);
    }

    public function test_externally_scoped_pqrs_are_not_found_for_expediente_operations(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $manager = $this->manager($organizacion, $copropiedad);
        $externalPqr = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->actingAs($manager)->post(route('pqrs.replies.store', $externalPqr), ['body' => 'No debe guardarse.', 'action' => 'draft'])
            ->assertNotFound();
        $this->post(route('pqrs.comments.store', $externalPqr), ['body' => 'No debe guardarse.'])
            ->assertNotFound();
        $this->patch(route('pqrs.tags.sync', $externalPqr), ['tags' => []])
            ->assertNotFound();

        $this->assertDatabaseCount('pqr_replies', 0);
        $this->assertDatabaseCount('pqr_internal_comments', 0);
        $this->assertDatabaseCount('pqr_activities', 0);
    }

    public function test_reply_transaction_rolls_back_when_an_attachment_cannot_be_stored(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = $this->manager($organizacion, $copropiedad);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['estado' => 'en_revision']);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $manager->id);
        $adjuntoFallido = new class {
            public function getClientOriginalName(): string { return 'fallido.pdf'; }
            public function getRealPath(): string { return __FILE__; }
            public function getMimeType(): string { return 'application/pdf'; }
            public function store(string $path): never { throw new RuntimeException('No se pudo almacenar el adjunto.'); }
            public function getSize(): int { return 10; }
        };

        try {
            app(RegistrarRespuestaPqrs::class)->ejecutar($contexto, $manager, $pqr, [
                'body' => 'No debe persistirse.',
                'action' => 'send',
            ], [$adjuntoFallido]);
            $this->fail('La respuesta debía fallar al almacenar el adjunto.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo almacenar el adjunto.', $exception->getMessage());
        }

        $this->assertSame('en_revision', $pqr->fresh()->estado);
        $this->assertDatabaseCount('pqr_replies', 0);
        $this->assertDatabaseCount('pqr_activities', 0);
    }

    private function manager(Organizacion $organizacion, Copropiedad $copropiedad): User
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);

        return $manager;
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
