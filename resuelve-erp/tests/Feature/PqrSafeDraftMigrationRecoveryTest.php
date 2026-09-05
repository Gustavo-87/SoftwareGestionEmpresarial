<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\PqrCommunicationOperation;
use App\Models\User;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Enums\PqrCommunicationOperation as OperationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Illuminate\Support\Str;
use Tests\TestCase;

class PqrSafeDraftMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_failure_boundaries_retry_without_row_loss_or_early_backup_drop(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        DB::table('pqr_replies')->insert(['pqr_id' => $pqr->id, 'user_id' => $user->id, 'body' => 'fila', 'is_draft' => 1, 'attachments' => '[{"size":7,"mime":"application/pdf"}]']);

        foreach (['replies_after_rename', 'replies_after_create', 'replies_during_copy', 'replies_before_drop', 'between_tables', 'ledger_after_rename', 'ledger_after_create', 'ledger_before_drop'] as $point) {
            $migration->sqliteFailurePoint = $point;
            try {
                $migration->up();
                $this->fail("Debía fallar en {$point}.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($point, $exception->getMessage());
            }
            $this->assertSame(1, $this->replyCountAcrossRecoveryTables());
            $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
            $migration->up();
            $this->assertSame(1, DB::table('pqr_replies')->count());
            $this->assertFalse(Schema::hasTable('pqr_replies_c351b2_previous'));
            $this->assertFalse(Schema::hasTable('pqr_communication_operations_c351b2_previous'));
            $this->assertTrue(Schema::hasColumn('pqr_replies', 'deleted_at'));
            $migration->down();
        }
        $migration->up();
    }

    public function test_sqlite_preserves_both_tables_when_deleted_at_or_row_content_differs(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        DB::statement('ALTER TABLE pqr_replies RENAME TO pqr_replies_c351b2_previous');
        DB::statement("CREATE TABLE pqr_replies AS SELECT id,pqr_id,user_id,body,is_draft,attachments,sent_at,created_at,updated_at,deleted_at FROM pqr_replies_c351b2_previous");
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        DB::table('pqr_replies_c351b2_previous')->insert(['pqr_id' => $pqr->id, 'user_id' => $user->id, 'body' => 'original', 'is_draft' => 1]);
        DB::table('pqr_replies')->insert(['id' => DB::table('pqr_replies_c351b2_previous')->max('id'), 'pqr_id' => $pqr->id, 'user_id' => $user->id, 'body' => 'distinta', 'is_draft' => 1, 'deleted_at' => now()]);
        try {
            $migration->up();
            $this->fail('La divergencia debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambigua', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('pqr_replies'));
        $this->assertTrue(Schema::hasTable('pqr_replies_c351b2_previous'));
    }

    public function test_interrupted_down_with_only_backup_never_reactivates_deleted_draft(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $draft = $pqr->replies()->create(['user_id' => $user->id, 'body' => 'eliminado', 'is_draft' => true]);
        $draft->delete();
        DB::statement('ALTER TABLE pqr_replies RENAME TO pqr_replies_c351b2_previous');

        try {
            $migration->down();
            $this->fail('El rollback parcial debía preservar el borrador eliminado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('borradores eliminados', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('pqr_replies'));
        $this->assertTrue(Schema::hasTable('pqr_replies_c351b2_previous'));
        $this->assertNotNull(DB::table('pqr_replies_c351b2_previous')->where('id', $draft->id)->value('deleted_at'));
    }

    public function test_interrupted_up_with_only_b2_backup_preserves_deleted_at_exactly(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $draft = $pqr->replies()->create(['user_id' => $user->id, 'body' => 'eliminado', 'is_draft' => true, 'attachments' => [['size' => 1, 'mime' => 'text/plain', 'sha256' => hash('sha256', 'x')]]]);
        $draft->delete();
        $expected = DB::table('pqr_replies')->where('id', $draft->id)->value('deleted_at');
        DB::statement('ALTER TABLE pqr_replies RENAME TO pqr_replies_c351b2_previous');

        $migration->up();

        $this->assertTrue(Schema::hasTable('pqr_replies'));
        $this->assertFalse(Schema::hasTable('pqr_replies_c351b2_previous'));
        $this->assertSame($expected, DB::table('pqr_replies')->where('id', $draft->id)->value('deleted_at'));
    }

    public function test_interrupted_down_with_only_ledger_backup_preserves_b2_operations(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        app(PqrCommunicationOperationRepository::class)->claim($pqr, $user, OperationType::CreateDraft, (string) Str::uuid(), hash('sha256', 'payload'));
        DB::statement('ALTER TABLE pqr_communication_operations RENAME TO pqr_communication_operations_c351b2_previous');

        try {
            $migration->down();
            $this->fail('El rollback parcial debía preservar las operaciones B-2.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('operaciones', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('pqr_communication_operations'));
        $this->assertTrue(Schema::hasTable('pqr_communication_operations_c351b2_previous'));
        $this->assertSame(1, DB::table('pqr_communication_operations_c351b2_previous')->count());
    }

    public function test_sqlite_requires_exact_ledger_equality_before_dropping_backup(): void
    {
        if (DB::getDriverName() !== 'sqlite') $this->markTestSkipped('Requiere SQLite.');
        $migration = $this->migration();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->create();
        app(PqrCommunicationOperationRepository::class)->claim($pqr, $user, OperationType::CreateDraft, (string) Str::uuid(), hash('sha256', 'payload'));
        DB::statement('ALTER TABLE pqr_communication_operations RENAME TO pqr_communication_operations_c351b2_previous');
        $migration->sqliteFailurePoint = 'ledger_after_create';
        try {
            $migration->up();
            $this->fail('El fallo inyectado debía dejar ambas tablas para recuperación.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
        $row = (array) DB::table('pqr_communication_operations_c351b2_previous')->first();
        $row['payload_sha256'] = hash('sha256', 'distinto');
        DB::table('pqr_communication_operations')->insert($row);

        try {
            $migration->up();
            $this->fail('El ledger divergente debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambigua', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('pqr_communication_operations'));
        $this->assertTrue(Schema::hasTable('pqr_communication_operations_c351b2_previous'));
        $this->assertSame(1, PqrCommunicationOperation::withoutEvents(fn () => DB::table('pqr_communication_operations')->count()));
    }

    public function test_mysql_check_comparator_preserves_literals_and_rejects_ambiguous_input(): void
    {
        $migration = $this->migration();

        $this->assertFalse($this->mysqlExpressionsEqual($migration, "operation IN ('send_reply')", "operation IN ('send_draft')"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "operation IN ('create_draft')", "operation IN ('update_draft')"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "result_code IN ('reply_recorded')", "result_code IN ('comment_recorded')"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, 'pqr_reply_id IS NOT NULL', 'pqr_internal_comment_id IS NOT NULL'));
        $this->assertTrue($this->mysqlExpressionsEqual($migration, " ( STATUS = 'completed' AND operation IN ('send_reply') ) ", "(status='completed' and OPERATION in ('send_reply'))"));
        $this->assertTrue($this->mysqlExpressionsEqual($migration, "result_code = _utf8mb4'under_score 42 O''Brien'", " RESULT_CODE='under_score 42 O''Brien' "));
        $mysqlRendered = "result_code = _utf8mb4\\'under_score 42 O".str_repeat('\\', 3)."'Brien\\'";
        $this->assertTrue($this->mysqlExpressionsEqual($migration, $mysqlRendered, "result_code='under_score 42 O''Brien'"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "result_code='under_score 42 O''Brien'", "result_code='under score 42 O''Brien'"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "result_code='value with spaces'", "result_code='valuewithspaces'"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "operation = 'send_reply", "operation = 'send_reply"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "((operation='send_reply')", "((operation='send_reply')"));
        $this->assertFalse($this->mysqlExpressionsEqual($migration, "operation @@ 'send_reply'", "operation @@ 'send_reply'"));
        foreach ([
            'operation =',
            'operation IN ()',
            "operation == 'send_reply'",
            "operation IN ('send_reply'",
            "operation IN ('send_reply',)",
        ] as $invalid) {
            $this->assertFalse($this->mysqlExpressionsEqual($migration, $invalid, $invalid));
        }
        $this->assertTrue($this->mysqlExpressionsEqual($migration, "cleanup_status IN ('pending', 'completed')", " CLEANUP_STATUS in('pending','completed') "));
        $this->assertTrue($this->mysqlExpressionsEqual($migration, "JSON_VALID(cleanup_manifest) AND JSON_TYPE(cleanup_manifest)='ARRAY'", "json_valid ( cleanup_manifest ) and json_type(cleanup_manifest) = 'ARRAY'"));
    }

    private function replyCountAcrossRecoveryTables(): int
    {
        $main = Schema::hasTable('pqr_replies') ? DB::table('pqr_replies')->count() : 0;
        $backup = Schema::hasTable('pqr_replies_c351b2_previous') ? DB::table('pqr_replies_c351b2_previous')->count() : 0;

        return max($main, $backup);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_180000_add_safe_draft_lifecycle.php');
    }

    private function mysqlExpressionsEqual(object $migration, string $actual, string $expected): bool
    {
        return (new \ReflectionMethod($migration, 'mysqlExpressionsEqual'))->invoke($migration, $actual, $expected);
    }
}
