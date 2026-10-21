<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * HU-USR-07 (Sprint 18): administración del catálogo de permisos. El registro
 * conserva nombre, descripción y módulo; la eliminación es lógica mediante
 * `estado` (la fila nunca se borra) y los permisos inactivos no conceden
 * acceso ni admiten nuevas asignaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('nombre', 150)->nullable()->after('name');
            $table->string('modulo', 100)->nullable()->after('nombre');
            $table->text('descripcion')->nullable()->after('modulo');
            $table->string('estado', 30)->default('activo')->after('descripcion');
            $table->index('estado');
        });

        DB::table('permissions')->orderBy('id')->get()->each(function ($permiso): void {
            DB::table('permissions')->where('id', $permiso->id)->update([
                'modulo' => Str::before((string) $permiso->name, '.'),
                'estado' => 'activo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropColumn(['nombre', 'modulo', 'descripcion', 'estado']);
        });
    }
};
