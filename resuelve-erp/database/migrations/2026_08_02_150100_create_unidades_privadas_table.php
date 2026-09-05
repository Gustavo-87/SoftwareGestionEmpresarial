<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_privadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizacion_id');
            $table->unsignedBigInteger('copropiedad_id');
            $table->string('codigo', 50);
            $table->string('numero_nombre', 100)->nullable();
            $table->string('torre_bloque', 50)->nullable();
            $table->string('tipo', 30)->nullable();
            $table->string('estado', 20)->default('activa');
            $table->timestamp('desactivada_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['copropiedad_id', 'organizacion_id'],
                'unidades_copropiedad_organizacion_foreign'
            )
                ->references(['id', 'organizacion_id'])
                ->on('copropiedades')
                ->onDelete('restrict');

            $table->unique(['id', 'organizacion_id', 'copropiedad_id']);
            $table->unique(['copropiedad_id', 'codigo']);
            $table->index(['organizacion_id', 'copropiedad_id', 'estado']);
            $table->index(['copropiedad_id', 'torre_bloque', 'numero_nombre'], 'idx_unidades_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_privadas');
    }
};
