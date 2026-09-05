<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Tests\Concerns\CreatesInstitutionalContext;

class PqrContextRelationshipsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_pqr_belongs_to_organizacion_and_copropiedad_with_inverse_relations(): void
    {
        [$organizacion, $copropiedad] = $this->createContext();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();

        $this->assertTrue($pqr->organizacion->is($organizacion));
        $this->assertTrue($pqr->copropiedad->is($copropiedad));
        $this->assertTrue($organizacion->pqrs->contains($pqr));
        $this->assertTrue($copropiedad->pqrs->contains($pqr));
    }

    public function test_authenticated_creation_assigns_active_context_and_ignores_client_context(): void
    {
        [$organizacion, $copropiedad] = $this->createContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra',
            'estado' => 'activa',
        ]);
        $usuario = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($usuario, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $this->actingAs($usuario)->post(route('pqrs.store'), [
            'asunto' => 'Solicitud contextual',
            'descripcion' => 'Debe usar exclusivamente el contexto activo.',
            'fecha_radicacion' => now()->toDateString(),
            'tipo_pqr_id' => $tipo->id,
            'organizacion_id' => $otraOrganizacion->id,
            'copropiedad_id' => $otraCopropiedad->id,
        ])->assertRedirect();

        $pqr = Pqr::query()->where('asunto', 'Solicitud contextual')->firstOrFail();
        $this->assertSame($organizacion->id, $pqr->organizacion_id);
        $this->assertSame($copropiedad->id, $pqr->copropiedad_id);
    }

    public function test_database_rejects_incompatible_organizacion_and_copropiedad(): void
    {
        [$organizacion] = $this->createContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra',
            'estado' => 'activa',
        ]);

        $this->expectException(QueryException::class);
        $this->insertPqr($organizacion->id, $otraCopropiedad->id);
    }

    public function test_database_rejects_nonexistent_context_references(): void
    {
        $this->expectException(QueryException::class);
        $this->insertPqr(999999, 999999);
    }

    public function test_invalid_institutional_context_prevents_creation(): void
    {
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Sin contexto',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $usuario = User::factory()->create(['role' => 'residente']);
        $tipo = TipoPqr::factory()->create();
        $exception = null;
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($usuario)->post(route('pqrs.store'), [
                'asunto' => 'No debe persistirse',
                'descripcion' => 'El contexto institucional es obligatorio.',
                'fecha_radicacion' => now()->toDateString(),
                'tipo_pqr_id' => $tipo->id,
            ]);
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('contexto institucional inicial', $exception->getMessage());
        $this->assertTrue($settings->exists);
        $this->assertDatabaseCount('pqrs', 0);
    }

    public function test_database_rejects_a_pqr_without_context_after_structural_closure(): void
    {
        $this->expectException(QueryException::class);

        DB::table('pqrs')->insert([
            'asunto' => 'Sin contexto',
            'descripcion' => 'Debe rechazarse.',
            'fecha_radicacion' => now()->toDateString(),
            'estado' => 'radicada',
            'user_id' => User::factory()->create()->id,
            'tipo_pqr_id' => TipoPqr::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{Organizacion, Copropiedad} */
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

        return [$organizacion, $copropiedad];
    }

    private function insertPqr(int $organizacionId, int $copropiedadId): void
    {
        DB::table('pqrs')->insert([
            'organizacion_id' => $organizacionId,
            'copropiedad_id' => $copropiedadId,
            'asunto' => 'Contexto inválido',
            'descripcion' => 'Debe rechazarse.',
            'fecha_radicacion' => now()->toDateString(),
            'estado' => 'radicada',
            'user_id' => User::factory()->create()->id,
            'tipo_pqr_id' => TipoPqr::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
