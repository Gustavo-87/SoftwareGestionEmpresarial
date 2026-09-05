<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('identificacion_tributaria', 40)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('estado', 30);
            $table->timestamp('desactivada_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index('estado');
            $table->index('nombre');
        });

        Schema::create('copropiedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id');
            $table->string('nombre');
            $table->string('nit', 40)->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('estado', 30);
            $table->timestamp('desactivada_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['id', 'organizacion_id']);
            $table->unique(['organizacion_id', 'nit']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'nombre']);
            $table->foreign('organizacion_id')
                ->references('id')
                ->on('organizaciones')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copropiedades');
        Schema::dropIfExists('organizaciones');
    }
};
