<?php

namespace Tests\Feature;

use App\Application\Autorizacion\VerificadorEquivalenciaRbacSpatie;
use App\Application\Roles\CrearRol;
use App\Models\Copropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class RolesAsignacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function operador($organizacion, Copropiedad $copropiedad, array $permisos, string $rol): array
    {
        $usuario = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, $rol, $permisos);

        return [$usuario, $contexto];
    }

    public function test_asignacion_y_revocacion_validas_con_espejo_auditoria_y_diagnostico(): void
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();
        [$operador, $contexto] = $this->operador(
            $organizacion, $copropiedad,
            ['usuarios.gestionar', 'roles.gestionar', 'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'informes.exportar', 'notificaciones.consultar'],
            'admin',
        );
        $target = User::factory()->create();
        [$membresia] = $this->createContextualIdentity($target, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);
        $filasLegadoPrevias = \DB::table('membresia_copropiedad_rol')->where('membresia_copropiedad_id', $membresia->id)->count();

        // Rol personalizado (sin contraparte legada): se asigna sin forzar el espejo.
        $personalizado = app(CrearRol::class)->ejecutar($operador, $contexto, [
            'nombre' => 'Coordinador especial',
            'permisos' => ['pqrs.listar'],
        ]);
        $this->actingAsContextual($operador)
            ->post(route('users.roles.store', $target), ['rol_id' => $personalizado->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $target->id, 'role_id' => $personalizado->id, 'copropiedad_id' => $copropiedad->id,
        ]);
        $this->assertSame(
            $filasLegadoPrevias,
            \DB::table('membresia_copropiedad_rol')->where('membresia_copropiedad_id', $membresia->id)->count(),
            'Un rol personalizado no debe forzarse al espejo legado.'
        );

        // Rol base con contraparte legada (matriz contenida en la del operador): asignación con espejo.
        $auditor = SpatieRole::findByName('auditor', 'web');
        $auditorLegado = (int) \App\Models\Rol::query()->where('clave', 'auditor')->value('id');
        $this->post(route('users.roles.store', $target), ['rol_id' => $auditor->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id, 'rol_id' => $auditorLegado, 'estado' => 'activa',
        ]);
        $this->assertSame([], app(VerificadorEquivalenciaRbacSpatie::class)->divergencias());

        // Duplicado rechazado.
        $this->post(route('users.roles.store', $target), ['rol_id' => $auditor->id])
            ->assertSessionHasErrors('rol_id');

        // Revocación: sale de ambos lados y se audita.
        $this->delete(route('users.roles.destroy', [$target, $auditor->id]))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id, 'rol_id' => $auditorLegado, 'estado' => 'activa',
        ]);
        $this->assertSame([], app(VerificadorEquivalenciaRbacSpatie::class)->divergencias());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'usuario.rol.asignar']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operador->id, 'action' => 'usuario.rol.revocar']);
    }

    public function test_cross_copropiedad_rol_de_otro_equipo_y_membresia_invalida(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        [$operadorA, $contextoA] = $this->operador($organizacion, $copropiedadA, ['usuarios.gestionar', 'roles.gestionar', 'pqrs.listar'], 'admin');
        [$operadorB, $contextoB] = $this->operador($organizacion, $copropiedadB, ['usuarios.gestionar', 'roles.gestionar', 'pqrs.listar'], 'gestor');
        $rolB = app(CrearRol::class)->ejecutar($operadorB, $contextoB, ['nombre' => 'Rol de B', 'permisos' => ['pqrs.listar']]);

        $enB = User::factory()->create();
        $this->createContextualIdentity($enB, $organizacion, $copropiedadB, 'residente', ['pqrs.listar']);

        // Usuario de otra Copropiedad: sin membresía en el contexto actual.
        $this->actingAsContextual($operadorA)
            ->post(route('users.roles.store', $enB), ['rol_id' => $rolB->id])
            ->assertSessionHasErrors('usuario');

        // Rol de otro equipo: rechazado en backend.
        $enA = User::factory()->create();
        $this->createContextualIdentity($enA, $organizacion, $copropiedadA, 'residente', ['pqrs.listar']);
        $this->post(route('users.roles.store', $enA), ['rol_id' => $rolB->id])->assertForbidden();

        // Membresía no vigente: rechazada con un rol válido del propio equipo.
        $rolA = app(CrearRol::class)->ejecutar($operadorA, $contextoA, ['nombre' => 'Rol de A', 'permisos' => ['pqrs.listar']]);
        $inactivo = User::factory()->create();
        $this->createContextualIdentity($inactivo, $organizacion, $copropiedadA, 'residente', ['pqrs.listar'], 'inactiva');
        $this->post(route('users.roles.store', $inactivo), ['rol_id' => $rolA->id])
            ->assertSessionHasErrors('usuario');
    }

    public function test_anti_escalada_no_permite_asignar_roles_fuera_del_conjunto_del_operador(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$operador, $contexto] = $this->operador($organizacion, $copropiedad, ['usuarios.gestionar', 'pqrs.listar'], 'admin');
        Permission::findOrCreate('pqrs.eliminar', 'web');
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $elevado = app(CrearRol::class)->ejecutar($plataforma, null, [
            'nombre' => 'Rol elevado',
            'permisos' => ['pqrs.eliminar'],
        ]);
        $target = User::factory()->create();
        $this->createContextualIdentity($target, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);

        $this->actingAsContextual($operador)
            ->post(route('users.roles.store', $target), ['rol_id' => $elevado->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $target->id, 'role_id' => $elevado->id]);
    }

    public function test_proteccion_de_ultima_capacidad_administrativa(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Permission::findOrCreate('usuarios.gestionar', 'web');
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $rolCapacidad = app(CrearRol::class)->ejecutar($plataforma, null, [
            'nombre' => 'Capacidad administrativa', 'permisos' => ['usuarios.gestionar'],
        ]);
        $target = User::factory()->create();
        [$membresiaTarget] = $this->createContextualIdentity($target, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);
        $segundo = User::factory()->create();
        [$membresiaSegundo] = $this->createContextualIdentity($segundo, $organizacion, $copropiedad, 'lector', ['pqrs.listar']);

        // Único titular (vía caso de uso con autoridad de plataforma).
        app(\App\Application\Roles\AsignarRolUsuario::class)->ejecutar($plataforma, null, $membresiaTarget, (int) $rolCapacidad->id);

        // Autorrevocación del último titular: bloqueada.
        $this->actingAsContextual($target)
            ->delete(route('users.roles.destroy', [$target, $rolCapacidad->id]))
            ->assertSessionHasErrors('rol');

        // Con dos titulares, revocar uno es válido.
        $this->post(route('users.roles.store', $segundo), ['rol_id' => $rolCapacidad->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->delete(route('users.roles.destroy', [$segundo, $rolCapacidad->id]))
            ->assertRedirect()->assertSessionHas('success');

        // El último titular vuelve a quedar protegido (autoridad de plataforma incluida).
        try {
            app(\App\Application\Roles\RevocarRolUsuario::class)->ejecutar($plataforma, null, $membresiaTarget, (int) $rolCapacidad->id);
            $this->fail('Debía bloquearse la revocación de la última capacidad administrativa.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('rol', $e->errors());
        }
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $target->id, 'role_id' => $rolCapacidad->id]);
    }

    public function test_403_sin_permiso_usuarios_gestionar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$conPermiso] = $this->operador($organizacion, $copropiedad, ['usuarios.gestionar', 'roles.gestionar', 'pqrs.listar'], 'admin');
        [$sinPermiso] = $this->operador($organizacion, $copropiedad, ['pqrs.listar'], 'lector');
        $target = User::factory()->create();
        $this->createContextualIdentity($target, $organizacion, $copropiedad, 'residente', ['pqrs.listar']);
        $rol = app(CrearRol::class)->ejecutar($conPermiso, $this->contextoDe($conPermiso, $organizacion, $copropiedad), [
            'nombre' => 'Rol común', 'permisos' => ['pqrs.listar'],
        ]);

        $this->actingAsContextual($sinPermiso);
        $this->post(route('users.roles.store', $target), ['rol_id' => $rol->id])->assertForbidden();
        $this->delete(route('users.roles.destroy', [$target, $rol->id]))->assertForbidden();
    }

    private function contextoDe(User $usuario, $organizacion, Copropiedad $copropiedad)
    {
        return app(\App\Application\Contexto\ContextResolver::class)
            ->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);
    }
}
