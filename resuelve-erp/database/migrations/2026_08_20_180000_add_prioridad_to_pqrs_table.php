<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->string('prioridad', 10)->default('media')->after('estado');
        });

        // CHECK constraint omitido en migración por incompatibilidad con SQLite.
        // La validación de valores ('alta', 'media', 'baja') se garantiza mediante:
        //   - Pqr::booted() — lanza InvalidArgumentException;
        //   - Request validation en controladores;
        //   - Pruebas existentes en PqrPrioridadTest.
    }

    public function down(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->dropColumn('prioridad');
        });
    }
};
