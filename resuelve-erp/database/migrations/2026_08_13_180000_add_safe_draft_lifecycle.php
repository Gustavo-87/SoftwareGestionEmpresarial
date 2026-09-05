<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPLIES_BACKUP = 'pqr_replies_c351b2_previous';
    private const LEDGER_BACKUP = 'pqr_communication_operations_c351b2_previous';

    public ?string $sqliteFailurePoint = null;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(true);
            return;
        }
        $this->preflightMysql(true);
        $this->applyMysqlReplySchema();
        $this->applyMysqlCleanupManifest();
        $this->replaceMysqlLedgerChecks(true);
    }

    public function down(): void
    {
        $this->assertRollbackSafe();
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(false);
            return;
        }
        $this->preflightMysql(false);
        $this->replaceMysqlLedgerChecks(false);
        $this->removeMysqlCleanupManifest();
        $this->removeMysqlReplySchema();
    }

    private function rebuildSqlite(bool $b2): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            $this->rebuildReplies($b2);
            $this->fail('between_tables');
            $this->rebuildLedger($b2);
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function rebuildReplies(bool $b2): void
    {
        $main = Schema::hasTable('pqr_replies');
        $backup = Schema::hasTable(self::REPLIES_BACKUP);
        if (! $main && ! $backup) throw new RuntimeException('Recuperación B-2 imposible: no existe pqr_replies ni su respaldo específico.');
        if ($main && $this->sqliteRepliesState('pqr_replies') === 'ambiguous') throw new RuntimeException('Recuperación B-2 ambigua: el esquema principal de respuestas es parcial.');
        if ($backup && $this->sqliteRepliesState(self::REPLIES_BACKUP) === 'ambiguous') throw new RuntimeException('Recuperación B-2 ambigua: el respaldo de respuestas es parcial.');
        if ($main && ! $backup && $this->sqliteRepliesState('pqr_replies') === ($b2 ? 'b2' : 'legacy')) return;
        if ($main && ! $backup) {
            DB::statement('ALTER TABLE pqr_replies RENAME TO '.self::REPLIES_BACKUP);
            $this->fail('replies_after_rename');
            $main = false; $backup = true;
        }
        if (! $main && $backup) {
            $this->createSqliteReplies($b2);
            $this->fail('replies_after_create');
        } elseif ($main && $backup && $this->sqliteRepliesState('pqr_replies') !== ($b2 ? 'b2' : 'legacy')) {
            throw new RuntimeException('Recuperación B-2 ambigua: principal y respaldo de respuestas tienen esquemas incompatibles.');
        }
        $comparisonColumns = ['pqr_id','user_id','body','is_draft','attachments','sent_at','created_at','updated_at'];
        if (Schema::hasColumn('pqr_replies', 'deleted_at') && Schema::hasColumn(self::REPLIES_BACKUP, 'deleted_at')) {
            $comparisonColumns[] = 'deleted_at';
        }
        $this->assertSubset('pqr_replies', self::REPLIES_BACKUP, $comparisonColumns);
        $columns = 'id,pqr_id,user_id,body,is_draft,attachments,sent_at,created_at,updated_at';
        if ($b2 && Schema::hasColumn(self::REPLIES_BACKUP, 'deleted_at')) $columns .= ',deleted_at';
        if ($this->sqliteFailurePoint === 'replies_during_copy') {
            DB::statement("INSERT INTO pqr_replies ({$columns}) SELECT {$columns} FROM ".self::REPLIES_BACKUP.' b WHERE NOT EXISTS (SELECT 1 FROM pqr_replies n WHERE n.id=b.id) LIMIT 1');
            $this->fail('replies_during_copy');
        }
        DB::statement("INSERT INTO pqr_replies ({$columns}) SELECT {$columns} FROM ".self::REPLIES_BACKUP.' b WHERE NOT EXISTS (SELECT 1 FROM pqr_replies n WHERE n.id=b.id)');
        $this->assertEqualCounts('pqr_replies', self::REPLIES_BACKUP);
        if (Schema::hasColumn('pqr_replies', 'deleted_at') && ! Schema::hasColumn(self::REPLIES_BACKUP, 'deleted_at') && DB::table('pqr_replies')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Recuperación B-2 ambigua: deleted_at no coincide exactamente con el respaldo previo.');
        }
        if (DB::select("PRAGMA foreign_key_check('pqr_replies')") !== []) {
            throw new RuntimeException('Recuperación B-2 ambigua: las relaciones de respuestas no son íntegras.');
        }
        $this->fail('replies_before_drop');
        DB::statement('DROP TABLE '.self::REPLIES_BACKUP);
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pqr_replies_official_unique ON pqr_replies(pqr_id,official_response_slot)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pqr_replies_pqr_id_id_unique ON pqr_replies(pqr_id,id)');
        if ($b2) DB::statement('CREATE INDEX IF NOT EXISTS pqr_replies_draft_deleted_index ON pqr_replies(pqr_id,is_draft,deleted_at)');
    }

    private function rebuildLedger(bool $b2): void
    {
        $main = Schema::hasTable('pqr_communication_operations');
        $backup = Schema::hasTable(self::LEDGER_BACKUP);
        if (! $main && ! $backup) throw new RuntimeException('Recuperación B-2 imposible: no existe ledger ni su respaldo específico.');
        if ($main && $this->sqliteLedgerState('pqr_communication_operations') === 'ambiguous') throw new RuntimeException('Recuperación B-2 ambigua: el esquema principal del ledger es parcial.');
        if ($backup && $this->sqliteLedgerState(self::LEDGER_BACKUP) === 'ambiguous') throw new RuntimeException('Recuperación B-2 ambigua: el respaldo del ledger es parcial.');
        if ($main && ! $backup
            && $this->sqliteLedgerState('pqr_communication_operations') === ($b2 ? 'b2' : 'legacy')
            && $this->sqliteLedgerReferencesCanonicalReplies('pqr_communication_operations')) return;
        if ($main && ! $backup) {
            DB::statement('ALTER TABLE pqr_communication_operations RENAME TO '.self::LEDGER_BACKUP);
            $this->fail('ledger_after_rename');
            $main = false; $backup = true;
        }
        if (! $main && $backup) {
            $this->createSqliteLedger($b2);
            $this->fail('ledger_after_create');
        } elseif ($main && $backup && $this->sqliteLedgerState('pqr_communication_operations') !== ($b2 ? 'b2' : 'legacy')) {
            throw new RuntimeException('Recuperación B-2 ambigua: principal y respaldo del ledger tienen esquemas incompatibles.');
        }
        $commonColumns = 'id,pqr_id,actor_id,actor_uuid,operation,idempotency_key,payload_sha256,result_code,status,cleanup_status,notification_status,pqr_reply_id,pqr_internal_comment_id,completed_at,created_at,updated_at';
        $columns = $commonColumns;
        if ($b2 && Schema::hasColumn(self::LEDGER_BACKUP, 'cleanup_manifest')) $columns .= ',cleanup_manifest';
        DB::statement("INSERT INTO pqr_communication_operations ({$columns}) SELECT {$columns} FROM ".self::LEDGER_BACKUP.' b WHERE NOT EXISTS (SELECT 1 FROM pqr_communication_operations n WHERE n.id=b.id)');
        $comparisonColumns = explode(',', $commonColumns);
        if (Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest') && Schema::hasColumn(self::LEDGER_BACKUP, 'cleanup_manifest')) {
            $comparisonColumns[] = 'cleanup_manifest';
        }
        $this->assertSubset('pqr_communication_operations', self::LEDGER_BACKUP, $comparisonColumns);
        $this->assertEqualCounts('pqr_communication_operations', self::LEDGER_BACKUP);
        if (Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest') !== Schema::hasColumn(self::LEDGER_BACKUP, 'cleanup_manifest')) {
            $manifestTable = Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest') ? 'pqr_communication_operations' : self::LEDGER_BACKUP;
            if (DB::table($manifestTable)->whereNotNull('cleanup_manifest')->exists()) {
                throw new RuntimeException('Recuperación B-2 ambigua: el manifiesto de limpieza diverge del respaldo.');
            }
        }
        if (DB::select("PRAGMA foreign_key_check('pqr_communication_operations')") !== []) {
            throw new RuntimeException('Recuperación B-2 ambigua: las relaciones del ledger no son íntegras.');
        }
        $this->fail('ledger_before_drop');
        DB::statement('DROP TABLE '.self::LEDGER_BACKUP);
        DB::statement('CREATE INDEX pqr_comm_pqr_operation_created_index ON pqr_communication_operations(pqr_id,operation,created_at)');
    }

    private function createSqliteReplies(bool $b2): void
    {
        $deleted = $b2 ? ', deleted_at DATETIME NULL, CONSTRAINT pqr_replies_deleted_draft_check CHECK (deleted_at IS NULL OR (is_draft=1 AND sent_at IS NULL))' : '';
        DB::statement("CREATE TABLE pqr_replies (id INTEGER PRIMARY KEY AUTOINCREMENT,pqr_id INTEGER NOT NULL,user_id INTEGER NULL,body TEXT NOT NULL,is_draft INTEGER NOT NULL DEFAULT 0,attachments TEXT NULL,sent_at DATETIME NULL,created_at DATETIME NULL,updated_at DATETIME NULL,official_response_slot INTEGER GENERATED ALWAYS AS (CASE WHEN is_draft=0 AND sent_at IS NOT NULL THEN 1 ELSE NULL END) STORED{$deleted},CONSTRAINT pqr_replies_draft_sent_check CHECK ((is_draft=1 AND sent_at IS NULL) OR (is_draft=0 AND sent_at IS NOT NULL)),FOREIGN KEY(pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL)");
    }

    private function createSqliteLedger(bool $b2): void
    {
        $ops = "'create_reply','create_internal_comment'".($b2 ? ",'create_draft','update_draft','send_draft','delete_draft','send_reply'" : '');
        $codes = "'reply_recorded','comment_recorded','operation_completed'".($b2 ? ",'draft_created','draft_updated','draft_sent','draft_deleted','reply_sent'" : '');
        $b2Matrix = $b2 ? " OR (operation='create_draft' AND result_code='draft_created' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='update_draft' AND result_code='draft_updated' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='send_draft' AND result_code='draft_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='delete_draft' AND result_code='draft_deleted' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='send_reply' AND result_code='reply_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)" : '';
        $manifestColumn = $b2 ? ',cleanup_manifest TEXT NULL' : '';
        $manifestCheck = $b2 ? ",CONSTRAINT pqr_comm_cleanup_manifest_check CHECK((cleanup_status='not_required' AND cleanup_manifest IS NULL) OR (cleanup_status IN ('pending','completed') AND cleanup_manifest IS NOT NULL AND json_valid(cleanup_manifest) AND json_type(cleanup_manifest)='array'))" : '';
        DB::statement("CREATE TABLE pqr_communication_operations(id INTEGER PRIMARY KEY AUTOINCREMENT,pqr_id INTEGER NOT NULL,actor_id INTEGER NULL,actor_uuid CHAR(36) NOT NULL,operation VARCHAR(50) NOT NULL,idempotency_key CHAR(36) NOT NULL,payload_sha256 CHAR(64) NOT NULL,result_code VARCHAR(40) NULL,status VARCHAR(20) NOT NULL,cleanup_status VARCHAR(20) NOT NULL{$manifestColumn},notification_status VARCHAR(20) NOT NULL,pqr_reply_id INTEGER NULL,pqr_internal_comment_id INTEGER NULL,completed_at DATETIME NULL,created_at DATETIME NULL,updated_at DATETIME NULL,CONSTRAINT pqr_comm_operation_check CHECK(operation IN ({$ops})),CONSTRAINT pqr_comm_status_check CHECK(status IN ('in_progress','completed')),CONSTRAINT pqr_comm_cleanup_check CHECK(cleanup_status IN ('not_required','pending','completed')){$manifestCheck},CONSTRAINT pqr_comm_notification_check CHECK(notification_status IN ('not_required','pending','completed','no_recipient')),CONSTRAINT pqr_comm_result_code_check CHECK(result_code IS NULL OR result_code IN ({$codes})),CONSTRAINT pqr_comm_payload_sha_check CHECK(LENGTH(payload_sha256)=64),CONSTRAINT pqr_comm_result_matrix_check CHECK((status='in_progress' AND result_code IS NULL AND completed_at IS NULL AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL) OR (status='completed' AND result_code IS NOT NULL AND completed_at IS NOT NULL AND ((operation='create_reply' AND ((result_code='reply_recorded' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL))) OR (operation='create_internal_comment' AND ((result_code='comment_recorded' AND pqr_internal_comment_id IS NOT NULL AND pqr_reply_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL))) {$b2Matrix}))),CONSTRAINT pqr_comm_idempotency_key_unique UNIQUE(idempotency_key),FOREIGN KEY(pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT,FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(pqr_id,pqr_reply_id) REFERENCES pqr_replies(pqr_id,id) ON DELETE RESTRICT,FOREIGN KEY(pqr_id,pqr_internal_comment_id) REFERENCES pqr_internal_comments(pqr_id,id) ON DELETE RESTRICT)");
    }

    private function replaceMysqlLedgerChecks(bool $b2): void
    {
        foreach ($this->mysqlLedgerChecks($b2) as $check => $expected) {
            $current = $this->mysqlCheckClause('pqr_communication_operations', $check);
            if ($current !== null && $this->mysqlExpressionsEqual($current, $expected)) {
                continue;
            }
            if ($current !== null) {
                DB::statement("ALTER TABLE pqr_communication_operations DROP CHECK {$check}");
            }
            DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT {$check} CHECK ({$expected})");
        }
    }

    private function applyMysqlReplySchema(): void
    {
        $column = Schema::hasColumn('pqr_replies', 'deleted_at');
        $index = $this->mysqlIndexExists('pqr_replies', 'pqr_replies_draft_deleted_index');
        $check = $this->mysqlCheckExists('pqr_replies', 'pqr_replies_deleted_draft_check');
        if (! $column && ($index || $check)) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: índice o CHECK existen sin deleted_at.');
        }
        if ($index && $this->mysqlIndexColumns('pqr_replies', 'pqr_replies_draft_deleted_index') !== ['pqr_id', 'is_draft', 'deleted_at']) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: el índice de borradores tiene columnas inesperadas.');
        }
        if (! $column) DB::statement('ALTER TABLE pqr_replies ADD deleted_at TIMESTAMP NULL AFTER sent_at');
        if (! $index) DB::statement('ALTER TABLE pqr_replies ADD INDEX pqr_replies_draft_deleted_index (pqr_id, is_draft, deleted_at)');
        if (! $check) DB::statement('ALTER TABLE pqr_replies ADD CONSTRAINT pqr_replies_deleted_draft_check CHECK ('.$this->mysqlReplyCheck().')');
    }

    private function removeMysqlReplySchema(): void
    {
        $column = Schema::hasColumn('pqr_replies', 'deleted_at');
        $index = $this->mysqlIndexExists('pqr_replies', 'pqr_replies_draft_deleted_index');
        $check = $this->mysqlCheckExists('pqr_replies', 'pqr_replies_deleted_draft_check');
        if (! $column && ($index || $check)) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo durante rollback: protecciones sin deleted_at.');
        }
        if ($check) DB::statement('ALTER TABLE pqr_replies DROP CHECK pqr_replies_deleted_draft_check');
        if ($index) DB::statement('ALTER TABLE pqr_replies DROP INDEX pqr_replies_draft_deleted_index');
        if ($column) DB::statement('ALTER TABLE pqr_replies DROP COLUMN deleted_at');
    }

    private function applyMysqlCleanupManifest(): void
    {
        if (! Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest')) {
            DB::statement('ALTER TABLE pqr_communication_operations ADD cleanup_manifest JSON NULL AFTER cleanup_status');
        }
        if (! $this->mysqlCheckExists('pqr_communication_operations', 'pqr_comm_cleanup_manifest_check')) {
            DB::statement('ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_cleanup_manifest_check CHECK ('.$this->mysqlCleanupManifestCheck().')');
        }
    }

    private function removeMysqlCleanupManifest(): void
    {
        if ($this->mysqlCheckExists('pqr_communication_operations', 'pqr_comm_cleanup_manifest_check')) {
            DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_cleanup_manifest_check');
        }
        if (Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest')) {
            DB::statement('ALTER TABLE pqr_communication_operations DROP COLUMN cleanup_manifest');
        }
    }

    private function preflightMysql(bool $targetB2): void
    {
        if (! Schema::hasTable('pqr_replies') || ! Schema::hasTable('pqr_communication_operations')) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: faltan tablas obligatorias antes del DDL.');
        }

        $column = Schema::hasColumn('pqr_replies', 'deleted_at');
        $index = $this->mysqlIndexExists('pqr_replies', 'pqr_replies_draft_deleted_index');
        $replyCheck = $this->mysqlCheckClause('pqr_replies', 'pqr_replies_deleted_draft_check');
        if (! $column && ($index || $replyCheck !== null)) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: existen protecciones de borrado sin deleted_at.');
        }
        if ($column && $this->mysqlColumnDefinition('pqr_replies', 'deleted_at') !== ['timestamp', 'YES']) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: deleted_at no coincide con la definición esperada.');
        }
        if ($index && $this->mysqlIndexColumns('pqr_replies', 'pqr_replies_draft_deleted_index') !== ['pqr_id', 'is_draft', 'deleted_at']) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: el índice de borradores no coincide exactamente.');
        }
        if ($replyCheck !== null && ! $this->mysqlExpressionsEqual($replyCheck, $this->mysqlReplyCheck())) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: el CHECK de borrado no coincide semánticamente.');
        }

        $manifestColumn = Schema::hasColumn('pqr_communication_operations', 'cleanup_manifest');
        $manifestCheck = $this->mysqlCheckClause('pqr_communication_operations', 'pqr_comm_cleanup_manifest_check');
        if (! $manifestColumn && $manifestCheck !== null) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: existe CHECK de limpieza sin manifiesto.');
        }
        if ($manifestColumn && $this->mysqlColumnDefinition('pqr_communication_operations', 'cleanup_manifest') !== ['json', 'YES']) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: cleanup_manifest no coincide con la definición esperada.');
        }
        if ($manifestCheck !== null && ! $this->mysqlExpressionsEqual($manifestCheck, $this->mysqlCleanupManifestCheck())) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: el CHECK del manifiesto no coincide semánticamente.');
        }

        $legacy = $this->mysqlLedgerChecks(false);
        $b2 = $this->mysqlLedgerChecks(true);
        foreach (array_keys($legacy) as $check) {
            $current = $this->mysqlCheckClause('pqr_communication_operations', $check);
            if ($current !== null
                && ! $this->mysqlExpressionsEqual($current, $legacy[$check])
                && ! $this->mysqlExpressionsEqual($current, $b2[$check])) {
                throw new RuntimeException("Estado MySQL B-2 ambiguo: {$check} no coincide con ninguna semántica conocida.");
            }
        }

        if ($column && DB::table('pqr_replies')->whereRaw('NOT ('.$this->mysqlReplyCheck().')')->exists()) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: existen respuestas incompatibles con deleted_at.');
        }
        if ($manifestColumn && DB::table('pqr_communication_operations')->whereRaw('NOT ('.$this->mysqlCleanupManifestCheck().')')->exists()) {
            throw new RuntimeException('Estado MySQL B-2 ambiguo: existen manifiestos de limpieza incompatibles.');
        }
        foreach ($this->mysqlLedgerChecks($targetB2) as $check => $expression) {
            if (DB::table('pqr_communication_operations')->whereRaw("NOT ({$expression})")->exists()) {
                throw new RuntimeException("Estado MySQL B-2 ambiguo: los datos no satisfacen {$check}.");
            }
        }
    }

    private function assertRollbackSafe(): void
    {
        foreach (['pqr_replies', self::REPLIES_BACKUP] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at') && DB::table($table)->whereNotNull('deleted_at')->exists()) {
                throw new RuntimeException('Rollback B-2 rechazado: existen borradores eliminados cuya trazabilidad debe conservarse.');
            }
        }
        foreach (['pqr_communication_operations', self::LEDGER_BACKUP] as $table) {
            if (! Schema::hasTable($table)) continue;
            if (DB::table($table)->whereIn('operation', $this->b2Operations())->exists()
                || (Schema::hasColumn($table, 'cleanup_manifest') && DB::table($table)->whereNotNull('cleanup_manifest')->exists())) {
                throw new RuntimeException('Rollback B-2 rechazado: existen operaciones o limpiezas reales cuya trazabilidad debe conservarse.');
            }
        }
    }

    /** @return array<string,string> */
    private function mysqlLedgerChecks(bool $b2): array
    {
        $ops = "'create_reply','create_internal_comment'".($b2 ? ",'create_draft','update_draft','send_draft','delete_draft','send_reply'" : '');
        $codes = "'reply_recorded','comment_recorded','operation_completed'".($b2 ? ",'draft_created','draft_updated','draft_sent','draft_deleted','reply_sent'" : '');
        $extra = $b2 ? " OR (operation='create_draft' AND result_code='draft_created' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='update_draft' AND result_code='draft_updated' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='send_draft' AND result_code='draft_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='delete_draft' AND result_code='draft_deleted' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (operation='send_reply' AND result_code='reply_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)" : '';

        return [
            'pqr_comm_operation_check' => "operation IN ({$ops})",
            'pqr_comm_result_code_check' => "result_code IS NULL OR result_code IN ({$codes})",
            'pqr_comm_result_matrix_check' => "(status='in_progress' AND result_code IS NULL AND completed_at IS NULL AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL) OR (status='completed' AND result_code IS NOT NULL AND completed_at IS NOT NULL AND ((operation='create_reply' AND ((result_code='reply_recorded' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL))) OR (operation='create_internal_comment' AND ((result_code='comment_recorded' AND pqr_internal_comment_id IS NOT NULL AND pqr_reply_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL))) {$extra}))",
        ];
    }

    private function mysqlReplyCheck(): string
    {
        return 'deleted_at IS NULL OR (is_draft = 1 AND sent_at IS NULL)';
    }

    private function mysqlCleanupManifestCheck(): string
    {
        return "(cleanup_status='not_required' AND cleanup_manifest IS NULL) OR (cleanup_status IN ('pending','completed') AND cleanup_manifest IS NOT NULL AND JSON_VALID(cleanup_manifest) AND JSON_TYPE(cleanup_manifest)='ARRAY')";
    }

    private function mysqlExpressionsEqual(string $actual, string $expected): bool
    {
        $actualDnf = $this->canonicalMysqlDnf($actual);
        $expectedDnf = $this->canonicalMysqlDnf($expected);

        return $actualDnf !== null
            && $expectedDnf !== null
            && $actualDnf === $expectedDnf;
    }

    /** @return list<string>|null */
    private function canonicalMysqlDnf(string $expression): ?array
    {
        $terms = $this->mysqlDnfTerms($expression);
        if ($terms === null) {
            return null;
        }
        $canonical = [];
        foreach ($terms as $atoms) {
            $atoms = array_values(array_unique($atoms));
            sort($atoms);
            $canonical[] = implode('&', $atoms);
        }
        $canonical = array_values(array_unique($canonical));
        sort($canonical);

        return $canonical;
    }

    /** @return list<list<string>>|null */
    private function mysqlDnfTerms(string $expression): ?array
    {
        $expression = $this->stripMysqlOuterParentheses(trim($expression));
        if ($expression === '') {
            return null;
        }
        $or = $this->splitMysqlBoolean($expression, 'or');
        if (count($or) > 1) {
            $terms = [];
            foreach ($or as $part) {
                $parsed = $this->mysqlDnfTerms($part);
                if ($parsed === null) {
                    return null;
                }
                $terms = array_merge($terms, $parsed);
            }

            return $terms;
        }
        $and = $this->splitMysqlBoolean($expression, 'and');
        if (count($and) > 1) {
            $terms = [[]];
            foreach ($and as $part) {
                $next = [];
                $parsed = $this->mysqlDnfTerms($part);
                if ($parsed === null) {
                    return null;
                }
                foreach ($terms as $left) {
                    foreach ($parsed as $right) {
                        $next[] = array_merge($left, $right);
                        if (count($next) > 256) {
                            return null;
                        }
                    }
                }
                $terms = $next;
            }

            return $terms;
        }

        $atom = $this->canonicalMysqlAtom($expression);

        return $atom === null || $atom === '' ? null : [[$atom]];
    }

    private function canonicalMysqlAtom(string $expression): ?string
    {
        $tokens = [];
        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];
            if (ctype_space($character)) {
                continue;
            }
            if ($character === '_' && preg_match('/\A_utf8mb4(?=(?:\\\\)?\')/i', substr($expression, $index), $match) === 1) {
                $index += strlen($match[0]) - 1;
                continue;
            }
            if ($character === "'" || ($character === '\\' && ($expression[$index + 1] ?? null) === "'")) {
                $literal = $this->consumeMysqlLiteral($expression, $index);
                if ($literal === null) {
                    return null;
                }
                $tokens[] = ['literal', bin2hex($literal)];
                continue;
            }
            if ($character === '`') {
                $closing = strpos($expression, '`', $index + 1);
                if ($closing === false || $closing === $index + 1) {
                    return null;
                }
                $identifier = strtolower(substr($expression, $index + 1, $closing - $index - 1));
                if (preg_match('/\A[a-z_][a-z0-9_]*\z/', $identifier) !== 1) {
                    return null;
                }
                $tokens[] = ['word', $identifier];
                $index = $closing;
                continue;
            }
            if (ctype_alpha($character) || $character === '_') {
                $start = $index;
                while ($index + 1 < $length && (ctype_alnum($expression[$index + 1]) || $expression[$index + 1] === '_')) {
                    $index++;
                }
                $tokens[] = ['word', strtolower(substr($expression, $start, $index - $start + 1))];
                continue;
            }
            if (ctype_digit($character)) {
                $start = $index;
                while ($index + 1 < $length && ctype_digit($expression[$index + 1])) {
                    $index++;
                }
                $tokens[] = ['number', substr($expression, $start, $index - $start + 1)];
                continue;
            }
            if (str_contains('=(),', $character)) {
                $tokens[] = ['symbol', $character];
                continue;
            }

            return null;
        }

        if (! $this->isAllowedMysqlAtom($tokens)) {
            return null;
        }

        return implode('|', array_map(fn (array $token) => $token[0].':'.$token[1], $tokens));
    }

    /** @param list<array{string,string}> $tokens */
    private function isAllowedMysqlAtom(array $tokens): bool
    {
        $identifiers = [
            'operation', 'result_code', 'status', 'completed_at', 'pqr_reply_id',
            'pqr_internal_comment_id', 'deleted_at', 'is_draft', 'sent_at',
            'cleanup_status', 'cleanup_manifest',
        ];
        $isIdentifier = fn (array $token): bool => $token[0] === 'word' && in_array($token[1], $identifiers, true);
        $isValue = fn (array $token): bool => in_array($token[0], ['literal', 'number'], true);
        $is = fn (int $index, string $type, string $value): bool => isset($tokens[$index]) && $tokens[$index] === [$type, $value];

        if (count($tokens) === 3 && $isIdentifier($tokens[0]) && $is(1, 'symbol', '=') && $isValue($tokens[2])) {
            return true;
        }
        if ($isIdentifier($tokens[0] ?? ['', '']) && $is(1, 'word', 'is')) {
            return count($tokens) === 3 && $is(2, 'word', 'null')
                || count($tokens) === 4 && $is(2, 'word', 'not') && $is(3, 'word', 'null');
        }
        if ($isIdentifier($tokens[0] ?? ['', '']) && $is(1, 'word', 'in') && $is(2, 'symbol', '(') && $is(count($tokens) - 1, 'symbol', ')')) {
            if (count($tokens) < 5 || ! $isValue($tokens[3])) {
                return false;
            }
            for ($index = 4; $index < count($tokens) - 1; $index += 2) {
                if (! $is($index, 'symbol', ',') || ! isset($tokens[$index + 1]) || ! $isValue($tokens[$index + 1])) {
                    return false;
                }
            }

            return count($tokens) % 2 === 1;
        }
        $function = $tokens[0][0] ?? null;
        $argument = $tokens[2] ?? ['', ''];
        $validCall = $function === 'word'
            && in_array($tokens[0][1], ['json_valid', 'json_type'], true)
            && $is(1, 'symbol', '(') && $isIdentifier($argument) && $is(3, 'symbol', ')');

        return $validCall && (
            count($tokens) === 4 && $tokens[0][1] === 'json_valid'
            || count($tokens) === 6 && $tokens[0][1] === 'json_type' && $is(4, 'symbol', '=') && $tokens[5][0] === 'literal'
        );
    }

    private function consumeMysqlLiteral(string $expression, int &$index): ?string
    {
        $length = strlen($expression);
        if ($expression[$index] === "'") {
            $literal = "'";
            for ($cursor = $index + 1; $cursor < $length; $cursor++) {
                if ($expression[$cursor] === '\\' && ($expression[$cursor + 1] ?? null) === "'") {
                    $literal .= "\\'";
                    $cursor++;
                    continue;
                }
                $literal .= $expression[$cursor];
                if ($expression[$cursor] !== "'") {
                    continue;
                }
                if (($expression[$cursor + 1] ?? null) === "'") {
                    $literal .= "'";
                    $cursor++;
                    continue;
                }
                $index = $cursor;

                return $literal;
            }

            return null;
        }

        $literal = "'";
        for ($cursor = $index + 2; $cursor < $length;) {
            if ($expression[$cursor] !== '\\') {
                if ($expression[$cursor] === "'") {
                    return null;
                }
                $literal .= $expression[$cursor++];
                continue;
            }
            $runStart = $cursor;
            while ($cursor < $length && $expression[$cursor] === '\\') {
                $cursor++;
            }
            $slashes = $cursor - $runStart;
            if (($expression[$cursor] ?? null) !== "'") {
                $literal .= str_repeat('\\', $slashes);
                continue;
            }
            if ($slashes === 1) {
                $literal .= "'";
                $index = $cursor;

                return $literal;
            }
            if ($slashes !== 3) {
                return null;
            }
            $literal .= "''";
            $cursor++;
        }

        return null;
    }

    /** @return list<string> */
    private function splitMysqlBoolean(string $expression, string $operator): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quoted = false;
        for ($index = 0, $length = strlen($expression); $index < $length; $index++) {
            if ($expression[$index] === "'" && ($index === 0 || $expression[$index - 1] !== '\\')) $quoted = ! $quoted;
            if ($quoted) continue;
            if ($expression[$index] === '(') $depth++;
            if ($expression[$index] === ')') $depth--;
            if ($depth !== 0 || strcasecmp(substr($expression, $index, strlen($operator)), $operator) !== 0) continue;
            $before = $index === 0 ? ' ' : $expression[$index - 1];
            $afterIndex = $index + strlen($operator);
            $after = $afterIndex >= $length ? ' ' : $expression[$afterIndex];
            if (preg_match('/[a-z0-9_]/i', $before) || preg_match('/[a-z0-9_]/i', $after)) continue;
            $parts[] = substr($expression, $start, $index - $start);
            $start = $afterIndex;
            $index = $afterIndex - 1;
        }
        $parts[] = substr($expression, $start);

        return array_map('trim', $parts);
    }

    private function stripMysqlOuterParentheses(string $expression): string
    {
        while (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            $depth = 0;
            $quoted = false;
            $wraps = true;
            for ($index = 0, $length = strlen($expression); $index < $length; $index++) {
                if ($expression[$index] === "'" && ($index === 0 || $expression[$index - 1] !== '\\')) $quoted = ! $quoted;
                if ($quoted) continue;
                if ($expression[$index] === '(') $depth++;
                if ($expression[$index] === ')') $depth--;
                if ($depth === 0 && $index < $length - 1) { $wraps = false; break; }
            }
            if (! $wraps) break;
            $expression = trim(substr($expression, 1, -1));
        }

        return $expression;
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('INDEX_NAME', $index)->exists();
    }

    private function mysqlCheckExists(string $table, string $check): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $check)->where('CONSTRAINT_TYPE', 'CHECK')->exists();
    }

    private function mysqlCheckClause(string $table, string $check): ?string
    {
        return DB::table('information_schema.CHECK_CONSTRAINTS as checks')
            ->join('information_schema.TABLE_CONSTRAINTS as constraints', function ($join) {
                $join->on('constraints.CONSTRAINT_SCHEMA', '=', 'checks.CONSTRAINT_SCHEMA')->on('constraints.CONSTRAINT_NAME', '=', 'checks.CONSTRAINT_NAME');
            })->where('constraints.CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('constraints.TABLE_NAME', $table)
            ->where('checks.CONSTRAINT_NAME', $check)->value('checks.CHECK_CLAUSE');
    }

    private function mysqlIndexColumns(string $table, string $index): array
    {
        return DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)->orderBy('SEQ_IN_INDEX')->pluck('COLUMN_NAME')->all();
    }

    /** @return array{string,string}|null */
    private function mysqlColumnDefinition(string $table, string $column): ?array
    {
        $definition = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)->where('COLUMN_NAME', $column)->first(['DATA_TYPE', 'IS_NULLABLE']);

        return $definition ? [strtolower($definition->DATA_TYPE), strtoupper($definition->IS_NULLABLE)] : null;
    }

    private function sqliteRepliesState(string $table): string
    {
        $sql = strtolower((string) DB::scalar("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]));
        $column = Schema::hasColumn($table, 'deleted_at');
        $check = str_contains($sql, 'pqr_replies_deleted_draft_check');
        if (! $column && ! $check) return 'legacy';
        if ($column && $check) return 'b2';

        return 'ambiguous';
    }

    private function sqliteLedgerState(string $table): string
    {
        $sql = strtolower((string) DB::scalar("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]));
        $b2Tokens = collect($this->b2Operations())->filter(fn (string $token) => str_contains($sql, $token))->count();
        $manifest = Schema::hasColumn($table, 'cleanup_manifest');
        $manifestCheck = str_contains($sql, 'pqr_comm_cleanup_manifest_check');
        if ($b2Tokens === 0 && ! $manifest && ! $manifestCheck) return 'legacy';
        if ($b2Tokens === count($this->b2Operations()) && $manifest && $manifestCheck) return 'b2';

        return 'ambiguous';
    }

    private function sqliteLedgerReferencesCanonicalReplies(string $table): bool
    {
        $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");
        $replyTargets = collect($foreignKeys)->pluck('table')->filter(fn (string $target) => str_contains($target, 'pqr_replies'))->unique()->values()->all();

        return $replyTargets === ['pqr_replies'];
    }
    private function b2Operations(): array { return ['create_draft','update_draft','send_draft','delete_draft','send_reply']; }
    private function assertEqualCounts(string $main, string $backup): void { if (DB::table($main)->count() !== DB::table($backup)->count()) throw new RuntimeException('Recuperación B-2 incompleta: el número de filas no coincide.'); }
    private function assertSubset(string $main, string $backup, array $columns): void { $diff = collect($columns)->map(fn($c) => "NOT (n.{$c} IS b.{$c})")->implode(' OR '); if (DB::table("{$main} as n")->leftJoin("{$backup} as b",'b.id','=','n.id')->whereNull('b.id')->orWhereRaw("({$diff})")->exists()) throw new RuntimeException('Recuperación B-2 ambigua: las filas de la tabla principal divergen del respaldo.'); }
    private function fail(string $point): void { if ($this->sqliteFailurePoint === $point) { $this->sqliteFailurePoint = null; throw new RuntimeException("Fallo SQLite B-2 inyectado en {$point}."); } }
};
