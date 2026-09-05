<?php

namespace Tests\Feature;

use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationOperation;
use App\Models\Pqr;
use App\Models\PqrAttachment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrCommunicationPrivacyAndRetentionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_unauthorized_invalid_payload_is_forbidden_before_validation_and_http_contracts_remain(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create();
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id]);

        $this->post(route('pqrs.replies.store', $pqr), [])->assertRedirect(route('login'));
        $this->actingAsContextual($resident)->post(route('pqrs.replies.store', $pqr), [])->assertForbidden()->assertSessionHasNoErrors();
        $this->post(route('pqrs.comments.store', $pqr), [])->assertForbidden()->assertSessionHasNoErrors();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAsContextual($manager)->withMiddleware(ValidateCsrfToken::class)
            ->post(route('pqrs.replies.store', $pqr), ['body' => 'x', 'action' => 'draft'])->assertStatus(419);
    }

    public function test_backend_filters_drafts_comments_and_internal_history_for_resident_and_auditor(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create();
        $auditor = User::factory()->create();
        $manager = User::factory()->create();
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($auditor, $org, $cop, 'auditor', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id]);
        $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'MARCADOR_BORRADOR', 'is_draft' => true, 'attachments' => [['name' => 'MARCADOR_ARCHIVO', 'path' => 'privado']]]);
        $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'Respuesta pública', 'is_draft' => false, 'sent_at' => now()]);
        $pqr->internalComments()->create(['user_id' => $manager->id, 'body' => 'MARCADOR_COMENTARIO']);
        $pqr->activities()->create(['user_id' => $manager->id, 'action' => 'drafted_reply', 'description' => 'MARCADOR_ACTUACION_INTERNA', 'metadata' => ['secret' => 'MARCADOR_METADATA']]);
        $pqr->activities()->create(['user_id' => $manager->id, 'action' => 'sent_reply', 'description' => 'Actuación pública']);

        foreach ([$resident, $auditor] as $reader) {
            $response = $this->actingAsContextual($reader)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('Respuesta pública')->assertSee('Actuación pública');
            foreach (['MARCADOR_BORRADOR', 'MARCADOR_ARCHIVO', 'MARCADOR_COMENTARIO', 'MARCADOR_ACTUACION_INTERNA', 'MARCADOR_METADATA'] as $marker) {
                $response->assertDontSee($marker, false);
            }
            $viewPqr = $response->viewData('pqr');
            $this->assertFalse($viewPqr->relationLoaded('internalComments'));
            $this->assertSame(1, $viewPqr->replies->count());
            $this->assertSame(['sent_reply'], $viewPqr->activities->pluck('action')->all());
        }
    }

    public function test_effective_admin_gestor_and_apoyo_receive_internal_collections_without_n_plus_one_growth(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        foreach (['admin', 'gestor', 'apoyo'] as $role) {
            $user = User::factory()->create();
            $this->createContextualIdentity($user, $org, $cop, $role, ['pqrs.ver_todas', 'pqrs.gestionar']);
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['assigned_to_id' => $role === 'apoyo' ? $user->id : null]);
            foreach (range(1, 5) as $number) {
                $pqr->replies()->create(['user_id' => $user->id, 'body' => "Borrador {$number}", 'is_draft' => true]);
                $pqr->internalComments()->create(['user_id' => $user->id, 'body' => "Comentario {$number}"]);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->actingAsContextual($user)->get(route('pqrs.show', $pqr))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertTrue($response->viewData('pqr')->relationLoaded('internalComments'));
            $this->assertSame(5, $response->viewData('pqr')->replies->where('is_draft', true)->count());
            $this->assertLessThan(30, $queries);
        }
    }

    public function test_draft_visibility_is_tristate_for_apoyo_admin_gestor_and_reader(): void
    {
        Storage::fake('local');
        [$org, $cop] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create();
        $other = User::factory()->create();
        $reader = User::factory()->create();
        $this->createContextualIdentity($apoyo, $org, $cop, 'apoyo', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->createContextualIdentity($reader, $org, $cop, 'lector', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['assigned_to_id' => $apoyo->id]);
        Storage::disk('local')->put('draft-own.txt', 'own');
        Storage::disk('local')->put('draft-other.txt', 'other');
        $own = $pqr->replies()->create(['user_id' => $apoyo->id, 'body' => 'BORRADOR_PROPIO', 'is_draft' => true, 'attachments' => [['name' => 'own.txt', 'path' => 'draft-own.txt']]]);
        $foreign = $pqr->replies()->create(['user_id' => $other->id, 'body' => 'BORRADOR_AJENO', 'is_draft' => true, 'attachments' => [['name' => 'other.txt', 'path' => 'draft-other.txt']]]);

        $apoyoResponse = $this->actingAsContextual($apoyo)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('BORRADOR_PROPIO')->assertDontSee('BORRADOR_AJENO');
        $this->assertSame([$own->id], $apoyoResponse->viewData('pqr')->replies->modelKeys());
        $this->get(route('pqrs.replies.download', [$pqr, $own, 0]))->assertOk();
        $this->get(route('pqrs.replies.download', [$pqr, $foreign, 0]))->assertNotFound();

        foreach (['admin', 'gestor'] as $role) {
            $manager = User::factory()->create();
            $this->createContextualIdentity($manager, $org, $cop, $role, ['pqrs.ver_todas', 'pqrs.gestionar']);
            $response = $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))->assertOk()->assertDontSee('BORRADOR_AJENO');
            $this->assertContains($foreign->id, $response->viewData('pqr')->replies->modelKeys());
            $this->get(route('pqrs.replies.download', [$pqr, $foreign, 0]))->assertOk();
        }

        $readerResponse = $this->actingAsContextual($reader)->get(route('pqrs.show', $pqr))->assertOk()->assertDontSee('BORRADOR_PROPIO')->assertDontSee('BORRADOR_AJENO');
        $this->assertSame([], $readerResponse->viewData('pqr')->replies->modelKeys());
    }

    public function test_unknown_history_action_is_private_by_default_while_allowlisted_action_remains_public(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $reader = User::factory()->create();
        $this->createContextualIdentity($reader, $org, $cop, 'auditor', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $pqr->activities()->create(['user_id' => $reader->id, 'action' => 'future_unknown_action', 'description' => 'MARCADOR_DESCONOCIDO']);
        $public = $pqr->activities()->create(['user_id' => $reader->id, 'action' => 'created', 'description' => 'MARCADOR_PUBLICO']);

        $response = $this->actingAsContextual($reader)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('MARCADOR_PUBLICO')->assertDontSee('MARCADOR_DESCONOCIDO');
        $this->assertSame([$public->id], $response->viewData('pqr')->activities->modelKeys());
    }

    public function test_reply_download_enforces_official_draft_and_parent_scope_rules(): void
    {
        Storage::fake('local');
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create();
        $manager = User::factory()->create();
        $other = User::factory()->create();
        $formerAuthor = User::factory()->create();
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->createContextualIdentity($formerAuthor, $org, $cop, 'lector', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id]);
        $otherPqr = Pqr::factory()->paraContexto($org, $cop)->create();
        Storage::disk('local')->put('reply.txt', 'official');
        Storage::disk('local')->put('draft.txt', 'draft');
        $official = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'x', 'is_draft' => false, 'sent_at' => now(), 'attachments' => [['name' => 'official.txt', 'path' => 'reply.txt']]]);
        $draft = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'x', 'is_draft' => true, 'attachments' => [['name' => 'draft.txt', 'path' => 'draft.txt']]]);
        $ownRestrictedDraft = $pqr->replies()->create(['user_id' => $formerAuthor->id, 'body' => 'x', 'is_draft' => true, 'attachments' => [['name' => 'draft.txt', 'path' => 'draft.txt']]]);
        $foreignReply = $otherPqr->replies()->create(['user_id' => $other->id, 'body' => 'x', 'is_draft' => true, 'attachments' => [['name' => 'x', 'path' => 'draft.txt']]]);

        $this->actingAsContextual($resident)->get(route('pqrs.replies.download', [$pqr, $official, 0]))->assertOk();
        $this->get(route('pqrs.replies.download', [$pqr, $draft, 0]))->assertNotFound();
        $this->get(route('pqrs.replies.download', [$pqr, $foreignReply, 0]))->assertNotFound();
        $this->actingAsContextual($formerAuthor)->get(route('pqrs.replies.download', [$pqr, $ownRestrictedDraft, 0]))->assertForbidden();
        $this->actingAsContextual($manager)->get(route('pqrs.replies.download', [$pqr, $draft, 0]))->assertOk();
    }

    public function test_deletion_is_blocked_by_each_evidence_type_and_preserves_files_atomically(): void
    {
        Storage::fake('local');
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create();
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['pqrs.eliminar', 'pqrs.ver_todas', 'pqrs.gestionar']);

        foreach (['official_reply', 'draft', 'comment', 'activity', 'operation'] as $type) {
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
            Storage::disk('local')->put("{$type}.txt", 'evidence');
            PqrAttachment::create(['pqr_id' => $pqr->id, 'original_name' => "{$type}.txt", 'path' => "{$type}.txt", 'mime_type' => 'text/plain', 'size' => 8]);
            match ($type) {
                'official_reply' => $pqr->replies()->create(['user_id' => $admin->id, 'body' => 'x', 'is_draft' => false, 'sent_at' => now()]),
                'draft' => $pqr->replies()->create(['user_id' => $admin->id, 'body' => 'x', 'is_draft' => true]),
                'comment' => $pqr->internalComments()->create(['user_id' => $admin->id, 'body' => 'x']),
                'activity' => $pqr->activities()->create(['user_id' => $admin->id, 'action' => 'updated', 'description' => 'x']),
                'operation' => app(PqrCommunicationOperationRepository::class)->claim($pqr, $admin, PqrCommunicationOperation::CreateReply, (string) Str::uuid(), hash('sha256', 'x')),
            };
            $this->actingAsContextual($admin)->delete(route('pqrs.destroy', $pqr))
                ->assertRedirect(route('pqrs.edit', $pqr))
                ->assertSessionHasErrors(['pqr' => 'Esta PQRS conserva actuaciones o comunicaciones y no puede eliminarse físicamente.']);
            $this->followingRedirects()->delete(route('pqrs.destroy', $pqr))->assertOk()
                ->assertSee('Esta PQRS conserva actuaciones o comunicaciones y no puede eliminarse físicamente.');
            $this->get(route('pqrs.show', $pqr))->assertOk()
                ->assertDontSee('Esta PQRS conserva actuaciones o comunicaciones y no puede eliminarse físicamente.');
            $this->assertDatabaseHas('pqrs', ['id' => $pqr->id]);
            Storage::disk('local')->assertExists("{$type}.txt");
        }
    }

    public function test_deletion_without_evidence_remains_allowed_and_removes_attachment_after_commit(): void
    {
        Storage::fake('local');
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create();
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['pqrs.eliminar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        Storage::disk('local')->put('removable.txt', 'x');
        PqrAttachment::create(['pqr_id' => $pqr->id, 'original_name' => 'removable.txt', 'path' => 'removable.txt', 'mime_type' => 'text/plain', 'size' => 1]);

        $this->actingAsContextual($admin)->delete(route('pqrs.destroy', $pqr))->assertRedirect(route('pqrs.index'));
        $this->assertDatabaseMissing('pqrs', ['id' => $pqr->id]);
        Storage::disk('local')->assertMissing('removable.txt');
    }
}
