<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->boolean('activo')->default(true)->after('color');
            $table->index(['organizacion_id', 'copropiedad_id', 'activo'], 'pqr_tags_context_activo_index');
        });
    }

    public function down(): void
    {
        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->dropIndex('pqr_tags_context_activo_index');
            $table->dropColumn('activo');
        });
    }
};
