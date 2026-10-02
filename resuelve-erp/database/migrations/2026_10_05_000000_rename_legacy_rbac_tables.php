<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renombrar('roles', 'roles_contextuales');
        $this->renombrar('permisos', 'permisos_contextuales');
        $this->renombrar('rol_permiso', 'rol_permiso_contextual');
    }

    public function down(): void
    {
        $this->renombrar('roles_contextuales', 'roles');
        $this->renombrar('permisos_contextuales', 'permisos');
        $this->renombrar('rol_permiso_contextual', 'rol_permiso');
    }

    /**
     * Renombra una tabla si su nombre de origen existe.
     * MySQL reescribe automáticamente las claves foráneas que la referencian;
     * SQLite actualiza las referencias de las tablas hijas desde la versión 3.25.
     */
    private function renombrar(string $desde, string $hasta): void
    {
        if (Schema::hasTable($desde)) {
            Schema::rename($desde, $hasta);
        }
    }
};
