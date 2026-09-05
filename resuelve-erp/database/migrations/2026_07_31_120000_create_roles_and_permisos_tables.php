<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('ambito_aplicable', ['organizacion', 'copropiedad']);
            $table->string('estado', 30);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['id', 'ambito_aplicable']);
            $table->index(['ambito_aplicable', 'estado']);
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 150)->unique();
            $table->string('modulo', 100);
            $table->string('accion', 100);
            $table->text('descripcion')->nullable();
            $table->enum('ambito_aplicable', ['organizacion', 'copropiedad']);
            $table->string('estado', 30);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['id', 'ambito_aplicable']);
            $table->index(['modulo', 'estado']);
            $table->index(['ambito_aplicable', 'estado']);
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id');
            $table->foreignId('permiso_id');
            $table->enum('ambito_aplicable', ['organizacion', 'copropiedad']);

            $table->primary(['rol_id', 'permiso_id']);
            $table->index('permiso_id');
            $table->foreign(
                ['rol_id', 'ambito_aplicable'],
                'rol_permiso_rol_ambito_foreign'
            )
                ->references(['id', 'ambito_aplicable'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign(
                ['permiso_id', 'ambito_aplicable'],
                'rol_permiso_permiso_ambito_foreign'
            )
                ->references(['id', 'ambito_aplicable'])
                ->on('permisos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
