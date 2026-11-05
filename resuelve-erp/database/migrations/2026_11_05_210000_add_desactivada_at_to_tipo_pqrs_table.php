<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extensión aprobada de HU-CONF-03 (Sprint 24): baja lógica de Tipos de PQRS
 * mediante `desactivada_at` (autorización del Product Owner). Los registros
 * activos tienen `null`; al desactivar se asigna la fecha y al restaurar se
 * limpia. No se eliminan filas físicamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipo_pqrs', function (Blueprint $table): void {
            $table->timestamp('desactivada_at')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('tipo_pqrs', function (Blueprint $table): void {
            $table->dropColumn('desactivada_at');
        });
    }
};
