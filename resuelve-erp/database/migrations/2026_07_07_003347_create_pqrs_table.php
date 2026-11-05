<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pqrs', function (Blueprint $table) {
            $table->id();
            $table->string('asunto', 150);
            $table->text('descripcion');
            $table->date('fecha_radicacion');
            $table->date('fecha_limite_respuesta')->nullable();
            $table->enum('estado', ['radicada', 'en_revision', 'respondida', 'cerrada'])->default('radicada');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tipo_pqr_id')->constrained('tipo_pqrs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pqrs');
    }
};
