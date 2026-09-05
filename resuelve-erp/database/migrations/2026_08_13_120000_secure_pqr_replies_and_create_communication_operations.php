<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEDGER = 'pqr_communication_operations';

    private const SQLITE_BACKUP = 'pqr_replies_c351a_backup';

    private ?string $sqliteFailurePoint = null;

    public function injectSqliteFailureAt(?string $point): void
    {
        $this->sqliteFailurePoint = $point;
    }

    public function up(): void
    {
        $this->preflight($this->repliesSourceTable());
        if (DB::getDriverName() === 'sqlite' && Schema::hasTable(self::SQLITE_BACKUP)) {
            $this->rebuildReplies(true);
        } elseif (! Schema::hasColumn('pqr_replies', 'official_response_slot')) {
            DB::getDriverName() === 'sqlite' ? $this->rebuildReplies(true) : $this->alterMysqlRepliesUp();
        }
        if (! Schema::hasTable(self::LEDGER)) {
            $this->createLedger();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::LEDGER) && DB::table(self::LEDGER)->exists()) {
            throw new RuntimeException('Rollback bloqueado: el ledger contiene operaciones reales y su trazabilidad no puede destruirse.');
        }
        if (Schema::hasTable(self::LEDGER)) {
            DB::statement('DROP TABLE '.self::LEDGER);
        }
        $this->dropReferenceIndexes();
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildReplies(false);
        } elseif (Schema::hasColumn('pqr_replies', 'official_response_slot')) {
            $this->alterMysqlRepliesDown();
        } elseif (DB::getDriverName() === 'mysql') {
            $this->restoreMysqlCascadeForeignKey();
        }
    }

    private function preflight(string $table): void
    {
        $failures = [];
        if (DB::table($table)->select('pqr_id')->where('is_draft', 0)->whereNotNull('sent_at')->groupBy('pqr_id')->havingRaw('COUNT(*) > 1')->limit(1)->get()->isNotEmpty()) {
            $failures[] = 'más de una respuesta oficial por PQRS';
        }
        if (DB::table($table)->where(fn ($q) => $q->where(fn ($q) => $q->where('is_draft', 1)->whereNotNull('sent_at'))->orWhere(fn ($q) => $q->where('is_draft', 0)->whereNull('sent_at')))->exists()) {
            $failures[] = 'combinaciones incoherentes de is_draft/sent_at';
        }
        if (DB::table($table)->whereNull('user_id')->exists()) {
            $failures[] = 'respuestas sin autor';
        }
        if (DB::table("{$table} as r")->leftJoin('pqrs as p', 'p.id', '=', 'r.pqr_id')->whereNull('p.id')->exists()) {
            $failures[] = 'respuestas huérfanas';
        }
        $invalidJson = DB::getDriverName() === 'sqlite' ? "attachments IS NOT NULL AND (json_valid(attachments) = 0 OR json_type(attachments) <> 'array')" : "attachments IS NOT NULL AND (JSON_VALID(attachments) = 0 OR JSON_TYPE(attachments) <> 'ARRAY')";
        if (DB::table($table)->whereRaw($invalidJson)->exists()) {
            $failures[] = 'adjuntos que no son arrays JSON válidos';
        }
        if (DB::table("{$table} as r")->join('pqrs as p', 'p.id', '=', 'r.pqr_id')->where('r.is_draft', 0)->whereNotNull('r.sent_at')->whereNotIn('p.estado', ['respondida', 'cerrada'])->exists()) {
            $failures[] = 'respuestas oficiales asociadas a estados incompatibles';
        }
        if ($failures !== []) {
            throw new RuntimeException('Preflight de comunicación rechazado: '.implode('; ', $failures).'.');
        }
    }

    private function alterMysqlRepliesUp(): void
    {
        $this->dropMysqlPqrForeignKey();
        DB::statement('ALTER TABLE pqr_replies ADD official_response_slot TINYINT GENERATED ALWAYS AS (CASE WHEN is_draft = 0 AND sent_at IS NOT NULL THEN 1 ELSE NULL END) STORED, ADD CONSTRAINT pqr_replies_draft_sent_check CHECK ((is_draft = 1 AND sent_at IS NULL) OR (is_draft = 0 AND sent_at IS NOT NULL)), ADD CONSTRAINT pqr_replies_official_unique UNIQUE (pqr_id, official_response_slot), ADD CONSTRAINT pqr_replies_pqr_id_foreign FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT');
    }

    private function alterMysqlRepliesDown(): void
    {
        $this->dropMysqlPqrForeignKey();
        if ($this->mysqlIndexExists('pqr_replies', 'pqr_replies_official_unique')) {
            DB::statement('ALTER TABLE pqr_replies DROP INDEX pqr_replies_official_unique');
        }
        if ($this->mysqlCheckExists('pqr_replies_draft_sent_check')) {
            DB::statement('ALTER TABLE pqr_replies DROP CHECK pqr_replies_draft_sent_check');
        }
        if (Schema::hasColumn('pqr_replies', 'official_response_slot')) {
            DB::statement('ALTER TABLE pqr_replies DROP COLUMN official_response_slot');
        }
        $this->restoreMysqlCascadeForeignKey();
    }

    private function restoreMysqlCascadeForeignKey(): void
    {
        $rule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', 'pqr_replies')->where('CONSTRAINT_NAME', 'pqr_replies_pqr_id_foreign')->value('DELETE_RULE');
        if ($rule === 'CASCADE') {
            return;
        }
        if ($rule !== null) {
            $this->dropMysqlPqrForeignKey();
        }
        DB::statement('ALTER TABLE pqr_replies ADD CONSTRAINT pqr_replies_pqr_id_foreign FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE CASCADE');
    }

    private function dropMysqlPqrForeignKey(): void
    {
        if ($this->mysqlForeignKeyExists('pqr_replies', 'pqr_replies_pqr_id_foreign')) {
            DB::statement('ALTER TABLE pqr_replies DROP FOREIGN KEY pqr_replies_pqr_id_foreign');
        }
    }

    private function rebuildReplies(bool $secure): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            $main = Schema::hasTable('pqr_replies');
            $backup = Schema::hasTable(self::SQLITE_BACKUP);
            if (! $main && ! $backup) {
                throw new RuntimeException('Recuperación SQLite imposible: no existe tabla principal ni respaldo C.3.5.1A.');
            }

            if ($main && ! $backup) {
                if ($this->sqliteRepliesIsDesired($secure)) {
                    return;
                }
                DB::statement('ALTER TABLE pqr_replies RENAME TO '.self::SQLITE_BACKUP);
                $this->failSqlite('after_rename');
                $main = false;
                $backup = true;
            }
            if (! $main && $backup) {
                $this->failSqlite('before_create');
                $this->createSqliteReplies($secure);
                $this->failSqlite('after_create');
            } elseif ($main && $backup && ! $this->sqliteRepliesIsDesired($secure)) {
                throw new RuntimeException('Recuperación SQLite ambigua: principal y respaldo existen, pero la principal no tiene el esquema objetivo.');
            }

            $this->assertSqliteMainIsConsistentSubsetOfBackup();
            $this->failSqlite('before_copy');
            $columns = 'id, pqr_id, user_id, body, is_draft, attachments, sent_at, created_at, updated_at';
            if ($this->sqliteFailurePoint === 'during_copy') {
                DB::statement('INSERT INTO pqr_replies ('.$columns.') SELECT '.$columns.' FROM '.self::SQLITE_BACKUP.' b WHERE NOT EXISTS (SELECT 1 FROM pqr_replies n WHERE n.id = b.id) LIMIT 1');
                $this->failSqlite('during_copy');
            }
            DB::statement('INSERT INTO pqr_replies ('.$columns.') SELECT '.$columns.' FROM '.self::SQLITE_BACKUP.' b WHERE NOT EXISTS (SELECT 1 FROM pqr_replies n WHERE n.id = b.id)');
            $this->failSqlite('after_copy');
            $this->assertSqliteTablesEqual();
            $this->failSqlite('before_drop_backup');
            DB::statement('DROP TABLE '.self::SQLITE_BACKUP);
            if ($secure && ! $this->indexExists('pqr_replies', 'pqr_replies_official_unique')) {
                DB::statement('CREATE UNIQUE INDEX pqr_replies_official_unique ON pqr_replies (pqr_id, official_response_slot)');
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function repliesSourceTable(): string
    {
        if (Schema::hasTable('pqr_replies')) {
            return 'pqr_replies';
        }
        if (DB::getDriverName() === 'sqlite' && Schema::hasTable(self::SQLITE_BACKUP)) {
            return self::SQLITE_BACKUP;
        }
        throw new RuntimeException('Preflight de comunicación rechazado: no existe tabla principal ni respaldo recuperable.');
    }

    private function createSqliteReplies(bool $secure): void
    {
        $slot = $secure ? ', official_response_slot INTEGER GENERATED ALWAYS AS (CASE WHEN is_draft = 0 AND sent_at IS NOT NULL THEN 1 ELSE NULL END) STORED' : '';
        $check = $secure ? ', CONSTRAINT pqr_replies_draft_sent_check CHECK ((is_draft = 1 AND sent_at IS NULL) OR (is_draft = 0 AND sent_at IS NOT NULL))' : '';
        $delete = $secure ? 'RESTRICT' : 'CASCADE';
        DB::statement("CREATE TABLE pqr_replies (id INTEGER PRIMARY KEY AUTOINCREMENT, pqr_id INTEGER NOT NULL, user_id INTEGER NULL, body TEXT NOT NULL, is_draft INTEGER NOT NULL DEFAULT 0, attachments TEXT NULL, sent_at DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL{$slot}{$check}, FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE {$delete}, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL)");
    }

    private function sqliteRepliesIsDesired(bool $secure): bool
    {
        return Schema::hasColumn('pqr_replies', 'official_response_slot') === $secure;
    }

    private function assertSqliteMainIsConsistentSubsetOfBackup(): void
    {
        $columns = ['pqr_id', 'user_id', 'body', 'is_draft', 'attachments', 'sent_at', 'created_at', 'updated_at'];
        $differences = collect($columns)->map(fn ($column) => "NOT (n.{$column} IS b.{$column})")->implode(' OR ');
        if (DB::table('pqr_replies as n')->leftJoin(self::SQLITE_BACKUP.' as b', 'b.id', '=', 'n.id')->whereNull('b.id')->orWhereRaw("({$differences})")->exists()) {
            throw new RuntimeException('Recuperación SQLite ambigua: la tabla principal no es un subconjunto íntegro del respaldo.');
        }
    }

    private function assertSqliteTablesEqual(): void
    {
        $expected = DB::table(self::SQLITE_BACKUP)->count();
        $actual = DB::table('pqr_replies')->count();
        if ($expected !== $actual) {
            throw new RuntimeException("Recuperación SQLite incompleta: se esperaban {$expected} filas y existen {$actual}.");
        }
        $this->assertSqliteMainIsConsistentSubsetOfBackup();
    }

    private function failSqlite(string $point): void
    {
        if ($this->sqliteFailurePoint === $point) {
            $this->sqliteFailurePoint = null;
            throw new RuntimeException("Fallo SQLite inyectado en {$point}.");
        }
    }

    private function createLedger(): void
    {
        $id = DB::getDriverName() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $bigint = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';
        $timestamp = DB::getDriverName() === 'sqlite' ? 'DATETIME' : 'TIMESTAMP';
        $this->createReferenceIndexes();
        DB::statement("CREATE TABLE pqr_communication_operations (
            id {$id}, pqr_id {$bigint} NOT NULL, actor_id {$bigint} NULL, actor_uuid CHAR(36) NOT NULL,
            operation VARCHAR(50) NOT NULL, idempotency_key CHAR(36) NOT NULL, payload_sha256 CHAR(64) NOT NULL,
            result_code VARCHAR(40) NULL,
            status VARCHAR(20) NOT NULL, cleanup_status VARCHAR(20) NOT NULL, notification_status VARCHAR(20) NOT NULL,
            pqr_reply_id {$bigint} NULL, pqr_internal_comment_id {$bigint} NULL, completed_at {$timestamp} NULL,
            created_at {$timestamp} NULL, updated_at {$timestamp} NULL,
            CONSTRAINT pqr_comm_operation_check CHECK (operation IN ('create_reply', 'create_internal_comment')),
            CONSTRAINT pqr_comm_status_check CHECK (status IN ('in_progress', 'completed')),
            CONSTRAINT pqr_comm_cleanup_check CHECK (cleanup_status IN ('not_required', 'pending', 'completed')),
            CONSTRAINT pqr_comm_notification_check CHECK (notification_status IN ('not_required', 'pending', 'completed', 'no_recipient')),
            CONSTRAINT pqr_comm_result_code_check CHECK (result_code IS NULL OR result_code IN ('reply_recorded', 'comment_recorded', 'operation_completed')),
            CONSTRAINT pqr_comm_payload_sha_check CHECK (LENGTH(payload_sha256) = 64),
            CONSTRAINT pqr_comm_result_matrix_check CHECK (
                (status = 'in_progress' AND result_code IS NULL AND completed_at IS NULL AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL)
                OR (status = 'completed' AND completed_at IS NOT NULL AND result_code IS NOT NULL AND (
                    (operation = 'create_reply' AND pqr_internal_comment_id IS NULL AND ((result_code = 'reply_recorded' AND pqr_reply_id IS NOT NULL) OR (result_code = 'operation_completed' AND pqr_reply_id IS NULL)))
                    OR (operation = 'create_internal_comment' AND pqr_reply_id IS NULL AND ((result_code = 'comment_recorded' AND pqr_internal_comment_id IS NOT NULL) OR (result_code = 'operation_completed' AND pqr_internal_comment_id IS NULL)))
                ))
            ),
            CONSTRAINT pqr_comm_idempotency_key_unique UNIQUE (idempotency_key),
            FOREIGN KEY (pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (pqr_id, pqr_reply_id) REFERENCES pqr_replies(pqr_id, id) ON DELETE RESTRICT,
            FOREIGN KEY (pqr_id, pqr_internal_comment_id) REFERENCES pqr_internal_comments(pqr_id, id) ON DELETE RESTRICT
        )");
        DB::statement('CREATE INDEX pqr_comm_pqr_operation_created_index ON pqr_communication_operations (pqr_id, operation, created_at)');
    }

    private function createReferenceIndexes(): void
    {
        if (! $this->indexExists('pqr_replies', 'pqr_replies_pqr_id_id_unique')) {
            DB::statement('CREATE UNIQUE INDEX pqr_replies_pqr_id_id_unique ON pqr_replies (pqr_id, id)');
        }
        if (! $this->indexExists('pqr_internal_comments', 'pqr_comments_pqr_id_id_unique')) {
            DB::statement('CREATE UNIQUE INDEX pqr_comments_pqr_id_id_unique ON pqr_internal_comments (pqr_id, id)');
        }
    }

    private function dropReferenceIndexes(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (! $this->mysqlIndexExists('pqr_replies', 'pqr_replies_pqr_id_foreign')) {
                DB::statement('CREATE INDEX pqr_replies_pqr_id_foreign ON pqr_replies (pqr_id)');
            }
            if (! $this->mysqlIndexExists('pqr_internal_comments', 'pqr_internal_comments_pqr_id_foreign')) {
                DB::statement('CREATE INDEX pqr_internal_comments_pqr_id_foreign ON pqr_internal_comments (pqr_id)');
            }
        }
        if ($this->indexExists('pqr_replies', 'pqr_replies_pqr_id_id_unique')) {
            DB::statement(DB::getDriverName() === 'sqlite' ? 'DROP INDEX pqr_replies_pqr_id_id_unique' : 'ALTER TABLE pqr_replies DROP INDEX pqr_replies_pqr_id_id_unique');
        }
        if ($this->indexExists('pqr_internal_comments', 'pqr_comments_pqr_id_id_unique')) {
            DB::statement(DB::getDriverName() === 'sqlite' ? 'DROP INDEX pqr_comments_pqr_id_id_unique' : 'ALTER TABLE pqr_internal_comments DROP INDEX pqr_comments_pqr_id_id_unique');
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return $this->mysqlIndexExists($table, $name);
        }

        return collect(DB::select("PRAGMA index_list('{$table}')"))->contains(fn ($index) => $index->name === $name);
    }

    private function mysqlForeignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->where('CONSTRAINT_TYPE', 'FOREIGN KEY')->exists();
    }

    private function mysqlIndexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)->where('INDEX_NAME', $name)->exists();
    }

    private function mysqlCheckExists(string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('CONSTRAINT_NAME', $name)->where('CONSTRAINT_TYPE', 'CHECK')->exists();
    }
};
