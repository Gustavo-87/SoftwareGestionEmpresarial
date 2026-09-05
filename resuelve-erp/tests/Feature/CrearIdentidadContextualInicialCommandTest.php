<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearIdentidadContextualInicialCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_complete_compatibility_catalog_from_empty_tables(): void
    {
        $this->createContext();
        User::factory()->create(['role' => 'admin']);

        $this->artisan('resuelve:crear-identidad-contextual-inicial')
            ->expectsOutputToContain('Roles creados: 5')
            ->expectsOutputToContain('Permisos creados: 18')
            ->expectsOutputToContain('Relaciones creadas: 50')
            ->assertSuccessful();

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permisos', 18);
        $this->assertDatabaseCount('rol_permiso', 50);
        $this->assertDatabaseHas('roles', [
            'clave' => 'admin',
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);
        $this->assertDatabaseHas('permisos', [
            'clave' => 'pqrs.ver_todas',
            'ambito_aplicable' => 'copropiedad',
        ]);
        $this->assertDatabaseHas('permisos', [
            'clave' => 'documentos.consultar',
            'ambito_aplicable' => 'copropiedad',
        ]);
    }

    public function test_second_execution_does_not_create_duplicates(): void
    {
        $this->createContext();
        User::factory()->create(['role' => 'gestor']);

        $this->artisan('resuelve:crear-identidad-contextual-inicial')->assertSuccessful();
        $counts = $this->identityCounts();

        $this->artisan('resuelve:crear-identidad-contextual-inicial')
            ->expectsOutputToContain('Roles creados: 0')
            ->expectsOutputToContain('Roles existentes: 5')
            ->expectsOutputToContain('Permisos creados: 0')
            ->expectsOutputToContain('Permisos existentes: 18')
            ->expectsOutputToContain('Relaciones creadas: 0')
            ->expectsOutputToContain('Relaciones existentes: 50')
            ->expectsOutputToContain('Membresias creadas: 0')
            ->expectsOutputToContain('Membresias existentes: 1')
            ->expectsOutputToContain('Asignaciones creadas: 0')
            ->expectsOutputToContain('Asignaciones existentes: 1')
            ->assertSuccessful();

        $this->assertSame($counts, $this->identityCounts());
    }

    public function test_creates_membership_and_equivalent_role_for_every_recognized_legacy_role(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $legacyRoles = ['admin', 'gestor', 'apoyo', 'auditor', 'residente'];
        $users = collect($legacyRoles)->mapWithKeys(
            fn (string $role) => [$role => User::factory()->create(['role' => $role])]
        );
        $originalRoles = $users->map->role->all();

        $this->artisan('resuelve:crear-identidad-contextual-inicial')->assertSuccessful();

        foreach ($users as $legacyRole => $user) {
            $membership = MembresiaCopropiedad::query()
                ->where('usuario_id', $user->id)
                ->where('organizacion_id', $organizacion->id)
                ->where('copropiedad_id', $copropiedad->id)
                ->firstOrFail();

            $this->assertDatabaseHas('membresia_copropiedad_rol', [
                'membresia_copropiedad_id' => $membership->id,
                'rol_id' => Rol::query()->where('clave', $legacyRole)->value('id'),
                'organizacion_id' => $organizacion->id,
                'copropiedad_id' => $copropiedad->id,
                'ambito_rol' => 'copropiedad',
                'estado' => 'activa',
                'vigente_hasta' => null,
            ]);
        }

        $this->assertDatabaseCount('membresias_copropiedad', 5);
        $this->assertDatabaseCount('membresia_copropiedad_rol', 5);
        $this->assertDatabaseCount('membresias_organizacion', 0);
        $this->assertSame($originalRoles, $users->map(fn (User $user) => $user->fresh()->role)->all());
    }

    public function test_reports_unknown_roles_without_creating_membership_or_equivalence(): void
    {
        $this->createContext();
        $unknown = User::factory()->create(['role' => 'supervisor']);

        $this->artisan('resuelve:crear-identidad-contextual-inicial')
            ->expectsOutputToContain(
                "Usuario {$unknown->id} ({$unknown->email}) omitido: rol desconocido 'supervisor'."
            )
            ->expectsOutputToContain('Usuarios omitidos: 1')
            ->expectsOutputToContain('Inconsistencias: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('roles', ['clave' => 'supervisor']);
        $this->assertDatabaseMissing('membresias_copropiedad', ['usuario_id' => $unknown->id]);
    }

    public function test_recovers_catalog_membership_and_assignment_from_partial_state(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $user = User::factory()->create(['role' => 'residente']);
        Rol::create([
            'clave' => 'residente',
            'nombre' => 'Nombre anterior',
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'inactivo',
        ]);
        Permiso::create([
            'clave' => 'pqrs.listar',
            'modulo' => 'anterior',
            'accion' => 'anterior',
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'inactivo',
        ]);
        $membership = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);

        $this->artisan('resuelve:crear-identidad-contextual-inicial')->assertSuccessful();

        $this->assertSame('Residente', Rol::query()->where('clave', 'residente')->value('nombre'));
        $this->assertSame('pqrs', Permiso::query()->where('clave', 'pqrs.listar')->value('modulo'));
        $this->assertDatabaseCount('membresias_copropiedad', 1);
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membership->id,
            'rol_id' => Rol::query()->where('clave', 'residente')->value('id'),
            'estado' => 'activa',
        ]);
    }

    public function test_rolls_back_every_change_when_an_active_assignment_diverges(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $user = User::factory()->create(['role' => 'admin']);
        $admin = $this->createRole('admin');
        $gestor = $this->createRole('gestor');
        $membership = MembresiaCopropiedad::create([
            'usuario_id' => $user->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
        ]);
        $this->createAssignment($membership, $gestor, $organizacion, $copropiedad);

        $before = $this->identityCounts();

        $this->artisan('resuelve:crear-identidad-contextual-inicial')
            ->expectsOutputToContain("El Usuario {$user->id} tiene una asignación activa diferente de users.role.")
            ->assertFailed();

        $this->assertSame($before, $this->identityCounts());
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertDatabaseMissing('permisos', []);
        $this->assertSame($admin->id, Rol::query()->where('clave', 'admin')->value('id'));
    }

    public function test_missing_sprint_one_context_fails_without_partial_catalog(): void
    {
        SiteSetting::create([
            'nombre_conjunto' => 'Sin contexto',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        User::factory()->create(['role' => 'admin']);

        $this->artisan('resuelve:crear-identidad-contextual-inicial')
            ->expectsOutputToContain('SiteSetting no está contextualizado')
            ->assertFailed();

        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('permisos', 0);
        $this->assertDatabaseCount('membresias_copropiedad', 0);
    }

    private function createContext(): array
    {
        $organizacion = Organizacion::create([
            'nombre' => 'Organización inicial',
            'estado' => 'activa',
        ]);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad inicial',
            'estado' => 'activa',
        ]);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Copropiedad inicial',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        return compact('organizacion', 'copropiedad');
    }

    private function createRole(string $clave): Rol
    {
        return Rol::create([
            'clave' => $clave,
            'nombre' => ucfirst($clave),
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);
    }

    private function createAssignment(
        MembresiaCopropiedad $membership,
        Rol $rol,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): void {
        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membership->id,
            'rol_id' => $rol->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function identityCounts(): array
    {
        return collect([
            'roles',
            'permisos',
            'rol_permiso',
            'membresias_organizacion',
            'membresias_copropiedad',
            'membresia_organizacion_rol',
            'membresia_copropiedad_rol',
        ])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }
}
