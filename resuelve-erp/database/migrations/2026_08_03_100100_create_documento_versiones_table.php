<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $type = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';
        $id = DB::getDriverName() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $timestamp = DB::getDriverName() === 'sqlite' ? 'DATETIME' : 'TIMESTAMP';
        $sql = "CREATE TABLE documento_versiones (
            id {$id}, documento_id {$type} NOT NULL, organizacion_id {$type} NOT NULL, copropiedad_id {$type} NOT NULL,
            numero INTEGER NOT NULL, estado VARCHAR(30) NOT NULL DEFAULT 'borrador', origen VARCHAR(20) NOT NULL DEFAULT 'usuario',
            nombre_original VARCHAR(255) NOT NULL, ruta_archivo VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL,
            extension VARCHAR(20) NOT NULL, tamano_bytes BIGINT NOT NULL, hash_sha256 CHAR(64) NOT NULL,
            vigente_desde DATE NULL, vigente_hasta DATE NULL, sustituye_version_id {$type} NULL,
            cargada_por_user_id {$type} NULL, nombre_cargador VARCHAR(180) NULL,
            sometida_por_user_id {$type} NULL, nombre_sometedor VARCHAR(180) NULL, sometida_at {$timestamp} NULL,
            aprobada_por_user_id {$type} NULL, nombre_aprobador VARCHAR(180) NULL, aprobada_at {$timestamp} NULL,
            rechazada_por_user_id {$type} NULL, nombre_rechazador VARCHAR(180) NULL, rechazada_at {$timestamp} NULL,
            observacion_rechazo TEXT NULL,
            pendiente_documento_id {$type} GENERATED ALWAYS AS (CASE WHEN estado = 'pendiente_aprobacion' THEN documento_id ELSE NULL END) STORED,
            created_at {$timestamp} NULL, updated_at {$timestamp} NULL,
            CONSTRAINT documento_versiones_estado_check CHECK (estado IN ('borrador', 'pendiente_aprobacion', 'aprobada', 'rechazada')),
            CONSTRAINT documento_versiones_origen_check CHECK (origen IN ('usuario', 'sistema', 'importado')),
            CONSTRAINT documento_versiones_tamano_check CHECK (tamano_bytes >= 0),
            CONSTRAINT documento_versiones_vigencia_check CHECK (vigente_hasta IS NULL OR vigente_desde IS NULL OR vigente_hasta >= vigente_desde),
            CONSTRAINT documento_versiones_numero_unique UNIQUE (documento_id, numero),
            CONSTRAINT documento_versiones_hash_unique UNIQUE (documento_id, hash_sha256),
            CONSTRAINT documento_versiones_pendiente_unique UNIQUE (pendiente_documento_id),
            CONSTRAINT documento_versiones_sustituye_unique UNIQUE (sustituye_version_id),
            CONSTRAINT documento_versiones_contexto_unique UNIQUE (id, documento_id, organizacion_id, copropiedad_id),
            FOREIGN KEY (documento_id, organizacion_id, copropiedad_id) REFERENCES documentos(id, organizacion_id, copropiedad_id) ON DELETE RESTRICT,
            FOREIGN KEY (sustituye_version_id, documento_id, organizacion_id, copropiedad_id) REFERENCES documento_versiones(id, documento_id, organizacion_id, copropiedad_id) ON DELETE RESTRICT,
            FOREIGN KEY (cargada_por_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (sometida_por_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (aprobada_por_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (rechazada_por_user_id) REFERENCES users(id) ON DELETE SET NULL
        )";

        DB::statement($sql);
        DB::statement('CREATE INDEX documento_versiones_contexto_estado_index ON documento_versiones (organizacion_id, copropiedad_id, estado)');
    }

    public function down(): void { DB::statement('DROP TABLE documento_versiones'); }
};
