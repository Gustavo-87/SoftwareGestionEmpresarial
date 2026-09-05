<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculos_unidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizacion_id');
            $table->unsignedBigInteger('copropiedad_id');
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('unidad_privada_id');
            $table->string('tipo_vinculo', 30);
            $table->string('estado', 20)->default('activo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->string('fuente', 50)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->foreign(
                ['persona_id', 'organizacion_id'],
                'vinculos_persona_organizacion_foreign'
            )
                ->references(['id', 'organizacion_id'])
                ->on('personas')
                ->onDelete('restrict');

            $table->foreign(
                ['unidad_privada_id', 'organizacion_id', 'copropiedad_id'],
                'vinculos_unidad_organizacion_copropiedad_foreign'
            )
                ->references(['id', 'organizacion_id', 'copropiedad_id'])
                ->on('unidades_privadas')
                ->onDelete('restrict');

            $table->unique(['persona_id', 'unidad_privada_id', 'tipo_vinculo', 'vigente_desde'], 'vinculos_persona_unidad_tipo_inicio_unique');
            $table->index(['organizacion_id', 'copropiedad_id', 'unidad_privada_id', 'estado'], 'idx_vinculos_cop_unidad');
            $table->index(['organizacion_id', 'persona_id', 'estado'], 'idx_vinculos_persona');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculos_unidad');
    }
};
