<?php

namespace Tests\Feature;

use App\Application\Identidad\SincronizarIdentidadContextualUsuario;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class SincronizarIdentidadContextualUsuarioTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_user_creation_synchronizes_copropiedad_membership_and_role(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->prepareIdentity(['residente']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nueva Residente',
            'email' => 'nueva@example.com',
            'role' => 'residente',
            'tower' => 'D',
            'unit' => '401',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
        ])->assertRedirect(route('users.index'));

        $usuario = User::query()->where('email', 'nueva@example.com')->firstOrFail();
        $membresia = MembresiaCopropiedad::query()->where('usuario_id', $usuario->id)->firstOrFail();

        $this->assertSame('residente', $usuario->role);
        $this->assertSame($organizacion->id, $membresia->organizacion_id);
        $this->assertSame($copropiedad->id, $membresia->copropiedad_id);
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => Rol::query()->where('clave', 'residente')->value('id'),
            'estado' => 'activa',
            'vigente_hasta' => null,
        ]);
        $this->assertDatabaseCount('membresias_organizacion', 0);
    }

    public function test_changing_users_role_ends_previous_assignment_and_assigns_equivalent_role(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->prepareIdentity(['residente', 'auditor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $usuario = User::factory()->create(['role' => 'residente']);
        $service = app(SincronizarIdentidadContextualUsuario::class);
        $service->actualizarUsuario($usuario, []);

        $this->actingAs($admin)->patch(route('users.role.update', $usuario), [
            'role' => 'auditor',
        ])->assertRedirect();

        $usuario->refresh();
        $membresia = $usuario->membresiasCopropiedad()->firstOrFail();
        $residenteId = Rol::query()->where('clave', 'residente')->value('id');
        $auditorId = Rol::query()->where('clave', 'auditor')->value('id');

        $this->assertSame('auditor', $usuario->role);
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $residenteId,
            'estado' => 'terminada',
        ]);
        $this->assertDatabaseMissing('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $residenteId,
            'vigente_hasta' => null,
        ]);
        $this->assertDatabaseHas('membresia_copropiedad_rol', [
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $auditorId,
            'estado' => 'activa',
            'vigente_hasta' => null,
        ]);
    }

    public function test_synchronization_failure_rolls_back_users_role_and_contextual_changes(): void
    {
        $this->prepareIdentity(['residente']);
        $usuario = User::factory()->create(['role' => 'residente']);
        $service = app(SincronizarIdentidadContextualUsuario::class);
        $service->actualizarUsuario($usuario, []);
        $beforeAssignments = DB::table('membresia_copropiedad_rol')->get()->toArray();

        try {
            $service->actualizarUsuario($usuario, ['role' => 'gestor']);
            $this->fail('La sincronización debió fallar sin el Rol contextual gestor.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('users.role=gestor', $exception->getMessage());
        }

        $this->assertSame('residente', $usuario->fresh()->role);
        $this->assertEquals($beforeAssignments, DB::table('membresia_copropiedad_rol')->get()->toArray());
    }

    public function test_contextual_admin_role_does_not_grant_access_to_legacy_resident(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->prepareIdentity(['admin']);
        $residente = User::factory()->create(['role' => 'residente']);
        $otroUsuario = User::factory()->create(['role' => 'residente']);
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $residente->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
        $this->assignRole($membresia, Rol::query()->where('clave', 'admin')->firstOrFail());
        $pqr = Pqr::factory()->create(['user_id' => $otroUsuario->id]);

        $this->actingAs($residente)->get(route('pqrs.show', $pqr))->assertForbidden();
        $this->assertFalse($residente->canViewAllPqrs());
        $this->assertFalse($residente->canManagePqrs());
        $this->assertFalse($residente->isAdmin());
    }

    private function prepareIdentity(array $roleKeys): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Inicial', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Inicial',
            'estado' => 'activa',
        ]);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Inicial',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        foreach ($roleKeys as $clave) {
            Rol::create([
                'clave' => $clave,
                'nombre' => ucfirst($clave),
                'ambito_aplicable' => 'copropiedad',
                'estado' => 'activo',
            ]);
        }

        return compact('organizacion', 'copropiedad');
    }

    private function assignRole(MembresiaCopropiedad $membresia, Rol $rol): void
    {
        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rol->id,
            'organizacion_id' => $membresia->organizacion_id,
            'copropiedad_id' => $membresia->copropiedad_id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
