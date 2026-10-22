<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19 (HU-DOC-03): evidencia documental de mantenimientos.
 *
 * La evidencia fija una versión exacta (`documento_version_id`) sin alterar el
 * ciclo documental: aunque existan versiones posteriores, la prueba utilizada
 * permanece inmutable. La combinación única impide vínculos duplicados por
 * doble clic, reenvío o concurrencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimiento_documento_evidencia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mantenimiento_id')->constrained('mantenimientos')->restrictOnDelete();
            $table->foreignId('documento_id')->constrained('documentos')->restrictOnDelete();
            $table->foreignId('documento_version_id')->constrained('documento_versiones')->restrictOnDelete();
            $table->foreignId('vinculado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mantenimiento_id', 'documento_version_id'], 'mantenimiento_evidencia_version_unica');
            $table->index(['documento_id', 'documento_version_id'], 'mantenimiento_evidencia_documento_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimiento_documento_evidencia');
    }
};
