<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-PQRS-04 (Sprint 25): anulación trazable de respuestas oficiales.
 *
 * 1. `pqr_replies` recibe los campos de anulación (`anulada_at`,
 *    `anulada_por_user_id`, `motivo_anulacion`): la anulación no edita ni
 *    elimina la respuesta, solo fija su estado de anulación.
 * 2. El ledger `pqr_communication_operations` amplía sus restricciones para
 *    registrar la operación `annul_reply` con resultado `reply_annulled`
 *    (misma matriz de integridad que el resto del ciclo de respuestas).
 */
return new class extends Migration
{
    public function up(): void
    {
        // La columna de actor se añade sin clave foránea declarada: en
        // SQLite una FK obligaría a recrear la tabla y perdería sus CHECKs de
        // integridad (una respuesta oficial no puede borrarse). La relación
        // con `users` se garantiza en la aplicación y con el índice siguiente.
        Schema::table('pqr_replies', function ($table): void {
            $table->timestamp('anulada_at')->nullable()->after('sent_at');
            $table->unsignedBigInteger('anulada_por_user_id')->nullable()->after('anulada_at');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulada_por_user_id');
            $table->index('anulada_por_user_id');
        });

        $this->replaceLedgerChecks(withAnnul: true);
    }

    public function down(): void
    {
        $this->replaceLedgerChecks(withAnnul: false);

        Schema::table('pqr_replies', function ($table): void {
            $table->dropIndex(['anulada_por_user_id']);
            $table->dropColumn(['anulada_at', 'anulada_por_user_id', 'motivo_anulacion']);
        });
    }

    /**
     * Amplía (o revierte) las restricciones del ledger para la operación de
     * anulación, conservando la matriz de integridad existente.
     */
    private function replaceLedgerChecks(bool $withAnnul): void
    {
        $ops = "'create_reply','create_internal_comment','create_draft','update_draft','send_draft','delete_draft','send_reply'"
            .($withAnnul ? ",'annul_reply'" : '');
        $codes = "'reply_recorded','comment_recorded','operation_completed','draft_created','draft_updated','draft_sent','draft_deleted','reply_sent'"
            .($withAnnul ? ",'reply_annulled'" : '');
        $matrix = " OR (operation='create_draft' AND result_code='draft_created' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)"
            ." OR (operation='update_draft' AND result_code='draft_updated' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)"
            ." OR (operation='send_draft' AND result_code='draft_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)"
            ." OR (operation='delete_draft' AND result_code='draft_deleted' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)"
            ." OR (operation='send_reply' AND result_code='reply_sent' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)"
            .($withAnnul ? " OR (operation='annul_reply' AND result_code='reply_annulled' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL)" : '');

        $operationCheck = "operation IN ({$ops})";
        $resultCodeCheck = "result_code IS NULL OR result_code IN ({$codes})";
        $resultMatrixCheck = "(status='in_progress' AND result_code IS NULL AND completed_at IS NULL AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL)"
            ." OR (status='completed' AND result_code IS NOT NULL AND completed_at IS NOT NULL AND ((operation='create_reply' AND ((result_code='reply_recorded' AND pqr_reply_id IS NOT NULL AND pqr_internal_comment_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL)))"
            ." OR (operation='create_internal_comment' AND ((result_code='comment_recorded' AND pqr_internal_comment_id IS NOT NULL AND pqr_reply_id IS NULL) OR (result_code='operation_completed' AND pqr_reply_id IS NULL AND pqr_internal_comment_id IS NULL))){$matrix}))";

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite no permite alterar CHECK constraints: se reconstruye la
            // tabla con el mismo esquema y se conservan las filas.
            DB::statement("CREATE TABLE pqr_communication_operations_tmp(id INTEGER PRIMARY KEY AUTOINCREMENT,pqr_id INTEGER NOT NULL,actor_id INTEGER NULL,actor_uuid CHAR(36) NOT NULL,operation VARCHAR(50) NOT NULL,idempotency_key CHAR(36) NOT NULL,payload_sha256 CHAR(64) NOT NULL,result_code VARCHAR(40) NULL,status VARCHAR(20) NOT NULL,cleanup_status VARCHAR(20) NOT NULL,cleanup_manifest TEXT NULL,notification_status VARCHAR(20) NOT NULL,pqr_reply_id INTEGER NULL,pqr_internal_comment_id INTEGER NULL,completed_at DATETIME NULL,created_at DATETIME NULL,updated_at DATETIME NULL,CONSTRAINT pqr_comm_operation_check CHECK({$operationCheck}),CONSTRAINT pqr_comm_status_check CHECK(status IN ('in_progress','completed')),CONSTRAINT pqr_comm_cleanup_check CHECK(cleanup_status IN ('not_required','pending','completed')),CONSTRAINT pqr_comm_cleanup_manifest_check CHECK((cleanup_status='not_required' AND cleanup_manifest IS NULL) OR (cleanup_status IN ('pending','completed') AND cleanup_manifest IS NOT NULL AND json_valid(cleanup_manifest) AND json_type(cleanup_manifest)='array')),CONSTRAINT pqr_comm_notification_check CHECK(notification_status IN ('not_required','pending','completed','no_recipient')),CONSTRAINT pqr_comm_result_code_check CHECK({$resultCodeCheck}),CONSTRAINT pqr_comm_payload_sha_check CHECK(LENGTH(payload_sha256)=64),CONSTRAINT pqr_comm_result_matrix_check CHECK({$resultMatrixCheck}),CONSTRAINT pqr_comm_idempotency_key_unique UNIQUE(idempotency_key),FOREIGN KEY(pqr_id) REFERENCES pqrs(id) ON DELETE RESTRICT,FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(pqr_id,pqr_reply_id) REFERENCES pqr_replies(pqr_id,id) ON DELETE RESTRICT,FOREIGN KEY(pqr_id,pqr_internal_comment_id) REFERENCES pqr_internal_comments(pqr_id,id) ON DELETE RESTRICT)");
            DB::statement('INSERT INTO pqr_communication_operations_tmp (id, pqr_id, actor_id, actor_uuid, operation, idempotency_key, payload_sha256, result_code, status, cleanup_status, cleanup_manifest, notification_status, pqr_reply_id, pqr_internal_comment_id, completed_at, created_at, updated_at) SELECT id, pqr_id, actor_id, actor_uuid, operation, idempotency_key, payload_sha256, result_code, status, cleanup_status, cleanup_manifest, notification_status, pqr_reply_id, pqr_internal_comment_id, completed_at, created_at, updated_at FROM pqr_communication_operations');
            DB::statement('DROP TABLE pqr_communication_operations');
            DB::statement('ALTER TABLE pqr_communication_operations_tmp RENAME TO pqr_communication_operations');

            return;
        }

        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_operation_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_operation_check CHECK({$operationCheck})");
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_result_code_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_result_code_check CHECK({$resultCodeCheck})");
        DB::statement('ALTER TABLE pqr_communication_operations DROP CHECK pqr_comm_result_matrix_check');
        DB::statement("ALTER TABLE pqr_communication_operations ADD CONSTRAINT pqr_comm_result_matrix_check CHECK({$resultMatrixCheck})");
    }
};
