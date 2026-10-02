<?php

namespace Tests\Feature;

use App\Application\Autorizacion\VerificadorEquivalenciaRbacSpatie;
use App\Application\Contexto\ContextResolver;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class RbacSpatieFuenteTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function membresiaActiva(User $usuario, $organizacion, Copropiedad $copropiedad): void
    {
        MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
    }

    private function contexto($organizacion, Copropiedad $copropiedad, User $usuario)
    {
        return app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);
    }

    public function test_a_contexto_obtiene_roles_desde_spatie(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $this->membresiaActiva($usuario, $organizacion, $copropiedad);

        setPermissionsTeamId($copropiedad->id);
        SpatieRole::findOrCreate('gestor', 'web');
        $usuario->assignRole('gestor');

        // La asignación existe SOLO en Spatie: cero filas en el pivote legado.
        $this->assertDatabaseCount('membresia_copropiedad_rol', 0);
        $this->assertSame(['gestor'], $this->contexto($organizacion, $copropiedad, $usuario)->clavesRoles());
    }

    public function test_b_contexto_obtiene_permisos_desde_spatie(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $this->membresiaActiva($usuario, $organizacion, $copropiedad);

        setPermissionsTeamId($copropiedad->id);
        Permission::findOrCreate('pqrs.listar', 'web');
        Permission::findOrCreate('pqrs.crear', 'web');
        $rol = SpatieRole::findOrCreate('gestor', 'web');
        $rol->syncPermissions(['pqrs.listar', 'pqrs.crear']);
        $usuario->assignRole($rol);

        $claves = $this->contexto($organizacion, $copropiedad, $usuario)->clavesPermisos();
        sort($claves);
        $this->assertSame(['pqrs.crear', 'pqrs.listar'], $claves);
    }

    public function test_c_modificar_solo_el_espejo_legado_no_cambia_la_autorizacion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);

        // Se destruye por completo el espejo legado de roles y permisos.
        DB::table('membresia_copropiedad_rol')->delete();
        DB::table('rol_permiso_contextual')->delete();

        $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
        $this->assertSame(['gestor'], $contexto->clavesRoles());
        $this->assertSame(['pqrs.gestionar'], $contexto->clavesPermisos());
    }

    public function test_d_modificar_spatie_si_cambia_la_autorizacion(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);

        setPermissionsTeamId($copropiedad->id);
        $rol = SpatieRole::findByName('gestor', 'web');

        // Agregar un permiso en Spatie cambia los permisos efectivos.
        Permission::findOrCreate('pqrs.ver_todas', 'web');
        $rol->givePermissionTo('pqrs.ver_todas');
        $claves = $this->contexto($organizacion, $copropiedad, $usuario)->clavesPermisos();
        sort($claves);
        $this->assertSame(['pqrs.gestionar', 'pqrs.ver_todas'], $claves);

        // Revocar el rol en Spatie elimina la autorización efectiva.
        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $usuario->removeRole($rol);
        $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
        $this->assertSame([], $contexto->clavesRoles());
        $this->assertSame([], $contexto->clavesPermisos());
    }

    public function test_e_un_rol_spatie_de_otra_copropiedad_no_aparece_en_el_contexto(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        $usuario = User::factory()->create();
        $this->createContextualIdentity($usuario, $organizacion, $copropiedadA, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($usuario, $organizacion, $copropiedadB, 'auditor', ['pqrs.ver_todas']);

        $contextoA = $this->contexto($organizacion, $copropiedadA, $usuario);
        $this->assertSame(['gestor'], $contextoA->clavesRoles());
        $this->assertSame(['pqrs.gestionar'], $contextoA->clavesPermisos());

        $contextoB = $this->contexto($organizacion, $copropiedadB, $usuario);
        $this->assertSame(['auditor'], $contextoB->clavesRoles());
        $this->assertSame(['pqrs.ver_todas'], $contextoB->clavesPermisos());
    }

    public function test_f_asignacion_spatie_sin_membresia_valida_no_autoriza(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $sinMembresia = User::factory()->create();
        $inactiva = User::factory()->create();
        $vencida = User::factory()->create();

        setPermissionsTeamId($copropiedad->id);
        SpatieRole::findOrCreate('gestor', 'web');
        foreach ([$sinMembresia, $inactiva, $vencida] as $usuario) {
            $usuario->assignRole('gestor');
        }
        MembresiaCopropiedad::create([
            'usuario_id' => $inactiva->id, 'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id, 'estado' => 'inactiva', 'vigente_desde' => now()->subDay(),
        ]);
        MembresiaCopropiedad::create([
            'usuario_id' => $vencida->id, 'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id, 'estado' => 'activa',
            'vigente_desde' => now()->subMonth(), 'vigente_hasta' => now()->subDay(),
        ]);

        foreach ([$sinMembresia, $inactiva, $vencida] as $usuario) {
            $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
            $this->assertFalse($contexto->tieneMembresiaContextual());
            $this->assertSame([], $contexto->clavesRoles());
            $this->assertSame([], $contexto->clavesPermisos());
        }
    }

    public function test_g_cambio_de_copropiedad_en_la_misma_ejecucion_no_contamina_relaciones(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        $usuario = User::factory()->create();
        $this->createContextualIdentity($usuario, $organizacion, $copropiedadA, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($usuario, $organizacion, $copropiedadB, 'auditor', ['pqrs.ver_todas']);

        // Misma instancia de Usuario y misma petición durante toda la ejecución.
        $session = new Store('testing', new ArraySessionHandler(120));
        $request = Request::create('/panel', 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $usuario);
        $resolver = app(ContextResolver::class);

        $session->put('copropiedad_activa_id', $copropiedadA->id);
        $contextoA = $resolver->resolverParaHttp($request);
        $this->assertSame(['gestor'], $contextoA->clavesRoles());
        $this->assertSame(['pqrs.gestionar'], $contextoA->clavesPermisos());

        $session->put('copropiedad_activa_id', $copropiedadB->id);
        $contextoB = $resolver->resolverParaHttp($request);
        $this->assertSame(['auditor'], $contextoB->clavesRoles());
        $this->assertSame(['pqrs.ver_todas'], $contextoB->clavesPermisos());
    }

    public function test_h_diagnostico_detecta_divergencia_mientras_spatie_sigue_decidiendo(): void
    {
        // Catálogo legado completo y su equivalente en Spatie, sin divergencias.
        [$organizacion, $copropiedad] = $this->catalogoLegado();
        $usuario = User::factory()->create(['role' => 'gestor']);
        $this->asignacionLegado($usuario, $organizacion, $copropiedad, 'gestor');
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();
        $verificador = app(VerificadorEquivalenciaRbacSpatie::class);
        $this->assertSame([], $verificador->divergencias());

        // Divergencia creada solo en el espejo legado (no se autorrepara).
        DB::table('membresia_copropiedad_rol')->delete();
        $divergencias = implode(' | ', $verificador->divergencias());
        $this->assertStringContainsString('no corresponde a una asignación legado activa', $divergencias);
        $this->assertDatabaseCount('membresia_copropiedad_rol', 0); // el diagnóstico no escribe ni autocorrige

        // La autorización efectiva continúa resolviéndose desde Spatie.
        $contexto = $this->contexto($organizacion, $copropiedad, $usuario);
        $this->assertSame(['gestor'], $contexto->clavesRoles());
        $this->assertContains('pqrs.gestionar', $contexto->clavesPermisos());
    }
}
