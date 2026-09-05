<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->onDelete('restrict');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('tipo_persona', 30)->default('natural');
            $table->string('nombre_razon_social');
            $table->string('identificacion', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('estado', 20)->default('activa');
            $table->timestamp('desactivada_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'organizacion_id']);
            $table->unique(['organizacion_id', 'identificacion']);
            $table->index(['organizacion_id', 'estado']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
