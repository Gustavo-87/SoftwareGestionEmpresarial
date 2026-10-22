<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19 (HU-PQRS-03, HU-MAN-03/04): integración de PQRS y mantenimiento.
 *
 * - `pqr_id`: vínculo de origen (una PQRS puede originar varios mantenimientos).
 * - `idempotency_key`: operación idempotente; doble clic, reenvío o concurrencia
 *   con la misma clave no duplica el mantenimiento, mientras que cada acción
 *   explícita usa una clave nueva.
 * - `resultado_publico` / `resultado_interno`: separación aprobada entre la
 *   información comunicable y la interna del personal.
 * - Registro del resultado y de la finalización para trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mantenimientos', function (Blueprint $table): void {
            $table->foreignId('pqr_id')->nullable()->after('copropiedad_id')
                ->constrained('pqrs')->restrictOnDelete();
            $table->string('idempotency_key', 36)->nullable()->after('descripcion');
            $table->text('resultado_publico')->nullable()->after('idempotency_key');
            $table->text('resultado_interno')->nullable()->after('resultado_publico');
            $table->foreignId('resultado_registrado_por_user_id')->nullable()->after('resultado_interno')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('resultado_registrado_at')->nullable()->after('resultado_registrado_por_user_id');
            $table->timestamp('finalizado_at')->nullable()->after('resultado_registrado_at');

            $table->unique('idempotency_key', 'mantenimiento_idempotency_key_unique');
            $table->index(['pqr_id', 'estado'], 'mantenimiento_pqr_estado_index');
        });
    }

    public function down(): void
    {
        Schema::table('mantenimientos', function (Blueprint $table): void {
            $table->dropIndex('mantenimiento_pqr_estado_index');
            $table->dropUnique('mantenimiento_idempotency_key_unique');
            $table->dropConstrainedForeignId('resultado_registrado_por_user_id');
            $table->dropConstrainedForeignId('pqr_id');
            $table->dropColumn([
                'idempotency_key',
                'resultado_publico',
                'resultado_interno',
                'resultado_registrado_at',
                'finalizado_at',
            ]);
        });
    }
};
