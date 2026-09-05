<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PqrSafeDraftMysqlPartialDdlTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_and_down_resume_each_unambiguous_partial_mysql_state(): void
    {
        if (DB::getDriverName() !== 'mysql') $this->markTestSkipped('Requiere MySQL.');
        $migration = require database_path('migrations/2026_08_13_180000_add_safe_draft_lifecycle.php');
        $migration->down();

        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK (operation LIKE 'send%')");
        try {
            $migration->up();
            $this->fail('El CHECK fuera de la gramática debía abortar antes del DDL de respuestas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambiguo', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'deleted_at'));
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK (operation IN ('create_reply','create_internal_comment','create_draft','update_draft','send_draft','delete_draft','send_draft'))");
        try {
            $migration->up();
            $this->fail('El CHECK semánticamente distinto debía abortar antes del DDL de respuestas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambiguo', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'deleted_at'));
        $this->assertFalse($this->indexExists('pqr_replies', 'pqr_replies_draft_deleted_index'));
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK (operation IN ('create_reply','create_internal_comment'))");

        DB::statement('ALTER TABLE pqr_replies ADD deleted_at TIMESTAMP NULL AFTER sent_at');
        $migration->up();
        $this->assertB2ReplySchema();

        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK ( OPERATION IN ( 'create_reply' , 'create_internal_comment' , 'create_draft' , 'update_draft' , 'send_draft' , 'delete_draft' , 'send_reply' ) )");
        $migration->up();
        $this->assertB2ReplySchema();
        $this->assertTrue($this->checkExists('pqr_communication_operations', 'pqr_comm_operation_check'));

        $this->assertTrue(Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest'));

        DB::statement('ALTER TABLE pqr_replies DROP CHECK pqr_replies_deleted_draft_check');
        $migration->up();
        $this->assertB2ReplySchema();

        DB::statement('ALTER TABLE pqr_replies DROP INDEX pqr_replies_draft_deleted_index');
        $migration->up();
        $this->assertB2ReplySchema();

        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_result_matrix_check');
        $migration->up();
        $this->assertTrue($this->checkExists('pqr_communication_operations', 'pqr_comm_result_matrix_check'));

        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_cleanup_manifest_check');
        $migration->up();
        $this->assertTrue($this->checkExists('pqr_communication_operations', 'pqr_comm_cleanup_manifest_check'));

        DB::statement('ALTER TABLE pqr_replies DROP CHECK pqr_replies_deleted_draft_check');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest'));

        $migration->up();
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_cleanup_manifest_check');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'deleted_at'));

        $migration->up();
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK (operation IN ('create_reply','create_internal_comment'))");
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'deleted_at'));
        $migration->up();
    }

    private function assertB2ReplySchema(): void
    {
        $this->assertTrue(Schema::hasColumn('pqr_replies', 'deleted_at'));
        $this->assertTrue($this->indexExists('pqr_replies', 'pqr_replies_draft_deleted_index'));
        $this->assertTrue($this->checkExists('pqr_replies', 'pqr_replies_deleted_draft_check'));
    }

    private function checkExists(string $table, string $check): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $check)->where('CONSTRAINT_TYPE', 'CHECK')->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('INDEX_NAME', $index)->exists();
    }
}
