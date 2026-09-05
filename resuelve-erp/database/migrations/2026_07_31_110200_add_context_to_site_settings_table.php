<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->foreignId('organizacion_id')->nullable()->after('id');
            $table->foreignId('copropiedad_id')->nullable()->after('organizacion_id');

            $table->index(['organizacion_id', 'copropiedad_id']);
            $table->foreign('organizacion_id')
                ->references('id')
                ->on('organizaciones')
                ->restrictOnDelete();
            $table->foreign(['copropiedad_id', 'organizacion_id'])
                ->references(['id', 'organizacion_id'])
                ->on('copropiedades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropForeign(['copropiedad_id', 'organizacion_id']);
            $table->dropForeign(['organizacion_id']);
            $table->dropIndex(['organizacion_id', 'copropiedad_id']);
            $table->dropColumn(['organizacion_id', 'copropiedad_id']);
        });
    }
};
