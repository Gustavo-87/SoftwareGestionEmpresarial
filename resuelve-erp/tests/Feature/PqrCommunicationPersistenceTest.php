<?php

namespace Tests\Feature;

use App\Application\Pqrs\Idempotency\PqrCommunicationOperationConflict;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationOperation as OperationType;
use App\Enums\PqrCommunicationOperationStatus;
use App\Enums\PqrCommunicationResultCode;
use App\Models\Pqr;
use App\Models\PqrCommunicationOperation as OperationModel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PqrCommunicationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_enforces_generated_slot_check_uniqueness_drafts_and_restrictive_foreign_key(): void
    {
        $author = User::factory()->create();
        $pqr = Pqr::factory()->create(['estado' => 'respondida']);

        $official = $pqr->replies()->create(['user_id' => $author->id, 'body' => 'Oficial', 'is_draft' => false, 'sent_at' => now()]);
        $this->assertSame(1, (int) DB::table('pqr_replies')->find($official->id)->official_response_slot);
        $pqr->replies()->create(['user_id' => $author->id, 'body' => 'Borrador 1', 'is_draft' => true]);
        $pqr->replies()->create(['user_id' => $author->id, 'body' => 'Borrador 2', 'is_draft' => true]);
        $this->assertSame(0, OperationModel::count());

        try {
            $pqr->replies()->create(['user_id' => $author->id, 'body' => 'Otra oficial', 'is_draft' => false, 'sent_at' => now()]);
            $this->fail('La segunda respuesta oficial debía rechazarse.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            DB::table('pqr_replies')->insert(['pqr_id' => $pqr->id, 'user_id' => $author->id, 'body' => 'Incoherente', 'is_draft' => 1, 'sent_at' => now()]);
            $this->fail('El CHECK debía rechazar el borrador enviado.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $pqr->delete();
    }

    public function test_respondida_without_official_reply_is_compatible(): void
    {
        Pqr::factory()->count(4)->create(['estado' => 'respondida']);
        $this->assertSame(4, Pqr::where('estado', 'respondida')->doesntHave('replies')->count());
    }

    public function test_claim_is_idempotent_and_rejects_key_reuse_with_a_different_fingerprint_or_operation(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $fingerprint = $repository::fingerprint(['body' => 'respuesta', 'files' => []]);

        $first = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint);
        $same = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint);
        $this->assertTrue($first->claimed);
        $this->assertFalse($same->claimed);
        $this->assertSame($first->operation->id, $same->operation->id);
        $this->assertSame(PqrCommunicationOperationStatus::InProgress, $same->operation->status);

        try {
            $repository->claim($pqr, $actor, OperationType::CreateReply, $key, hash('sha256', 'otro'));
            $this->fail('La huella diferente debía producir conflicto.');
        } catch (PqrCommunicationOperationConflict) {
            $this->assertTrue(true);
        }
        $this->expectException(PqrCommunicationOperationConflict::class);
        $repository->claim($pqr, $actor, OperationType::CreateInternalComment, $key, $fingerprint);
    }

    public function test_completed_operation_returns_prior_technical_result_and_is_not_rewritten(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', 'payload');
        $claim = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint);
        $reply = $pqr->replies()->create(['user_id' => $actor->id, 'body' => 'respuesta', 'is_draft' => true]);
        $completed = $repository->complete($claim->operation, PqrCommunicationResultCode::ReplyRecorded, $reply->id);
        $repository->complete($completed, PqrCommunicationResultCode::OperationCompleted);
        $prior = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint);

        $this->assertFalse($prior->claimed);
        $this->assertSame(PqrCommunicationOperationStatus::Completed, $prior->operation->status);
        $this->assertSame(PqrCommunicationResultCode::ReplyRecorded, $prior->operation->result_code);
        $this->assertSame($reply->id, $prior->operation->pqr_reply_id);
        $this->assertNotNull($prior->operation->completed_at);
    }

    public function test_failed_transaction_before_claim_commit_can_retry_safely(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', 'payload');
        try {
            DB::transaction(function () use ($repository, $pqr, $actor, $key, $fingerprint): void {
                $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint);
                throw new RuntimeException('fallo de escritura');
            });
        } catch (RuntimeException) {
        }

        $this->assertTrue($repository->claim($pqr, $actor, OperationType::CreateReply, $key, $fingerprint)->claimed);
    }

    public function test_actor_is_null_after_user_deletion_while_immutable_identity_remains_and_model_is_protected(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $claim = $repository->claim($pqr, $actor, OperationType::CreateReply, (string) Str::uuid(), hash('sha256', 'payload'));
        $actor->delete();
        $this->assertNull($claim->operation->fresh()->actor_id);
        $this->assertNotNull($claim->operation->fresh()->actor_uuid);
        $serialized = $claim->operation->fresh()->toArray();
        $this->assertArrayNotHasKey('idempotency_key', $serialized);
        $this->assertArrayNotHasKey('payload_sha256', $serialized);
        $this->assertArrayNotHasKey('actor_uuid', $serialized);
        $this->assertArrayNotHasKey('result_code', $serialized);
        $this->assertSame([], (new OperationModel)->getFillable());
    }

    public function test_actor_identity_is_random_and_immutable_fields_fail_before_update(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $first = $repository->claim($pqr, $actor, OperationType::CreateReply, (string) Str::uuid(), hash('sha256', 'one'))->operation;
        $second = $repository->claim($pqr, $actor, OperationType::CreateReply, (string) Str::uuid(), hash('sha256', 'two'))->operation;
        $this->assertNotSame($first->actor_uuid, $second->actor_uuid);

        foreach (['actor_uuid', 'idempotency_key', 'actor_id', 'pqr_id', 'operation', 'payload_sha256'] as $attribute) {
            $operation = $first->fresh();
            $operation->setAttribute($attribute, match ($attribute) {
                'actor_id', 'pqr_id' => PHP_INT_MAX,
                'operation' => OperationType::CreateInternalComment,
                'payload_sha256' => hash('sha256', 'changed'),
                default => (string) Str::uuid(),
            });
            try {
                $operation->save();
                $this->fail("{$attribute} debía ser inmutable.");
            } catch (\LogicException) {
                $this->assertFalse($operation->wasChanged());
            }
        }
    }

    public function test_deleted_actor_makes_historical_key_irreversibly_conflicting(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $hash = hash('sha256', 'historical');
        $operation = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $hash)->operation;
        $historicalUuid = $operation->actor_uuid;
        $actor->delete();
        $this->assertSame($historicalUuid, $operation->fresh()->actor_uuid);

        $this->expectException(PqrCommunicationOperationConflict::class);
        $repository->claim($pqr, null, OperationType::CreateReply, $key, $hash);
    }

    public function test_global_key_validates_pqr_actor_operation_and_fingerprint(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $otherActor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $otherPqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $hash = hash('sha256', 'scope');
        $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $hash);

        foreach ([
            [$otherPqr, $actor, OperationType::CreateReply, $hash],
            [$pqr, $otherActor, OperationType::CreateReply, $hash],
            [$pqr, $actor, OperationType::CreateInternalComment, $hash],
            [$pqr, $actor, OperationType::CreateReply, hash('sha256', 'different')],
        ] as [$candidatePqr, $candidateActor, $candidateOperation, $candidateHash]) {
            try {
                $repository->claim($candidatePqr, $candidateActor, $candidateOperation, $key, $candidateHash);
                $this->fail('El cambio de ámbito debía producir conflicto.');
            } catch (PqrCommunicationOperationConflict) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, OperationModel::where('idempotency_key', $key)->count());
    }

    public function test_database_rejects_cross_pqr_and_incompatible_references(): void
    {
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $otherPqr = Pqr::factory()->create();
        $reply = $otherPqr->replies()->create(['user_id' => $actor->id, 'body' => 'x', 'is_draft' => true]);
        $comment = $pqr->internalComments()->create(['user_id' => $actor->id, 'body' => 'x']);
        $base = [
            'pqr_id' => $pqr->id, 'actor_id' => $actor->id, 'actor_uuid' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(), 'payload_sha256' => hash('sha256', 'x'),
            'status' => 'completed', 'cleanup_status' => 'not_required', 'notification_status' => 'not_required',
        ];
        foreach ([
            $base + ['operation' => 'create_reply', 'pqr_reply_id' => $reply->id],
            array_merge($base, ['idempotency_key' => (string) Str::uuid(), 'operation' => 'create_reply', 'pqr_internal_comment_id' => $comment->id]),
        ] as $row) {
            try {
                DB::table('pqr_communication_operations')->insert($row);
                $this->fail('La referencia incoherente debía rechazarse en base de datos.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_database_enforces_complete_operation_result_reference_matrix(): void
    {
        $actor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $reply = $pqr->replies()->create(['user_id' => $actor->id, 'body' => 'x', 'is_draft' => true]);
        $comment = $pqr->internalComments()->create(['user_id' => $actor->id, 'body' => 'x']);
        $base = fn (string $operation, string $status = 'in_progress') => [
            'pqr_id' => $pqr->id, 'actor_id' => $actor->id, 'actor_uuid' => (string) Str::uuid(),
            'operation' => $operation, 'idempotency_key' => (string) Str::uuid(), 'payload_sha256' => hash('sha256', Str::random()),
            'status' => $status, 'cleanup_status' => 'not_required', 'notification_status' => 'not_required',
            'completed_at' => $status === 'completed' ? now() : null,
        ];
        $allowed = [
            $base('create_reply'),
            $base('create_internal_comment'),
            $base('create_reply', 'completed') + ['result_code' => 'reply_recorded', 'pqr_reply_id' => $reply->id],
            $base('create_internal_comment', 'completed') + ['result_code' => 'comment_recorded', 'pqr_internal_comment_id' => $comment->id],
            $base('create_reply', 'completed') + ['result_code' => 'operation_completed'],
            $base('create_internal_comment', 'completed') + ['result_code' => 'operation_completed'],
        ];
        foreach ($allowed as $row) {
            DB::table('pqr_communication_operations')->insert($row);
        }
        $this->assertSame(count($allowed), OperationModel::count());

        $forbidden = [
            $base('create_reply') + ['pqr_reply_id' => $reply->id],
            $base('create_internal_comment') + ['pqr_internal_comment_id' => $comment->id],
            $base('create_reply', 'completed') + ['result_code' => 'comment_recorded', 'pqr_internal_comment_id' => $comment->id],
            $base('create_internal_comment', 'completed') + ['result_code' => 'reply_recorded', 'pqr_reply_id' => $reply->id],
            $base('create_reply', 'completed') + ['result_code' => 'reply_recorded'],
            $base('create_internal_comment', 'completed') + ['result_code' => 'comment_recorded'],
            $base('create_reply', 'completed') + ['result_code' => 'operation_completed', 'pqr_reply_id' => $reply->id],
            $base('create_internal_comment', 'completed') + ['result_code' => 'operation_completed', 'pqr_internal_comment_id' => $comment->id],
            $base('create_reply', 'completed') + ['result_code' => 'reply_recorded', 'pqr_reply_id' => $reply->id, 'pqr_internal_comment_id' => $comment->id],
            $base('create_reply', 'completed') + ['result_code' => null],
        ];
        foreach ($forbidden as $row) {
            try {
                DB::table('pqr_communication_operations')->insert($row);
                $this->fail('La combinación operación/resultado/referencia debía rechazarse.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_non_unique_query_exception_is_never_treated_as_idempotent_collision(): void
    {
        $invalidPqr = new Pqr;
        $invalidPqr->setAttribute('id', PHP_INT_MAX);

        $this->expectException(QueryException::class);
        app(PqrCommunicationOperationRepository::class)->claim(
            $invalidPqr,
            User::factory()->create(),
            OperationType::CreateReply,
            (string) Str::uuid(),
            hash('sha256', 'foreign-key-failure'),
        );
    }

    public function test_rollback_is_blocked_when_ledger_has_been_used(): void
    {
        $repository = app(PqrCommunicationOperationRepository::class);
        $repository->claim(Pqr::factory()->create(), User::factory()->create(), OperationType::CreateReply, (string) Str::uuid(), hash('sha256', 'payload'));
        $migration = require database_path('migrations/2026_08_13_120000_secure_pqr_replies_and_create_communication_operations.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trazabilidad');
        $migration->down();
    }

    public function test_empty_ledger_allows_sqlite_rollback_and_reapplication(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Cobertura específica de reconstrucción SQLite.');
        }
        $migration = require database_path('migrations/2026_08_13_120000_secure_pqr_replies_and_create_communication_operations.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('pqr_communication_operations'));
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'official_response_slot'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('pqr_replies', 'official_response_slot'));
        $this->assertSame(0, DB::table('pqr_communication_operations')->count());
    }

    public function test_preflight_aborts_before_ddl_and_reports_every_rejected_condition(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('El escenario portátil de datos inválidos usa diferimiento FK de SQLite.');
        }
        $migration = require database_path('migrations/2026_08_13_120000_secure_pqr_replies_and_create_communication_operations.php');
        $migration->down();
        $author = User::factory()->create();
        $pqr = Pqr::factory()->create(['estado' => 'radicada']);
        $base = ['pqr_id' => $pqr->id, 'user_id' => $author->id, 'body' => 'x', 'is_draft' => 0, 'sent_at' => now()];
        DB::table('pqr_replies')->insert($base);
        DB::table('pqr_replies')->insert($base);
        DB::table('pqr_replies')->insert(['pqr_id' => $pqr->id, 'user_id' => null, 'body' => 'x', 'is_draft' => 1, 'sent_at' => now(), 'attachments' => '{}']);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('pqr_replies')->insert(['pqr_id' => 999999, 'user_id' => $author->id, 'body' => 'x', 'is_draft' => 1, 'sent_at' => null, 'attachments' => 'not-json']);

        try {
            $migration->up();
            $this->fail('El preflight debía abortar antes del DDL.');
        } catch (RuntimeException $exception) {
            foreach ([
                'más de una respuesta oficial',
                'combinaciones incoherentes',
                'respuestas sin autor',
                'respuestas huérfanas',
                'arrays JSON válidos',
                'estados incompatibles',
            ] as $condition) {
                $this->assertStringContainsString($condition, $exception->getMessage());
            }
        }
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'official_response_slot'));
        $this->assertFalse(Schema::hasTable('pqr_communication_operations'));
    }
}
