<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pqrs', function (Blueprint $table): void {
            $table->unique(['id', 'organizacion_id', 'copropiedad_id'], 'pqrs_context_unique');
        });

        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->foreignId('organizacion_id')->nullable()->after('id');
            $table->foreignId('copropiedad_id')->nullable()->after('organizacion_id');

            $table->dropUnique('pqr_tags_name_unique');
            $table->unique(['id', 'organizacion_id', 'copropiedad_id'], 'pqr_tags_context_unique');
            $table->unique(['copropiedad_id', 'name'], 'pqr_tags_copropiedad_name_unique');
            $table->index(['organizacion_id', 'copropiedad_id'], 'pqr_tags_context_index');
            $table->foreign('organizacion_id')
                ->references('id')
                ->on('organizaciones')
                ->restrictOnDelete();
            $table->foreign(['copropiedad_id', 'organizacion_id'])
                ->references(['id', 'organizacion_id'])
                ->on('copropiedades')
                ->restrictOnDelete();
        });

        Schema::table('pqr_pqr_tag', function (Blueprint $table): void {
            $table->foreignId('organizacion_id')->nullable()->after('pqr_tag_id');
            $table->foreignId('copropiedad_id')->nullable()->after('organizacion_id');

            $table->index(['organizacion_id', 'copropiedad_id', 'pqr_tag_id'], 'pqr_pqr_tag_context_tag_index');
            $table->foreign(['pqr_id', 'organizacion_id', 'copropiedad_id'], 'pqr_pqr_tag_pqr_context_foreign')
                ->references(['id', 'organizacion_id', 'copropiedad_id'])
                ->on('pqrs')
                ->cascadeOnDelete();
            $table->foreign(['pqr_tag_id', 'organizacion_id', 'copropiedad_id'], 'pqr_pqr_tag_tag_context_foreign')
                ->references(['id', 'organizacion_id', 'copropiedad_id'])
                ->on('pqr_tags')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('pqr_tags')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException(
                'No es posible restaurar la unicidad global de etiquetas: existen nombres repetidos entre Copropiedades.'
            );
        }

        Schema::table('pqr_pqr_tag', function (Blueprint $table): void {
            $table->dropForeign('pqr_pqr_tag_pqr_context_foreign');
            $table->dropForeign('pqr_pqr_tag_tag_context_foreign');
            $table->dropIndex('pqr_pqr_tag_context_tag_index');
            $table->dropColumn(['organizacion_id', 'copropiedad_id']);
        });

        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->dropForeign(['copropiedad_id', 'organizacion_id']);
            $table->dropForeign(['organizacion_id']);
            $table->dropIndex('pqr_tags_context_index');
            $table->dropUnique('pqr_tags_copropiedad_name_unique');
            $table->dropUnique('pqr_tags_context_unique');
            $table->unique('name');
            $table->dropColumn(['organizacion_id', 'copropiedad_id']);
        });

        Schema::table('pqrs', function (Blueprint $table): void {
            $table->dropUnique('pqrs_context_unique');
        });
    }
};
