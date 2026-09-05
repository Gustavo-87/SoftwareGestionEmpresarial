<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = DB::getDriverName() === 'sqlite'
            ? 'CREATE TABLE documentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organizacion_id INTEGER NOT NULL,
                copropiedad_id INTEGER NULL,
                ambito VARCHAR(20) NOT NULL,
                propietario_documental_user_id INTEGER NOT NULL,
                tipo VARCHAR(30) NOT NULL,
                categoria VARCHAR(40) NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                descripcion TEXT NULL,
                nivel_acceso VARCHAR(20) NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT \'activo\',
                creado_por_user_id INTEGER NULL,
                archivado_por_user_id INTEGER NULL,
                archivado_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT documentos_ambito_check CHECK ((ambito = \'organizacion\' AND copropiedad_id IS NULL) OR (ambito = \'copropiedad\' AND copropiedad_id IS NOT NULL)),
                CONSTRAINT documentos_tipo_ambito_check CHECK (tipo NOT IN (\'reglamento\', \'manual_convivencia\') OR ambito = \'copropiedad\'),
                CONSTRAINT documentos_tipo_check CHECK (tipo IN (\'documento_general\', \'reglamento\', \'manual_convivencia\', \'acta\')),
                CONSTRAINT documentos_categoria_check CHECK (categoria IN (\'normativo\', \'administrativo\', \'gobierno_copropiedad\', \'contractual\', \'financiero\', \'comunicaciones\', \'otro\')),
                CONSTRAINT documentos_nivel_acceso_check CHECK (nivel_acceso IN (\'administrativo\', \'interno\', \'comunidad\')),
                CONSTRAINT documentos_estado_check CHECK (estado IN (\'activo\', \'archivado\')),
                CONSTRAINT documentos_contexto_unique UNIQUE (id, organizacion_id, copropiedad_id),
                FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE RESTRICT,
                FOREIGN KEY (copropiedad_id, organizacion_id) REFERENCES copropiedades(id, organizacion_id) ON DELETE RESTRICT,
                FOREIGN KEY (propietario_documental_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                FOREIGN KEY (creado_por_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (archivado_por_user_id) REFERENCES users(id) ON DELETE SET NULL
            )'
            : 'CREATE TABLE documentos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organizacion_id BIGINT UNSIGNED NOT NULL,
                copropiedad_id BIGINT UNSIGNED NULL,
                ambito VARCHAR(20) NOT NULL,
                propietario_documental_user_id BIGINT UNSIGNED NOT NULL,
                tipo VARCHAR(30) NOT NULL,
                categoria VARCHAR(40) NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                descripcion TEXT NULL,
                nivel_acceso VARCHAR(20) NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT \'activo\',
                creado_por_user_id BIGINT UNSIGNED NULL,
                archivado_por_user_id BIGINT UNSIGNED NULL,
                archivado_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT documentos_ambito_check CHECK ((ambito = \'organizacion\' AND copropiedad_id IS NULL) OR (ambito = \'copropiedad\' AND copropiedad_id IS NOT NULL)),
                CONSTRAINT documentos_tipo_ambito_check CHECK (tipo NOT IN (\'reglamento\', \'manual_convivencia\') OR ambito = \'copropiedad\'),
                CONSTRAINT documentos_tipo_check CHECK (tipo IN (\'documento_general\', \'reglamento\', \'manual_convivencia\', \'acta\')),
                CONSTRAINT documentos_categoria_check CHECK (categoria IN (\'normativo\', \'administrativo\', \'gobierno_copropiedad\', \'contractual\', \'financiero\', \'comunicaciones\', \'otro\')),
                CONSTRAINT documentos_nivel_acceso_check CHECK (nivel_acceso IN (\'administrativo\', \'interno\', \'comunidad\')),
                CONSTRAINT documentos_estado_check CHECK (estado IN (\'activo\', \'archivado\')),
                CONSTRAINT documentos_contexto_unique UNIQUE (id, organizacion_id, copropiedad_id),
                CONSTRAINT documentos_organizacion_foreign FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE RESTRICT,
                CONSTRAINT documentos_copropiedad_organizacion_foreign FOREIGN KEY (copropiedad_id, organizacion_id) REFERENCES copropiedades(id, organizacion_id) ON DELETE RESTRICT,
                CONSTRAINT documentos_propietario_foreign FOREIGN KEY (propietario_documental_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT documentos_creador_foreign FOREIGN KEY (creado_por_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT documentos_archivador_foreign FOREIGN KEY (archivado_por_user_id) REFERENCES users(id) ON DELETE SET NULL
            )';

        DB::statement($sql);
        DB::statement('CREATE INDEX documentos_contexto_estado_index ON documentos (organizacion_id, copropiedad_id, estado)');
    }

    public function down(): void { DB::statement('DROP TABLE documentos'); }
};
