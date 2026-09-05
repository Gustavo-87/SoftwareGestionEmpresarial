<?php

namespace Tests\Feature;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CrearContextoInicialCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_initial_context_and_copies_approved_fields(): void
    {
        $settings = $this->createSiteSetting();

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        $settings->refresh();
        $organizacion = $settings->organizacion;
        $copropiedad = $settings->copropiedad;
        $configuracion = $copropiedad->configuracion;

        $this->assertNotNull($organizacion);
        $this->assertSame('activa', $organizacion->estado);
        $this->assertSame($organizacion->id, $copropiedad->organizacion_id);
        $this->assertSame('Conjunto Los Robles', $copropiedad->nombre);
        $this->assertSame('900.123.456-7', $copropiedad->nit);
        $this->assertSame('Ana Pérez', $copropiedad->representante_legal);
        $this->assertSame('Calle 10 # 20-30', $copropiedad->direccion);
        $this->assertSame('Cartago', $copropiedad->ciudad);
        $this->assertSame('3001234567', $copropiedad->telefono);
        $this->assertSame('admin@losrobles.co', $copropiedad->email);
        $this->assertSame('#1e3a5f', $configuracion->color_principal);
        $this->assertSame('logos/robles.png', $configuracion->logo_path);
        $this->assertSame(12, $configuracion->dias_respuesta);
        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_second_execution_does_not_create_duplicates(): void
    {
        $this->createSiteSetting();

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();
        $this->artisan('resuelve:crear-contexto-inicial')
            ->expectsOutput('El contexto inicial ya existía y no requirió cambios.')
            ->assertSuccessful();

        $this->assertSame(1, Organizacion::query()->count());
        $this->assertSame(1, Copropiedad::query()->count());
        $this->assertSame(1, ConfiguracionCopropiedad::query()->count());
        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_completes_missing_configuracion(): void
    {
        $settings = $this->createSiteSetting();
        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();
        $settings->refresh()->copropiedad->configuracion()->delete();

        $this->artisan('resuelve:crear-contexto-inicial')
            ->expectsOutput('El contexto inicial ya existía y fue completado de forma segura.')
            ->assertSuccessful();

        $this->assertSame(1, ConfiguracionCopropiedad::query()->count());
        $this->assertSame('#1e3a5f', $settings->fresh()->copropiedad->configuracion->color_principal);
    }

    public function test_recovers_organizacion_from_a_partial_copropiedad_reference(): void
    {
        $settings = $this->createSiteSetting();
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedadFrom($settings, $organizacion);
        $settings->forceFill(['copropiedad_id' => $copropiedad->id])->save();

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        $this->assertSame($organizacion->id, $settings->fresh()->organizacion_id);
        $this->assertSame(1, Organizacion::query()->count());
        $this->assertSame(1, Copropiedad::query()->count());
    }

    public function test_creates_copropiedad_for_a_partial_organizacion_reference(): void
    {
        $settings = $this->createSiteSetting();
        $organizacion = $this->createOrganizacion();
        $settings->forceFill(['organizacion_id' => $organizacion->id])->save();

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        $settings->refresh();
        $this->assertSame($organizacion->id, $settings->organizacion_id);
        $this->assertNotNull($settings->copropiedad_id);
        $this->assertSame($organizacion->id, $settings->copropiedad->organizacion_id);
        $this->assertSame(1, Organizacion::query()->count());
        $this->assertSame(1, Copropiedad::query()->count());
    }

    public function test_rejects_an_inconsistent_reference(): void
    {
        $settings = $this->createSiteSetting();
        $settings->forceFill(['copropiedad_id' => 999999])->save();

        $this->artisan('resuelve:crear-contexto-inicial')
            ->expectsOutputToContain('referencia la Copropiedad inexistente')
            ->assertFailed();

        $this->assertSame(0, Organizacion::query()->count());
        $this->assertSame(0, Copropiedad::query()->count());
        $this->assertNull($settings->fresh()->organizacion_id);
        $this->assertSame(999999, $settings->fresh()->copropiedad_id);
    }

    public function test_preserves_and_warns_about_additional_site_settings(): void
    {
        $settings = $this->createSiteSetting();
        $additional = $this->createSiteSetting(['nombre_conjunto' => 'Fila adicional']);

        $this->artisan('resuelve:crear-contexto-inicial')
            ->expectsOutputToContain('1 filas adicionales de site_settings')
            ->assertSuccessful();

        $this->assertNotNull($settings->fresh()->organizacion_id);
        $this->assertNull($additional->fresh()->organizacion_id);
        $this->assertNull($additional->fresh()->copropiedad_id);
        $this->assertSame(2, SiteSetting::query()->count());
    }

    public function test_rolls_back_every_change_when_configuration_creation_fails(): void
    {
        $settings = $this->createSiteSetting();
        ConfiguracionCopropiedad::creating(function (): void {
            throw new RuntimeException('Fallo controlado durante la configuración.');
        });

        $this->artisan('resuelve:crear-contexto-inicial')
            ->expectsOutputToContain('se revirtieron los cambios')
            ->assertFailed();

        $this->assertSame(0, Organizacion::query()->count());
        $this->assertSame(0, Copropiedad::query()->count());
        $this->assertSame(0, ConfiguracionCopropiedad::query()->count());
        $this->assertSame(1, SiteSetting::query()->count());
        $this->assertNull($settings->fresh()->organizacion_id);
        $this->assertNull($settings->fresh()->copropiedad_id);
    }

    private function createSiteSetting(array $overrides = []): SiteSetting
    {
        return SiteSetting::create(array_merge([
            'nombre_conjunto' => 'Conjunto Los Robles',
            'nit' => '900.123.456-7',
            'representante_legal' => 'Ana Pérez',
            'direccion' => 'Calle 10 # 20-30',
            'ciudad' => 'Cartago',
            'telefono' => '3001234567',
            'email' => 'admin@losrobles.co',
            'color_principal' => '#1e3a5f',
            'logo_path' => 'logos/robles.png',
            'dias_respuesta' => 12,
        ], $overrides));
    }

    private function createOrganizacion(): Organizacion
    {
        return Organizacion::create([
            'nombre' => 'Organización existente',
            'estado' => 'activa',
        ]);
    }

    private function createCopropiedadFrom(
        SiteSetting $settings,
        Organizacion $organizacion
    ): Copropiedad {
        return Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => $settings->nombre_conjunto,
            'nit' => $settings->nit,
            'representante_legal' => $settings->representante_legal,
            'direccion' => $settings->direccion,
            'ciudad' => $settings->ciudad,
            'telefono' => $settings->telefono,
            'email' => $settings->email,
            'estado' => 'activa',
        ]);
    }
}
