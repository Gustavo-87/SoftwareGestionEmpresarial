<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\ConsultaParticipacionContextual;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Persona;
use App\Models\Pqr;
use App\Models\UnidadPrivada;
use App\Models\User;
use App\Models\VinculoUnidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ConsultaParticipacionContextualTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_returns_the_person_and_unit_from_the_active_context_and_exposes_it_to_the_pqr_view(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create(['role' => 'residente']);
        $persona = $this->persona($organizacion, $usuario);
        $unidad = $this->unidad($organizacion, $copropiedad, 'A-101');
        $vinculo = $this->vinculo($persona, $unidad);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertTrue($participacion['persona']->is($persona));
        $this->assertSame([$vinculo->id], $participacion['vinculos']->pluck('id')->all());
        $this->assertSame([$unidad->id], $participacion['unidadesPrivadas']->pluck('id')->all());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $usuario->id]);
        $response = $this->actingAs($admin)->get(route('pqrs.show', $pqr));

        $response->assertOk();
        $vista = $response->viewData('participacion');
        $this->assertTrue($vista['persona']->is($persona));
        $this->assertSame([$unidad->id], $vista['unidadesPrivadas']->pluck('id')->all());
    }

    public function test_excludes_links_and_units_from_another_copropiedad_without_exposing_other_people(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [, $otraCopropiedad] = $this->createOtherContext($organizacion);
        $usuario = User::factory()->create();
        $persona = $this->persona($organizacion, $usuario);
        $unidadLocal = $this->unidad($organizacion, $copropiedad, 'A-101');
        $unidadExterna = $this->unidad($organizacion, $otraCopropiedad, 'B-201');
        $this->vinculo($persona, $unidadLocal);
        $this->vinculo($persona, $unidadExterna);
        $otraPersona = $this->persona($organizacion, User::factory()->create());
        $this->vinculo($otraPersona, $unidadLocal);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertSame([$unidadLocal->id], $participacion['unidadesPrivadas']->pluck('id')->all());
        $this->assertSame(1, $participacion['vinculos']->count());
        $this->assertFalse($participacion['unidadesPrivadas']->first()->relationLoaded('personas'));
    }

    public function test_returns_an_empty_context_when_the_user_has_no_persona(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertNull($participacion['persona']);
        $this->assertTrue($participacion['vinculos']->isEmpty());
        $this->assertTrue($participacion['unidadesPrivadas']->isEmpty());
    }

    public function test_returns_an_empty_participation_when_the_person_has_no_active_contextual_link(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $persona = $this->persona($organizacion, $usuario);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertTrue($participacion['persona']->is($persona));
        $this->assertTrue($participacion['vinculos']->isEmpty());
        $this->assertTrue($participacion['unidadesPrivadas']->isEmpty());
    }

    public function test_returns_all_current_active_links_and_their_contextual_units(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $persona = $this->persona($organizacion, $usuario);
        $primera = $this->unidad($organizacion, $copropiedad, 'A-101');
        $segunda = $this->unidad($organizacion, $copropiedad, 'A-102');
        $this->vinculo($persona, $primera);
        $this->vinculo($persona, $segunda, ['tipo_vinculo' => 'propietario']);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertEqualsCanonicalizing([$primera->id, $segunda->id], $participacion['unidadesPrivadas']->pluck('id')->all());
        $this->assertCount(2, $participacion['vinculos']);
    }

    public function test_excludes_expired_and_concluded_links(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $usuario = User::factory()->create();
        $persona = $this->persona($organizacion, $usuario);
        $vencida = $this->unidad($organizacion, $copropiedad, 'A-101');
        $concluida = $this->unidad($organizacion, $copropiedad, 'A-102');
        $this->vinculo($persona, $vencida, ['vigente_hasta' => now()->subDay()->toDateString()]);
        $this->vinculo($persona, $concluida, ['estado' => 'concluido']);
        $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);

        $participacion = app(ConsultaParticipacionContextual::class)->para($usuario, $contexto);

        $this->assertTrue($participacion['vinculos']->isEmpty());
        $this->assertTrue($participacion['unidadesPrivadas']->isEmpty());
    }

    private function persona(Organizacion $organizacion, User $usuario): Persona
    {
        $persona = new Persona();
        $persona->forceFill([
            'organizacion_id' => $organizacion->id,
            'usuario_id' => $usuario->id,
            'tipo_persona' => 'natural',
            'nombre_razon_social' => $usuario->name,
        ])->save();

        return $persona;
    }

    private function unidad(Organizacion $organizacion, Copropiedad $copropiedad, string $codigo): UnidadPrivada
    {
        $unidad = new UnidadPrivada();
        $unidad->forceFill([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'codigo' => $codigo,
        ])->save();

        return $unidad;
    }

    private function vinculo(Persona $persona, UnidadPrivada $unidad, array $atributos = []): VinculoUnidad
    {
        $vinculo = new VinculoUnidad();
        $vinculo->asociarPartesContextuales($persona, $unidad);
        $vinculo->forceFill($atributos + [
            'tipo_vinculo' => 'residente',
            'estado' => 'activo',
            'vigente_desde' => now()->subDay()->toDateString(),
        ])->save();

        return $vinculo;
    }

    /** @return array{Organizacion, Copropiedad} */
    private function createOtherContext(Organizacion $organizacion): array
    {
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);

        return [$organizacion, $copropiedad];
    }
}
