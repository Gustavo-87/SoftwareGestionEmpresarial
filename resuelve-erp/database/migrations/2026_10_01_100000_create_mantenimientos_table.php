<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mantenimientos')) {
            Schema::create('mantenimientos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organizacion_id')->constrained('organizaciones')->restrictOnDelete();
                $table->unsignedBigInteger('copropiedad_id');
                $table->foreign(['copropiedad_id', 'organizacion_id'])->references(['id', 'organizacion_id'])->on('copropiedades')->restrictOnDelete();
                $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('responsable_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('titulo', 180);
                $table->text('descripcion');
                $table->date('fecha_programada')->nullable();
                $table->enum('estado', ['pendiente', 'en_proceso', 'finalizado'])->default('pendiente');
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex('mantenimientos', 'mantenimiento_contexto_solicitante')) {
            Schema::table('mantenimientos', function (Blueprint $table): void {
                $table->index(['organizacion_id', 'copropiedad_id', 'solicitante_id'], 'mantenimiento_contexto_solicitante');
            });
        }
        foreach (['crear', 'ver_propias', 'ver_todas', 'gestionar'] as $accion) {
            DB::table('permisos')->updateOrInsert(['clave' => 'mantenimiento.'.$accion], [
                'modulo' => 'mantenimiento', 'accion' => $accion, 'descripcion' => 'Acceso contextual de mantenimiento.',
                'ambito_aplicable' => 'copropiedad', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
            $permiso = DB::table('permisos')->where('clave', 'mantenimiento.'.$accion)->value('id');
            $roles = in_array($accion, ['crear', 'ver_propias']) ? ['admin', 'gestor', 'residente'] : ['admin', 'gestor'];
            foreach (DB::table('roles')->whereIn('clave', $roles)->pluck('id') as $rol) {
                DB::table('rol_permiso')->updateOrInsert(['rol_id' => $rol, 'permiso_id' => $permiso], ['ambito_aplicable' => 'copropiedad']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
        $ids = DB::table('permisos')->where('modulo', 'mantenimiento')->pluck('id');
        DB::table('rol_permiso')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('id', $ids)->delete();
    }
};
