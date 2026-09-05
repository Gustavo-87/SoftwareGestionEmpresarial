<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('pqrs')
            ->whereNull('organizacion_id')
            ->orWhereNull('copropiedad_id')
            ->exists()) {
            throw new \RuntimeException(
                'No se puede exigir el contexto: existen PQRS sin Organización o Copropiedad.'
            );
        }

        Schema::table('pqrs', function (Blueprint $table) {
            $table->unsignedBigInteger('organizacion_id')->nullable(false)->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
    }
};
