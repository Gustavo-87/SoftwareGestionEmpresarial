<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-USR-04 (Sprint 18): rol activo con segregación de funciones. La selección
 * persiste por Membresía/Copropiedad y se revalida al resolver el contexto: si
 * el Rol ya no está asignado, se descarta y la Membresía recupera la unión de
 * roles (compatibilidad por defecto de ADR-013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membresias_copropiedad', function (Blueprint $table) {
            $table->unsignedBigInteger('rol_activo_id')->nullable()->after('copropiedad_id');
            $table->foreign('rol_activo_id')
                ->references('id')
                ->on('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membresias_copropiedad', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rol_activo_id');
        });
    }
};
