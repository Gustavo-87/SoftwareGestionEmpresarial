<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_conjunto');
            $table->string('nit', 40)->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('color_principal', 7)->default('#12382f');
            $table->unsignedSmallInteger('dias_respuesta')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
