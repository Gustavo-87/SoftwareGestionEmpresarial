<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_organizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->unique();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->foreign('organizacion_id')
                ->references('id')
                ->on('organizaciones')
                ->restrictOnDelete();
        });

        Schema::create('configuraciones_copropiedad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id');
            $table->foreignId('copropiedad_id')->unique();
            $table->string('color_principal', 7)->default('#12382f');
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('dias_respuesta')->default(15);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(
                ['copropiedad_id', 'organizacion_id'],
                'config_copropiedad_contexto_unique'
            );
            $table->index(
                ['organizacion_id', 'copropiedad_id'],
                'config_copropiedad_contexto_index'
            );
            $table->foreign(
                ['copropiedad_id', 'organizacion_id'],
                'config_copropiedad_contexto_foreign'
            )
                ->references(['id', 'organizacion_id'])
                ->on('copropiedades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_copropiedad');
        Schema::dropIfExists('configuraciones_organizacion');
    }
};
