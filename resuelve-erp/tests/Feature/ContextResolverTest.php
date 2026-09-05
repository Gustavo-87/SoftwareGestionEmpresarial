<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ContextResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_resolves_institutional_context_without_contextual_access(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();

        $contexto = app(ContextResolver::class)->resolverParaHttp(Request::create('/pqrs'));

        $this->assertTrue($contexto->organizacion->is($organizacion));
        $this->assertTrue($contexto->copropiedad->is($copropiedad));
        $this->assertNull($contexto->usuario);
        $this->assertNull($contexto->membresiaCopropiedad);
        $this->assertSame([], $contexto->roles);
        $this->assertSame([], $contexto->permisos);
        $this->assertFalse($contexto->tieneMembresiaContextual());
    }

    public function test_recognized_user_resolves_current_membership_roles_and_permissions(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $usuario = User::factory()->create(['role' => 'gestor']);
        $rol = $this->createRole('gestor');
        $permiso = Permiso::create([
            'clave' => 'pqrs.gestionar',
            'modulo' => 'pqrs',
            'accion' => 'gestionar',
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ]);
        $rol->permisos()->attach($permiso, ['ambito_aplicable' => 'copropiedad']);
        $membresia = $this->createMembership($usuario, $organizacion, $copropiedad);
        $this->assignRole($membresia, $rol, $organizacion, $copropiedad);

        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id,
            $usuario->id
        );

        $this->assertTrue($contexto->usuario->is($usuario));
        $this->assertTrue($contexto->membresiaCopropiedad->is($membresia));
        $this->assertSame(['gestor'], $contexto->clavesRoles());
        $this->assertSame(['pqrs.gestionar'], $contexto->clavesPermisos());
        $this->assertTrue($contexto->rolHeredadoCoincideParaDiagnostico());
    }

    public function test_authenticated_user_without_membership_has_no_contextual_access(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $usuario = User::factory()->create(['role' => 'admin']);

        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id,
            $usuario->id
        );

        $this->assertTrue($contexto->usuario->is($usuario));
        $this->assertFalse($contexto->tieneMembresiaContextual());
        $this->assertSame([], $contexto->roles);
        $this->assertSame([], $contexto->permisos);
        $this->assertNull($contexto->rolHeredadoCoincideParaDiagnostico());
    }

    public function test_inconsistent_institutional_reference_fails_clearly(): void
    {
        $this->createContext();
        DB::table('site_settings')->update(['organizacion_id' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contexto institucional inicial no está configurado');

        app(ContextResolver::class)->resolverParaHttp(Request::create('/'));
    }

    public function test_explicit_resolution_rejects_copropiedad_from_another_organizacion(): void
    {
        ['organizacion' => $organizacion] = $this->createContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra Copropiedad',
            'estado' => 'activa',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no pertenece');

        app(ContextResolver::class)->resolverExplicito($organizacion->id, $otraCopropiedad->id);
    }

    public function test_http_parameters_cannot_change_the_initial_context(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra Copropiedad',
            'estado' => 'activa',
        ]);
        $request = Request::create('/pqrs', 'GET', [
            'organizacion_id' => $otraOrganizacion->id,
            'copropiedad_id' => $otraCopropiedad->id,
        ]);

        $contexto = app(ContextResolver::class)->resolverParaHttp($request);

        $this->assertTrue($contexto->organizacion->is($organizacion));
        $this->assertTrue($contexto->copropiedad->is($copropiedad));
    }

    public function test_contexto_operativo_properties_are_readonly(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();
        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id
        );

        $this->expectException(Error::class);

        $contexto->roles = [];
    }

    public function test_scoped_context_is_reused_only_within_the_current_request_scope(): void
    {
        $this->createContext();
        $this->app->instance('request', Request::create('/primera'));
        $this->app->forgetScopedInstances();

        $first = app(ContextoOperativo::class);
        $sameRequest = app(ContextoOperativo::class);

        $this->assertSame($first, $sameRequest);

        $this->app->instance('request', Request::create('/segunda'));
        $this->app->forgetScopedInstances();
        $second = app(ContextoOperativo::class);

        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->identificadorCorrelacion, $second->identificadorCorrelacion);
    }

    public function test_explicit_resolution_validates_user_and_scope_without_request(): void
    {
        ['organizacion' => $organizacion, 'copropiedad' => $copropiedad] = $this->createContext();

        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id
        );

        $this->assertNull($contexto->usuario);
        $this->assertNotSame('', $contexto->identificadorCorrelacion);

        $this->expectException(RuntimeException::class);
        app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id,
            999999
        );
    }

    private function createContext(): array
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

    private function createMembership(
        User $usuario,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): MembresiaCopropiedad {
        return MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
    }

    private function assignRole(
        MembresiaCopropiedad $membresia,
        Rol $rol,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): void {
        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rol->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
