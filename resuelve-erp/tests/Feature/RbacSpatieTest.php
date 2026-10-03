<?php

namespace Tests\Feature;

use App\Application\Autorizacion\VerificadorEquivalenciaRbacSpatie;
use App\Application\Identidad\SincronizarIdentidadContextualUsuario;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class RbacSpatieTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private const GUARD = 'web';

    private function snapshotSpatie(): array
    {
        return [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
        ];
    }

    public function test_a_has_roles_esta_operativo_en_user(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();

        setPermissionsTeamId($copropiedad->id);
        Permission::findOrCreate('pqrs.listar', self::GUARD);
        $rol = Role::findOrCreate('gestor', self::GUARD);
        $rol->givePermissionTo('pqrs.listar');
        $usuario->assignRole($rol);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue($usuario->hasRole('gestor'));
        $this->assertTrue($usuario->hasPermissionTo('pqrs.listar'));
    }

    public function test_b_y_c_roles_distintos_por_copropiedad_sin_filtracion_entre_equipos(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        $usuario = User::factory()->create();

        Permission::findOrCreate('pqrs.gestionar', self::GUARD);
        Permission::findOrCreate('pqrs.ver_todas', self::GUARD);
        $gestor = Role::findOrCreate('gestor', self::GUARD);
        $gestor->syncPermissions(['pqrs.gestionar']);
        $auditor = Role::findOrCreate('auditor', self::GUARD);
        $auditor->syncPermissions(['pqrs.ver_todas']);

        setPermissionsTeamId($copropiedadA->id);
        $usuario->assignRole($gestor);
        setPermissionsTeamId($copropiedadB->id);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $usuario->assignRole($auditor);

        // Copropiedad A: rol X activo, rol Y ausente.
        setPermissionsTeamId($copropiedadA->id);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($usuario->hasRole('gestor'));
        $this->assertFalse($usuario->hasRole('auditor'));
        $this->assertTrue($usuario->hasPermissionTo('pqrs.gestionar'));
        $this->assertFalse($usuario->hasPermissionTo('pqrs.ver_todas'));

        // Copropiedad B: el rol de A no concede permisos en B.
        setPermissionsTeamId($copropiedadB->id);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($usuario->hasRole('auditor'));
        $this->assertFalse($usuario->hasRole('gestor'));
        $this->assertFalse($usuario->hasPermissionTo('pqrs.gestionar'));
        $this->assertTrue($usuario->hasPermissionTo('pqrs.ver_todas'));
    }

    public function test_d_a_k_migracion_completa_idempotente_con_matriz_y_asignaciones(): void
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();

        $activo = User::factory()->create(['role' => 'gestor']);
        $vencido = User::factory()->create(['role' => 'apoyo']);
        $terminado = User::factory()->create(['role' => 'residente']);
        $this->asignacionLegado($activo, $organizacion, $copropiedad, 'gestor');
        $this->asignacionLegado($vencido, $organizacion, $copropiedad, 'apoyo', 'activa', now()->subDay());
        $this->asignacionLegado($terminado, $organizacion, $copropiedad, 'residente', 'terminada');

        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();
        $primera = $this->snapshotSpatie();

        // D: idempotencia — segunda ejecución sin cambios ni duplicados.
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();
        $this->assertSame($primera, $this->snapshotSpatie());

        // E y F.
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 25);
        $this->assertTrue(Role::query()->where('name', 'admin')->first()->hasPermissionTo('roles.gestionar'));

        // G: la matriz de los 22 permisos heredados coincide con el RBAC anterior.
        $clavesLegado = DB::table('permisos_contextuales')->orderBy('clave')->pluck('clave')->all();
        foreach (DB::table('roles_contextuales')->where('ambito_aplicable', 'copropiedad')->get() as $rolLegado) {
            $matrizLegado = DB::table('rol_permiso_contextual')
                ->join('permisos_contextuales', 'permisos_contextuales.id', '=', 'rol_permiso_contextual.permiso_id')
                ->where('rol_permiso_contextual.rol_id', $rolLegado->id)
                ->pluck('permisos_contextuales.clave')->all();
            sort($matrizLegado);
            $matrizSpatie = Role::query()->where('name', $rolLegado->clave)->first()->permissions()->pluck('name')->all();
            $matrizSpatie = array_values(array_intersect($matrizSpatie, $clavesLegado));
            sort($matrizSpatie);
            $this->assertSame($matrizLegado, $matrizSpatie, "La matriz del rol {$rolLegado->clave} no coincide con el RBAC legado.");
        }

        // H: pqrs.gestionar_asignadas solo en apoyo.
        foreach (['admin', 'gestor', 'auditor', 'residente'] as $sin) {
            $this->assertFalse(Role::query()->where('name', $sin)->first()->hasPermissionTo('pqrs.gestionar_asignadas'));
        }
        $this->assertTrue(Role::query()->where('name', 'apoyo')->first()->hasPermissionTo('pqrs.gestionar_asignadas'));

        // I: pqrs.ver_borradores en admin y gestor únicamente.
        foreach (['admin', 'gestor'] as $con) {
            $this->assertTrue(Role::query()->where('name', $con)->first()->hasPermissionTo('pqrs.ver_borradores'));
        }
        foreach (['apoyo', 'auditor', 'residente'] as $sin) {
            $this->assertFalse(Role::query()->where('name', $sin)->first()->hasPermissionTo('pqrs.ver_borradores'));
        }

        // J: solo la asignación activa se migra, con aislamiento por Copropiedad.
        $asignaciones = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->get(['model_has_roles.model_id', 'model_has_roles.copropiedad_id', 'roles.name'])
            ->map(fn (object $fila) => "{$fila->model_id}|{$fila->copropiedad_id}|{$fila->name}")->all();
        $this->assertSame(["{$activo->id}|{$copropiedad->id}|gestor"], $asignaciones);

        // K: las asignaciones vencidas o inactivas no producen autorización activa.
        setPermissionsTeamId($copropiedad->id);
        $this->assertFalse($vencido->fresh()->hasRole('apoyo'));
        $this->assertFalse($terminado->fresh()->hasRole('residente'));
        $this->assertTrue($activo->fresh()->hasRole('gestor'));
    }

    public function test_diagnostico_detecta_faltantes_matriz_y_asignaciones_entre_copropiedades(): void
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();
        $activo = User::factory()->create(['role' => 'gestor']);
        $this->asignacionLegado($activo, $organizacion, $copropiedad, 'gestor');
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();

        $verificador = app(VerificadorEquivalenciaRbacSpatie::class);
        $this->assertSame([], $verificador->divergencias());

        // Asignación incorrecta entre Copropiedades.
        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        DB::table('model_has_roles')->insert([
            'role_id' => Role::query()->where('name', 'gestor')->value('id'),
            'model_type' => User::class,
            'model_id' => $activo->id,
            'copropiedad_id' => $otra->id,
        ]);
        $this->assertStringContainsString('no corresponde a una asignación legado activa', implode(' | ', $verificador->divergencias()));

        // Asignación faltante.
        DB::table('model_has_roles')->where('copropiedad_id', $copropiedad->id)->delete();
        $this->assertStringContainsString('Falta la asignación de Spatie', implode(' | ', $verificador->divergencias()));

        // Diferencia en la matriz rol-permiso.
        DB::table('model_has_roles')->delete();
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();
        $relacion = DB::table('role_has_permissions')
            ->where('role_id', Role::query()->where('name', 'admin')->value('id'))
            ->first();
        DB::table('role_has_permissions')
            ->where('role_id', $relacion->role_id)
            ->where('permission_id', $relacion->permission_id)
            ->delete();
        $this->assertStringContainsString('La matriz rol-permiso de admin difiere', implode(' | ', $verificador->divergencias()));

        // Permiso faltante.
        DB::table('role_has_permissions')->where('permission_id', Permission::findOrCreate('pqrs.listar', self::GUARD)->id)->delete();
        Permission::query()->where('name', 'pqrs.listar')->delete();
        $this->assertStringContainsString('Falta el permiso de Spatie pqrs.listar', implode(' | ', $verificador->divergencias()));

        // Rol faltante.
        Role::query()->where('name', 'auditor')->delete();
        $this->assertStringContainsString('Falta el rol de Spatie auditor', implode(' | ', $verificador->divergencias()));
    }

    public function test_doble_escritura_sincroniza_asignacion_spatie_por_copropiedad(): void
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();

        $usuario = app(SincronizarIdentidadContextualUsuario::class)->crearUsuario([
            'name' => 'Doble Escritura',
            'email' => 'doble.escritura@example.com',
            'password' => 'password-segura-123',
            'role' => 'gestor',
        ]);

        // Escritura legada conservada.
        $this->assertDatabaseHas('membresias_copropiedad', ['usuario_id' => $usuario->id, 'copropiedad_id' => $copropiedad->id]);
        $this->assertSame(1, DB::table('membresia_copropiedad_rol')->where('membresia_copropiedad_id',
            MembresiaCopropiedad::query()->where('usuario_id', $usuario->id)->value('id'))->count());

        // Escritura equivalente en Spatie para la misma Copropiedad.
        setPermissionsTeamId($copropiedad->id);
        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($usuario->hasRole('gestor'));
    }

    public function test_comando_de_verificacion_integral_reporta_equivalencia(): void
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();
        User::factory()->create(['role' => 'admin']);
        $this->artisan('resuelve:crear-identidad-contextual-inicial')->assertSuccessful();
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();

        $this->artisan('resuelve:verificar-equivalencia-autorizacion-contextual')->assertSuccessful();
    }
}
