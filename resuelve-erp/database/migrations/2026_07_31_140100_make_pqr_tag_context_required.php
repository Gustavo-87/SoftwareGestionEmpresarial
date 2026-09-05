<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tagSinContexto = DB::table('pqr_tags')
            ->whereNull('organizacion_id')
            ->orWhereNull('copropiedad_id')
            ->exists();
        $pivotSinContexto = DB::table('pqr_pqr_tag')
            ->whereNull('organizacion_id')
            ->orWhereNull('copropiedad_id')
            ->exists();

        if ($tagSinContexto || $pivotSinContexto) {
            throw new RuntimeException(
                'No es posible exigir el contexto de etiquetas: existen etiquetas o asociaciones sin contexto completo.'
            );
        }

        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable(false)->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable(false)->change();
        });
        Schema::table('pqr_pqr_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable(false)->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pqr_pqr_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
    }
};
