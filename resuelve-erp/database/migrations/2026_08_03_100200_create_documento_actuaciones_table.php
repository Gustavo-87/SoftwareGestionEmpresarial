<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $type = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';
        $id = DB::getDriverName() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $timestamp = DB::getDriverName() === 'sqlite' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        DB::statement("CREATE TABLE documento_actuaciones (
            id {$id}, documento_id {$type} NOT NULL, documento_version_id {$type} NULL,
            organizacion_id {$type} NOT NULL, copropiedad_id {$type} NOT NULL,
            accion VARCHAR(80) NOT NULL, detalle VARCHAR(1000) NULL, actor_user_id {$type} NULL,
            nombre_actor VARCHAR(180) NULL, created_at {$timestamp},
            CONSTRAINT documento_actuaciones_contexto_unique UNIQUE (id, documento_id, organizacion_id, copropiedad_id),
            FOREIGN KEY (documento_id, organizacion_id, copropiedad_id) REFERENCES documentos(id, organizacion_id, copropiedad_id) ON DELETE RESTRICT,
            FOREIGN KEY (documento_version_id, documento_id, organizacion_id, copropiedad_id) REFERENCES documento_versiones(id, documento_id, organizacion_id, copropiedad_id) ON DELETE RESTRICT,
            FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        DB::statement('CREATE INDEX documento_actuaciones_contexto_index ON documento_actuaciones (organizacion_id, copropiedad_id, documento_id)');
    }

    public function down(): void { DB::statement('DROP TABLE documento_actuaciones'); }
};
