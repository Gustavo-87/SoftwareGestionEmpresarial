<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->unsignedBigInteger('organizacion_id')->nullable();
            $table->unsignedBigInteger('copropiedad_id')->nullable();
            $table->timestamps();
            $table->index(['organizacion_id', 'copropiedad_id', 'created_at'], 'activity_contexto_fecha');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('organizacion_id')->nullable();
            $table->unsignedBigInteger('copropiedad_id')->nullable();
            $table->index(['organizacion_id', 'copropiedad_id', 'created_at'], 'audit_contexto_fecha');
        });
        // El contexto histórico desconocido permanece sin asignar y solo es
        // consultable por la autoridad de plataforma; no se infiere por actor.
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_contexto_fecha');
            $table->dropColumn(['organizacion_id', 'copropiedad_id']);
        });
    }
};
