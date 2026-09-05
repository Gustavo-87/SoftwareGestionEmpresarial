<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Residencia\ConsultaPersonasContextuales;
use App\Application\Residencia\ConsultaUnidadesPrivadasContextuales;
use App\Application\Residencia\ConsultaVinculosUnidadContextuales;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Persona;
use App\Models\UnidadPrivada;
use App\Models\VinculoUnidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ConsultasResidenciaContextualesTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_queries_are_limited_to_the_operational_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $otraOrganizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id);

        $persona = $this->persona($organizacion->id, 'Persona local');
        $unidad = $this->unidad($organizacion->id, $copropiedad->id, 'A-101');
        $vinculo = $this->vinculo($persona, $unidad);
        $personaForanea = $this->persona($otraOrganizacion->id, 'Persona foránea');
        $unidadForanea = $this->unidad($otraOrganizacion->id, $otraCopropiedad->id, 'B-201');
        $this->vinculo($personaForanea, $unidadForanea);

        $this->assertEquals([$persona->id], app(ConsultaPersonasContextuales::class)->para($contexto)->pluck('id')->all());
        $this->assertEquals([$unidad->id], app(ConsultaUnidadesPrivadasContextuales::class)->para($contexto)->pluck('id')->all());
        $this->assertEquals([$vinculo->id], app(ConsultaVinculosUnidadContextuales::class)->para($contexto)->pluck('id')->all());
    }

    private function persona(int $organizacionId, string $nombre): Persona
    {
        $persona = new Persona();
        $persona->forceFill(['organizacion_id' => $organizacionId, 'tipo_persona' => 'natural', 'nombre_razon_social' => $nombre])->save();

        return $persona;
    }

    private function unidad(int $organizacionId, int $copropiedadId, string $codigo): UnidadPrivada
    {
        $unidad = new UnidadPrivada();
        $unidad->forceFill(['organizacion_id' => $organizacionId, 'copropiedad_id' => $copropiedadId, 'codigo' => $codigo])->save();

        return $unidad;
    }

    private function vinculo(Persona $persona, UnidadPrivada $unidad): VinculoUnidad
    {
        $vinculo = new VinculoUnidad();
        $vinculo->asociarPartesContextuales($persona, $unidad);
        $vinculo->forceFill(['tipo_vinculo' => 'residente', 'estado' => 'activo', 'vigente_desde' => now()->toDateString()])->save();

        return $vinculo;
    }
}
