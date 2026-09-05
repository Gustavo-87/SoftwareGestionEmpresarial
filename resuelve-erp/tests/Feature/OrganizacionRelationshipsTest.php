<?php

namespace Tests\Feature;

use App\Models\ConfiguracionCopropiedad;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizacionRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizacion_has_many_copropiedades(): void
    {
        $organizacion = $this->createOrganizacion();
        $copropiedadA = $this->createCopropiedad($organizacion, 'Copropiedad A');
        $copropiedadB = $this->createCopropiedad($organizacion, 'Copropiedad B');

        $this->assertInstanceOf(HasMany::class, $organizacion->copropiedades());
        $this->assertCount(2, $organizacion->copropiedades);
        $this->assertTrue($organizacion->copropiedades->contains($copropiedadA));
        $this->assertTrue($organizacion->copropiedades->contains($copropiedadB));
    }

    public function test_copropiedad_belongs_to_organizacion(): void
    {
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);

        $this->assertInstanceOf(BelongsTo::class, $copropiedad->organizacion());
        $this->assertTrue($copropiedad->organizacion->is($organizacion));
    }

    public function test_organizacion_has_one_configuracion(): void
    {
        $organizacion = $this->createOrganizacion();
        $configuracion = ConfiguracionOrganizacion::create([
            'organizacion_id' => $organizacion->id,
        ]);

        $this->assertInstanceOf(HasOne::class, $organizacion->configuracion());
        $this->assertTrue($organizacion->configuracion->is($configuracion));
        $this->assertTrue($configuracion->organizacion->is($organizacion));
    }

    public function test_copropiedad_has_one_configuracion(): void
    {
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $configuracion = $this->createConfiguracionCopropiedad($organizacion, $copropiedad);

        $this->assertInstanceOf(HasOne::class, $copropiedad->configuracion());
        $this->assertTrue($copropiedad->configuracion->is($configuracion));
    }

    public function test_configuracion_copropiedad_belongs_to_the_same_context(): void
    {
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $configuracion = $this->createConfiguracionCopropiedad($organizacion, $copropiedad);

        $this->assertTrue($configuracion->organizacion->is($organizacion));
        $this->assertTrue($configuracion->copropiedad->is($copropiedad));
        $this->assertSame($copropiedad->organizacion_id, $configuracion->organizacion_id);
    }

    public function test_database_rejects_a_configuracion_with_a_different_context(): void
    {
        $organizacionA = $this->createOrganizacion('Organización A');
        $organizacionB = $this->createOrganizacion('Organización B');
        $copropiedad = $this->createCopropiedad($organizacionA);

        $this->expectException(QueryException::class);

        $this->createConfiguracionCopropiedad($organizacionB, $copropiedad);
    }

    public function test_site_setting_relations_are_optional_and_preserve_current_behavior(): void
    {
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Conjunto existente',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);

        $this->assertNull($settings->organizacion);
        $this->assertNull($settings->copropiedad);
        $this->assertTrue(SiteSetting::current()->is($settings));
    }

    public function test_site_setting_can_reference_organizacion_and_copropiedad(): void
    {
        $organizacion = $this->createOrganizacion();
        $copropiedad = $this->createCopropiedad($organizacion);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Conjunto existente',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);

        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        $this->assertTrue($settings->fresh()->organizacion->is($organizacion));
        $this->assertTrue($settings->fresh()->copropiedad->is($copropiedad));
    }

    private function createOrganizacion(string $nombre = 'Organización principal'): Organizacion
    {
        return Organizacion::create([
            'nombre' => $nombre,
            'estado' => 'activa',
        ]);
    }

    private function createCopropiedad(
        Organizacion $organizacion,
        string $nombre = 'Copropiedad principal'
    ): Copropiedad {
        return Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => $nombre,
            'estado' => 'activa',
        ]);
    }

    private function createConfiguracionCopropiedad(
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): ConfiguracionCopropiedad {
        return ConfiguracionCopropiedad::create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
    }
}
