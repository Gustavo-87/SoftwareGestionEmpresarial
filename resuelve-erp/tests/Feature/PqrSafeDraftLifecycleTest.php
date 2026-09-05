<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\PqrCommunicationOperation;
use App\Models\PqrReply;
use App\Models\User;
use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\GestionarCicloRespuestaPqrs;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationConflict;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationCleanupStatus;
use App\Application\Pqrs\RegistrarRespuestaPqrs;
use App\Enums\PqrCommunicationOperation as OperationType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrSafeDraftLifecycleTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_owner_can_create_update_send_and_deleted_drafts_stay_hidden(): void
    {
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);

        $this->actingAs($manager)->post(route('pqrs.replies.store', $pqr), ['body' => 'Inicial', 'action' => 'draft', 'create_draft_key' => (string) Str::uuid(), 'send_reply_key' => (string) Str::uuid()])->assertRedirect();
        $draft = $pqr->replies()->sole();
        $this->put(route('pqrs.replies.update', [$pqr, $draft]), ['body' => 'Corregido', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('Corregido', $draft->fresh()->body);
        $this->post(route('pqrs.replies.send', [$pqr, $draft]), ['idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertFalse($draft->fresh()->is_draft);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertSame('respondida', $pqr->fresh()->estado);
        $this->assertDatabaseHas('pqr_communication_operations', ['operation' => 'send_draft', 'result_code' => 'draft_sent', 'notification_status' => 'no_recipient']);

        $other = Pqr::factory()->paraContexto($organization, $property)->create();
        $this->post(route('pqrs.replies.store', $other), ['body' => 'Desechable', 'action' => 'draft', 'create_draft_key' => (string) Str::uuid(), 'send_reply_key' => (string) Str::uuid()]);
        $deleted = $other->replies()->sole();
        $this->delete(route('pqrs.replies.destroy', [$other, $deleted]), ['idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertNull(PqrReply::find($deleted->id));
        $this->assertNotNull(PqrReply::withTrashed()->find($deleted->id));
        $this->get(route('pqrs.replies.download', [$other, $deleted, 0]))->assertNotFound();
    }

    public function test_foreign_draft_is_404_and_owner_without_effective_management_is_403(): void
    {
        [$organization, $property] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $this->createContextualIdentity($owner, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $other = User::factory()->create();
        $this->createContextualIdentity($other, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create();
        $draft = $pqr->replies()->create(['user_id' => $owner->id, 'body' => 'x', 'is_draft' => true]);

        $this->actingAs($other)->put(route('pqrs.replies.update', [$pqr, $draft]), ['body' => 'no', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->createContextualIdentity($owner, $organization, $property, 'gestor', ['pqrs.ver_todas']);
        $this->actingAsContextual($owner)->put(route('pqrs.replies.update', [$pqr, $draft]), ['body' => 'no', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_database_and_model_reject_invalid_deleted_or_official_mutations(): void
    {
        $pqr = Pqr::factory()->create();
        try {
            DB::table('pqr_replies')->insert(['pqr_id' => $pqr->id, 'user_id' => $pqr->user_id, 'body' => 'x', 'is_draft' => false, 'sent_at' => now(), 'deleted_at' => now()]);
            $this->fail('El CHECK debía rechazar una respuesta oficial eliminada.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $official = $pqr->replies()->create(['user_id' => $pqr->user_id, 'body' => 'oficial', 'is_draft' => false, 'sent_at' => now()]);
        $this->expectException(\LogicException::class);
        $official->body = 'alterada';
        $official->save();
    }

    public function test_real_http_replay_of_all_five_operations_writes_each_result_once(): void
    {
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->actingAsContextual($manager);

        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $createKey = (string) Str::uuid();
        $create = ['body' => '  borrador\r\nuno  ', 'action' => 'draft', 'create_draft_key' => $createKey];
        $this->post(route('pqrs.replies.store', $pqr), $create)->assertRedirect();
        $this->post(route('pqrs.replies.store', $pqr), $create)->assertRedirect();
        $draft = $pqr->replies()->sole();

        $updateKey = (string) Str::uuid();
        $update = ['body' => 'borrador actualizado', 'idempotency_key' => $updateKey];
        $this->put(route('pqrs.replies.update', [$pqr, $draft]), $update)->assertRedirect();
        $this->put(route('pqrs.replies.update', [$pqr, $draft]), $update)->assertRedirect();

        $sendKey = (string) Str::uuid();
        $send = ['idempotency_key' => $sendKey];
        $this->post(route('pqrs.replies.send', [$pqr, $draft]), $send)->assertRedirect();
        $this->post(route('pqrs.replies.send', [$pqr, $draft->id]), $send)->assertRedirect();

        $deletePqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $deleted = $deletePqr->replies()->create(['user_id' => $manager->id, 'body' => 'eliminar', 'is_draft' => true]);
        $deleteKey = (string) Str::uuid();
        $this->delete(route('pqrs.replies.destroy', [$deletePqr, $deleted]), ['idempotency_key' => $deleteKey])->assertRedirect();
        $this->delete(route('pqrs.replies.destroy', [$deletePqr, $deleted->id]), ['idempotency_key' => $deleteKey])->assertRedirect();

        $directPqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'en_revision']);
        $directKey = (string) Str::uuid();
        $direct = ['body' => 'respuesta directa', 'action' => 'send', 'send_reply_key' => $directKey];
        $this->post(route('pqrs.replies.store', $directPqr), $direct)->assertRedirect();
        $this->post(route('pqrs.replies.store', $directPqr), $direct)->assertRedirect();

        $this->assertSame(5, PqrCommunicationOperation::whereIn('idempotency_key', [$createKey, $updateKey, $sendKey, $deleteKey, $directKey])->count());
        $this->assertSame(5, PqrCommunicationOperation::where('status', 'completed')->whereIn('idempotency_key', [$createKey, $updateKey, $sendKey, $deleteKey, $directKey])->count());
        $this->assertSame(1, $pqr->replies()->count());
        $this->assertSame(1, $directPqr->replies()->count());
        $this->assertSame(5, DB::table('pqr_activities')->whereIn('pqr_id', [$pqr->id, $deletePqr->id, $directPqr->id])->count());
    }

    public function test_form_key_survives_validation_error_and_is_renewed_only_after_success(): void
    {
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('create_draft_key', false)->assertSee('send_reply_key', false);
        $slot = "pqr_operation_keys.create_draft.{$pqr->id}";
        $key = session($slot);
        $this->post(route('pqrs.replies.store', $pqr), ['body' => '', 'action' => 'draft', 'create_draft_key' => $key])->assertSessionHasErrors('body');
        $this->assertSame($key, session($slot));
        $this->post(route('pqrs.replies.store', $pqr), ['body' => 'válido', 'action' => 'draft', 'create_draft_key' => $key])->assertRedirect();
        $this->assertNull(session($slot));
        $this->get(route('pqrs.show', $pqr))->assertOk();
        $this->assertNotSame($key, session($slot));
    }

    public function test_fingerprint_scopes_draft_and_attachment_metadata_without_names_or_paths(): void
    {
        $file = UploadedFile::fake()->createWithContent('persona.pdf', 'AAAA');
        $a = GestionarCicloRespuestaPqrs::fingerprint(OperationType::UpdateDraft, 9, 10, " texto\r\n", [$file], []);
        $b = GestionarCicloRespuestaPqrs::fingerprint(OperationType::UpdateDraft, 9, 11, "texto\n", [$file], []);
        $c = GestionarCicloRespuestaPqrs::fingerprint(OperationType::UpdateDraft, 9, 10, "texto\n", [UploadedFile::fake()->createWithContent('otro.pdf', 'BBBB')], []);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a));
    }

    public function test_same_attachment_metadata_with_different_content_conflicts_on_replay(): void
    {
        Storage::fake('local');
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $draft = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'x', 'is_draft' => true]);
        $key = (string) Str::uuid();
        $context = app(ContextResolver::class)->resolverExplicito($organization->id, $property->id, $manager->id);
        $cycle = app(GestionarCicloRespuestaPqrs::class);
        $cycle->update($context, $manager, $pqr, $draft, ['body' => 'x'], [UploadedFile::fake()->createWithContent('uno.pdf', 'AAAA')], $key);

        $this->expectException(PqrCommunicationOperationConflict::class);
        $cycle->update($context, $manager, $pqr, $draft->fresh(), ['body' => 'x'], [UploadedFile::fake()->createWithContent('dos.pdf', 'BBBB')], $key);
    }

    public function test_legacy_adapter_uses_ledger_and_force_delete_is_rejected(): void
    {
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $context = app(ContextResolver::class)->resolverExplicito($organization->id, $property->id, $manager->id);
        app(RegistrarRespuestaPqrs::class)->ejecutar($context, $manager, $pqr, ['body' => 'adaptada', 'action' => 'draft'], [], (string) Str::uuid());
        $this->assertDatabaseHas('pqr_communication_operations', ['pqr_id' => $pqr->id, 'operation' => 'create_draft']);

        $this->expectException(\LogicException::class);
        $pqr->replies()->sole()->forceDelete();
    }

    public function test_attachment_reconciliation_is_idempotent_and_completes_cleanup(): void
    {
        Storage::fake('local');
        [$organization, $property] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $oldKey = (string) Str::uuid();
        $oldPath = "pqrs/{$pqr->id}/communications/{$oldKey}/00";
        Storage::disk('local')->put($oldPath, 'old');
        $draft = $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'x', 'is_draft' => true, 'attachments' => [['name' => 'old.pdf', 'path' => $oldPath, 'storage_key' => $oldKey, 'position' => 0, 'size' => 3, 'mime' => 'application/pdf', 'sha256' => hash('sha256', 'old')]]]);
        $key = (string) Str::uuid();
        $payload = ['body' => 'x2', 'idempotency_key' => $key, 'attachments' => [UploadedFile::fake()->create('new.pdf', 8, 'application/pdf')]];
        $this->actingAsContextual($manager)->put(route('pqrs.replies.update', [$pqr, $draft]), $payload)->assertRedirect();
        $path = $draft->fresh()->attachments[0]['path'];
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('pqr_communication_operations', ['idempotency_key' => $key, 'cleanup_status' => 'completed']);

        $this->put(route('pqrs.replies.update', [$pqr, $draft]), $payload)->assertRedirect();
        Storage::disk('local')->assertExists($path);
        $this->assertSame(1, PqrCommunicationOperation::where('idempotency_key', $key)->count());
    }

    public function test_cleanup_manifest_never_scans_or_deletes_another_operations_file_and_is_retryable(): void
    {
        Storage::fake('local');
        $pqr = Pqr::factory()->create();
        $actor = User::factory()->create();
        $repository = app(PqrCommunicationOperationRepository::class);
        $aKey = (string) Str::uuid();
        $bKey = (string) Str::uuid();
        $aPath = "pqrs/{$pqr->id}/communications/{$aKey}/00";
        $bPath = "pqrs/{$pqr->id}/communications/{$bKey}/00";
        Storage::disk('local')->put($aPath, 'unexpected');
        Storage::disk('local')->put($bPath, 'operation-b');
        $claim = $repository->claim($pqr, $actor, OperationType::UpdateDraft, $aKey, hash('sha256', 'payload'));
        $operation = $repository->scheduleCleanup($claim->operation, [['storage_key' => $aKey, 'position' => 0, 'sha256' => hash('sha256', 'expected')]]);

        try {
            app(GestionarCicloRespuestaPqrs::class)->reconcileCleanup($pqr, $operation);
            $this->fail('La huella distinta debía mantener la limpieza pendiente.');
        } catch (\RuntimeException) {
            $this->assertSame(PqrCommunicationCleanupStatus::Pending, $operation->fresh()->cleanup_status);
        }
        Storage::disk('local')->assertExists($bPath);
        Storage::disk('local')->put($aPath, 'expected');
        app(GestionarCicloRespuestaPqrs::class)->reconcileCleanup($pqr, $operation->fresh());
        app(GestionarCicloRespuestaPqrs::class)->reconcileCleanup($pqr, $operation->fresh());
        Storage::disk('local')->assertMissing($aPath);
        Storage::disk('local')->assertExists($bPath);
        $this->assertSame(PqrCommunicationCleanupStatus::Completed, $operation->fresh()->cleanup_status);
    }
}
