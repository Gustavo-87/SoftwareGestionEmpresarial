<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PqrCommunicationMysqlRollbackTest extends TestCase
{
    public function test_rollback_recovers_nothing_ledger_only_slot_only_restrict_only_and_complete_states(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere MySQL.');
        }
        $migration = require database_path('migrations/2026_08_13_120000_secure_pqr_replies_and_create_communication_operations.php');

        $migration->down();
        $migration->down();
        $this->assertCascadeBaseline();
        $migration->up();

        $this->removeReplyProtectionKeepingLedger();
        $migration->down();
        $this->assertFalse(Schema::hasTable('pqr_communication_operations'));
        $this->assertCascadeBaseline();
        $migration->up();

        DB::statement('DROP TABLE pqr_communication_operations');
        $migration->down();
        $this->assertCascadeBaseline();
        $migration->up();

        $migration->down();
        DB::statement('ALTER TABLE pqr_replies DROP FOREIGN KEY pqr_replies_pqr_id_foreign');
        DB::statement('ALTER TABLE pqr_replies ADD CONSTRAINT pqr_replies_pqr_id_foreign FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT');
        $migration->down();
        $this->assertCascadeBaseline();
        $migration->up();

        $migration->down();
        $this->assertCascadeBaseline();
        $migration->up();
        $this->assertTrue(Schema::hasTable('pqr_communication_operations'));
        $this->assertTrue(Schema::hasColumn('pqr_replies', 'official_response_slot'));
    }

    private function removeReplyProtectionKeepingLedger(): void
    {
        DB::statement('ALTER TABLE pqr_replies DROP FOREIGN KEY pqr_replies_pqr_id_foreign');
        DB::statement('ALTER TABLE pqr_replies DROP INDEX pqr_replies_official_unique, DROP CHECK pqr_replies_draft_sent_check, DROP COLUMN official_response_slot');
        DB::statement('ALTER TABLE pqr_replies ADD CONSTRAINT pqr_replies_pqr_id_foreign FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE CASCADE');
    }

    private function assertCascadeBaseline(): void
    {
        $this->assertFalse(Schema::hasTable('pqr_communication_operations'));
        $this->assertFalse(Schema::hasColumn('pqr_replies', 'official_response_slot'));
        $this->assertSame('CASCADE', DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'pqr_replies')
            ->where('CONSTRAINT_NAME', 'pqr_replies_pqr_id_foreign')
            ->value('DELETE_RULE'));
    }
}
