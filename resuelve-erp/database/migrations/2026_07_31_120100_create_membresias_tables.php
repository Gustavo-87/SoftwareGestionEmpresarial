<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membresias_organizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id');
            $table->foreignId('organizacion_id');
            $table->string('estado', 30);
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->foreignId('creada_por')->nullable();
            $table->text('motivo_terminacion')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['usuario_id', 'organizacion_id']);
            $table->unique(['id', 'organizacion_id']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['usuario_id', 'estado']);
            $table->index(['vigente_hasta', 'estado']);
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('organizacion_id')
                ->references('id')
                ->on('organizaciones')
                ->restrictOnDelete();
            $table->foreign('creada_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('membresias_copropiedad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id');
            $table->foreignId('organizacion_id');
            $table->foreignId('copropiedad_id');
            $table->string('estado', 30);
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->foreignId('creada_por')->nullable();
            $table->text('motivo_terminacion')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['usuario_id', 'copropiedad_id']);
            $table->unique(
                ['id', 'organizacion_id', 'copropiedad_id'],
                'memb_copropiedad_contexto_unique'
            );
            $table->index(
                ['organizacion_id', 'copropiedad_id', 'estado'],
                'memb_copropiedad_contexto_estado_index'
            );
            $table->index(['usuario_id', 'estado']);
            $table->index(['vigente_hasta', 'estado']);
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(
                ['copropiedad_id', 'organizacion_id'],
                'memb_copropiedad_copropiedad_foreign'
            )
                ->references(['id', 'organizacion_id'])
                ->on('copropiedades')
                ->restrictOnDelete();
            $table->foreign('creada_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membresias_copropiedad');
        Schema::dropIfExists('membresias_organizacion');
    }
};
