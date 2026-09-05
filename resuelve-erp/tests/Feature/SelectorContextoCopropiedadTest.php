<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Rol;
use App\Models\Permiso;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelectorContextoCopropiedadTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_una_copropiedad_entra_automaticamente(): void
    {
        [$organizacion, $copropiedad] = $this->createContextoInstitucional();
        $usuario = User::factory()->create(['role' => 'admin']);
        $this->createMembresia($usuario, $organizacion, $copropiedad);

        $response = $this->actingAs($usuario)->get('/panel');

        $response->assertOk();
        $this->assertEquals(
            $copropiedad->id,
            $this->app->make('session')->get('copropiedad_activa_id')
        );
    }

    public function test_usuario_con_varias_copropiedades_ve_selector(): void
    {
        [$organizacion, $copropiedad1] = $this->createContextoInstitucional();
        $copropiedad2 = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Segunda Copropiedad',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'admin']);
        $this->createMembresia($usuario, $organizacion, $copropiedad1);
        $this->createMembresia($usuario, $organizacion, $copropiedad2);

        $response = $this->actingAs($usuario)->get('/panel');

        $response->assertOk();
        $response->assertSee('copropiedad_selector');
        $response->assertSee($copropiedad1->nombre);
        $response->assertSee($copropiedad2->nombre);
    }

    public function test_usuario_sin_membresia_accede_con_contexto_institucional(): void
    {
        [$organizacion, $copropiedad] = $this->createContextoInstitucional();
        $usuario = User::factory()->create(['role' => 'residente']);

        $response = $this->actingAs($usuario)->get('/panel');

        $response->assertOk();
        $this->assertNull(
            $this->app->make('session')->get('copropiedad_activa_id')
        );
    }

    public function test_cambio_de_contexto_a_copropiedad_autorizada(): void
    {
        [$organizacion, $copropiedad1] = $this->createContextoInstitucional();
        $copropiedad2 = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Segunda Copropiedad',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'admin']);
        $this->createMembresia($usuario, $organizacion, $copropiedad1);
        $this->createMembresia($usuario, $organizacion, $copropiedad2);

        $response = $this->actingAs($usuario)->post('/cambiar-contexto', [
            'copropiedad_id' => $copropiedad2->id,
        ]);

        $response->assertRedirect('/panel');
        $this->assertEquals(
            $copropiedad2->id,
            $this->app->make('session')->get('copropiedad_activa_id')
        );
    }

    public function test_cambio_de_contexto_a_copropiedad_no_autorizada_falla(): void
    {
        [$organizacion, $copropiedad1] = $this->createContextoInstitucional();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra Org', 'estado' => 'activa']);
        $copropiedadAjena = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Copropiedad Ajena',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'admin']);
        $this->createMembresia($usuario, $organizacion, $copropiedad1);

        $this->actingAs($usuario);

        $this->get('/panel');
        $this->assertEquals($copropiedad1->id, session('copropiedad_activa_id'));

        $response = $this->post('/cambiar-contexto', [
            'copropiedad_id' => $copropiedadAjena->id,
        ]);

        $response->assertSessionHasErrors('copropiedad_id');
        $this->assertEquals($copropiedad1->id, session('copropiedad_activa_id'));
    }

    public function test_informacion_de_una_copropiedad_no_aparece_en_otra(): void
    {
        [$organizacion, $copropiedad1] = $this->createContextoInstitucional();
        $copropiedad2 = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Segunda Copropiedad',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'admin']);
        $membresia1 = $this->createMembresia($usuario, $organizacion, $copropiedad1);
        $membresia2 = $this->createMembresia($usuario, $organizacion, $copropiedad2);

        $this->actingAs($usuario)->post('/cambiar-contexto', [
            'copropiedad_id' => $copropiedad2->id,
        ]);

        $this->assertEquals($copropiedad2->id, session('copropiedad_activa_id'));
    }

    public function test_revocacion_de_membresia_invalida_contexto_en_sesion(): void
    {
        [$organizacion, $copropiedad1] = $this->createContextoInstitucional();
        $copropiedad2 = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Segunda Copropiedad',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'admin']);
        $this->createMembresia($usuario, $organizacion, $copropiedad1);
        $membresia2 = $this->createMembresia($usuario, $organizacion, $copropiedad2);

        $this->actingAs($usuario)->post('/cambiar-contexto', [
            'copropiedad_id' => $copropiedad2->id,
        ]);
        $this->assertEquals($copropiedad2->id, session('copropiedad_activa_id'));

        $membresia2->update(['estado' => 'revocada']);

        $this->get('/panel');

        $this->assertNotEquals($copropiedad2->id, session('copropiedad_activa_id'));
    }

    private function createContextoInstitucional(): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Org Test', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad Test',
            'estado' => 'activa',
        ]);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Test',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        return [$organizacion, $copropiedad];
    }

    private function createMembresia(
        User $usuario,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): MembresiaCopropiedad {
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);

        $rol = Rol::firstOrCreate(
            ['clave' => 'admin'],
            ['nombre' => 'Administrador', 'ambito_aplicable' => 'copropiedad', 'estado' => 'activo']
        );

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

        return $membresia;
    }
}
