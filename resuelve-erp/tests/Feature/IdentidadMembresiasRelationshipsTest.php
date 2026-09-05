<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\MembresiaOrganizacion;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentidadMembresiasRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permisos_are_related_with_their_scope(): void
    {
        $rol = $this->createRol('gestor-organizacion', 'organizacion');
        $permiso = $this->createPermiso('organizacion.ver', 'organizacion');

        $rol->permisos()->attach($permiso, ['ambito_aplicable' => 'organizacion']);

        $this->assertTrue($rol->fresh()->permisos->contains($permiso));
        $this->assertTrue($permiso->fresh()->roles->contains($rol));
        $this->assertSame('organizacion', $rol->fresh()->permisos->first()->pivot->ambito_aplicable);
    }

    public function test_database_rejects_a_role_permission_with_incompatible_scopes(): void
    {
        $rol = $this->createRol('gestor-organizacion', 'organizacion');
        $permiso = $this->createPermiso('copropiedad.ver', 'copropiedad');

        $this->expectException(QueryException::class);

        DB::table('rol_permiso')->insert([
            'rol_id' => $rol->id,
            'permiso_id' => $permiso->id,
            'ambito_aplicable' => 'organizacion',
        ]);
    }

    public function test_organizacion_membership_is_unique_per_user_and_organizacion(): void
    {
        $usuario = User::factory()->create();
        $organizacion = $this->createOrganizacion();
        $this->createMembresiaOrganizacion($usuario, $organizacion);

        $this->expectException(QueryException::class);

        $this->createMembresiaOrganizacion($usuario, $organizacion);
    }

    public function test_copropiedad_membership_is_unique_per_user_and_copropiedad(): void
    {
        $usuario = User::factory()->create();
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $this->createMembresiaCopropiedad($usuario, $organizacion, $copropiedad);

        $this->expectException(QueryException::class);

        $this->createMembresiaCopropiedad($usuario, $organizacion, $copropiedad);
    }

    public function test_copropiedad_membership_rejects_a_copropiedad_from_another_organizacion(): void
    {
        $organizacion = $this->createOrganizacion('Organización A');
        $otraOrganizacion = $this->createOrganizacion('Organización B');
        $copropiedad = $this->createCopropiedad($otraOrganizacion);

        $this->expectException(QueryException::class);

        $this->createMembresiaCopropiedad(
            User::factory()->create(),
            $organizacion,
            $copropiedad
        );
    }

    public function test_role_assignments_are_separated_by_scope(): void
    {
        $usuario = User::factory()->create();
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $membresiaOrganizacion = $this->createMembresiaOrganizacion($usuario, $organizacion);
        $membresiaCopropiedad = $this->createMembresiaCopropiedad($usuario, $organizacion, $copropiedad);
        $rolOrganizacion = $this->createRol('gestor-organizacion', 'organizacion');
        $rolCopropiedad = $this->createRol('gestor-copropiedad', 'copropiedad');

        $membresiaOrganizacion->roles()->attach($rolOrganizacion, [
            'organizacion_id' => $organizacion->id,
            'ambito_rol' => 'organizacion',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);
        $membresiaCopropiedad->roles()->attach($rolCopropiedad, [
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $this->assertTrue($membresiaOrganizacion->fresh()->roles->contains($rolOrganizacion));
        $this->assertTrue($membresiaCopropiedad->fresh()->roles->contains($rolCopropiedad));
        $this->assertTrue($rolOrganizacion->fresh()->membresiasOrganizacion->contains($membresiaOrganizacion));
        $this->assertTrue($rolCopropiedad->fresh()->membresiasCopropiedad->contains($membresiaCopropiedad));

        $this->expectException(QueryException::class);

        $membresiaOrganizacion->roles()->attach($rolCopropiedad, [
            'organizacion_id' => $organizacion->id,
            'ambito_rol' => 'organizacion',
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);
    }

    public function test_relations_are_available_from_user_organizacion_and_copropiedad(): void
    {
        $creador = User::factory()->create();
        $usuario = User::factory()->create();
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $membresiaOrganizacion = $this->createMembresiaOrganizacion($usuario, $organizacion, $creador);
        $membresiaCopropiedad = $this->createMembresiaCopropiedad(
            $usuario,
            $organizacion,
            $copropiedad,
            $creador
        );

        $this->assertTrue($membresiaOrganizacion->usuario->is($usuario));
        $this->assertTrue($membresiaOrganizacion->organizacion->is($organizacion));
        $this->assertTrue($membresiaOrganizacion->creador->is($creador));
        $this->assertTrue($membresiaCopropiedad->usuario->is($usuario));
        $this->assertTrue($membresiaCopropiedad->organizacion->is($organizacion));
        $this->assertTrue($membresiaCopropiedad->copropiedad->is($copropiedad));
        $this->assertTrue($membresiaCopropiedad->creador->is($creador));
        $this->assertTrue($usuario->membresiasOrganizacion->contains($membresiaOrganizacion));
        $this->assertTrue($usuario->membresiasCopropiedad->contains($membresiaCopropiedad));
        $this->assertTrue($organizacion->membresiasOrganizacion->contains($membresiaOrganizacion));
        $this->assertTrue($organizacion->membresiasCopropiedad->contains($membresiaCopropiedad));
        $this->assertTrue($copropiedad->membresiasCopropiedad->contains($membresiaCopropiedad));
    }

    public function test_membership_vigency_fields_are_cast_as_datetimes(): void
    {
        $usuario = User::factory()->create();
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $vigenteHasta = now()->addMonth()->startOfSecond();
        $membresiaOrganizacion = $this->createMembresiaOrganizacion(
            $usuario,
            $organizacion,
            null,
            $vigenteHasta
        );
        $membresiaCopropiedad = $this->createMembresiaCopropiedad(
            $usuario,
            $organizacion,
            $copropiedad,
            null,
            $vigenteHasta
        );

        $this->assertInstanceOf(Carbon::class, $membresiaOrganizacion->vigente_desde);
        $this->assertInstanceOf(Carbon::class, $membresiaOrganizacion->vigente_hasta);
        $this->assertInstanceOf(Carbon::class, $membresiaCopropiedad->vigente_desde);
        $this->assertInstanceOf(Carbon::class, $membresiaCopropiedad->vigente_hasta);
        $this->assertTrue($membresiaOrganizacion->vigente_hasta->equalTo($vigenteHasta));
        $this->assertTrue($membresiaCopropiedad->vigente_hasta->equalTo($vigenteHasta));
    }

    public function test_active_authorization_still_uses_users_role(): void
    {
        $administrador = User::factory()->create(['role' => 'admin']);
        $residente = User::factory()->create(['role' => 'residente']);

        $this->assertSame('admin', $administrador->role);
        $this->assertTrue($administrador->isAdmin());
        $this->assertTrue($administrador->canViewAllPqrs());
        $this->assertTrue($administrador->canManagePqrs());
        $this->assertFalse($residente->isAdmin());
        $this->assertFalse($residente->canViewAllPqrs());
        $this->assertFalse($residente->canManagePqrs());
    }

    private function createRol(string $clave, string $ambito): Rol
    {
        return Rol::create([
            'clave' => $clave,
            'nombre' => $clave,
            'ambito_aplicable' => $ambito,
            'estado' => 'activo',
        ]);
    }

    private function createPermiso(string $clave, string $ambito): Permiso
    {
        return Permiso::create([
            'clave' => $clave,
            'modulo' => 'pqrs',
            'accion' => 'ver',
            'ambito_aplicable' => $ambito,
            'estado' => 'activo',
        ]);
    }

    private function createOrganizacion(string $nombre = 'Organización principal'): Organizacion
    {
        return Organizacion::create([
            'nombre' => $nombre,
            'estado' => 'activa',
        ]);
    }

    private function createCopropiedad(Organizacion $organizacion): Copropiedad
    {
        return Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad '.$organizacion->id,
            'estado' => 'activa',
        ]);
    }

    private function createMembresiaOrganizacion(
        User $usuario,
        Organizacion $organizacion,
        ?User $creador = null,
        mixed $vigenteHasta = null
    ): MembresiaOrganizacion {
        return MembresiaOrganizacion::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
            'vigente_hasta' => $vigenteHasta,
            'creada_por' => $creador?->id,
        ]);
    }

    private function createMembresiaCopropiedad(
        User $usuario,
        Organizacion $organizacion,
        Copropiedad $copropiedad,
        ?User $creador = null,
        mixed $vigenteHasta = null
    ): MembresiaCopropiedad {
        return MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
            'vigente_hasta' => $vigenteHasta,
            'creada_por' => $creador?->id,
        ]);
    }
}
