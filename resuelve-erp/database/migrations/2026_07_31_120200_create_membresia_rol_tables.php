<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membresia_organizacion_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membresia_organizacion_id');
            $table->foreignId('rol_id');
            $table->foreignId('organizacion_id');
            $table->enum('ambito_rol', ['organizacion', 'copropiedad']);
            $table->string('estado', 30);
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->foreignId('asignado_por')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['membresia_organizacion_id', 'estado'], 'memb_org_rol_estado_index');
            $table->index(['organizacion_id', 'rol_id', 'estado'], 'org_rol_estado_index');
            $table->foreign(
                ['membresia_organizacion_id', 'organizacion_id'],
                'memb_org_rol_membresia_foreign'
            )
                ->references(['id', 'organizacion_id'])
                ->on('membresias_organizacion')
                ->restrictOnDelete();
            $table->foreign(
                ['rol_id', 'ambito_rol'],
                'memb_org_rol_rol_foreign'
            )
                ->references(['id', 'ambito_aplicable'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign('asignado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('membresia_copropiedad_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membresia_copropiedad_id');
            $table->foreignId('rol_id');
            $table->foreignId('organizacion_id');
            $table->foreignId('copropiedad_id');
            $table->enum('ambito_rol', ['organizacion', 'copropiedad']);
            $table->string('estado', 30);
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->foreignId('asignado_por')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(
                ['membresia_copropiedad_id', 'estado'],
                'memb_cop_rol_estado_index'
            );
            $table->index(
                ['organizacion_id', 'copropiedad_id', 'rol_id', 'estado'],
                'copropiedad_rol_estado_index'
            );
            $table->foreign(
                ['membresia_copropiedad_id', 'organizacion_id', 'copropiedad_id'],
                'memb_cop_rol_membresia_foreign'
            )
                ->references(['id', 'organizacion_id', 'copropiedad_id'])
                ->on('membresias_copropiedad')
                ->restrictOnDelete();
            $table->foreign(
                ['rol_id', 'ambito_rol'],
                'memb_cop_rol_rol_foreign'
            )
                ->references(['id', 'ambito_aplicable'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign('asignado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE membresia_organizacion_rol
                ADD CONSTRAINT memb_org_rol_ambito_check CHECK (ambito_rol = 'organizacion')"
            );
            DB::statement(
                "ALTER TABLE membresia_copropiedad_rol
                ADD CONSTRAINT memb_cop_rol_ambito_check CHECK (ambito_rol = 'copropiedad')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('membresia_copropiedad_rol');
        Schema::dropIfExists('membresia_organizacion_rol');
    }
};
