<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ContextualizarEtiquetasPqrsCommandTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('pqr_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
        Schema::table('pqr_pqr_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
    }

    public function test_contextualizes_empty_legacy_tags_and_associations_idempotently(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $tagId = DB::table('pqr_tags')->insertGetId(['name' => 'Urgente', 'color' => '#ff0000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pqr_pqr_tag')->insert(['pqr_id' => $pqr->id, 'pqr_tag_id' => $tagId]);

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutput('Etiquetas actualizadas: 1')
            ->expectsOutput('Asociaciones actualizadas: 1')
            ->expectsOutput('Inconsistencias: 0')
            ->assertSuccessful();

        $this->assertDatabaseHas('pqr_tags', ['id' => $tagId, 'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id]);
        $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $tagId, 'organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id]);
        $before = DB::table('pqr_pqr_tag')->first();

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutput('Etiquetas existentes: 1')
            ->expectsOutput('Asociaciones existentes: 1')
            ->assertSuccessful();
        $this->assertEquals($before, DB::table('pqr_pqr_tag')->first());
    }

    public function test_empty_backfill_is_successful(): void
    {
        $this->createInstitutionalContext();

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutput('Etiquetas examinadas: 0')
            ->expectsOutput('Asociaciones examinadas: 0')
            ->assertSuccessful();
    }

    public function test_rejects_partial_tag_context_without_writing_anything(): void
    {
        [$organizacion] = $this->createInstitutionalContext();
        $tagId = DB::table('pqr_tags')->insertGetId(['name' => 'Parcial', 'color' => '#ff0000', 'organizacion_id' => $organizacion->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutputToContain("La etiqueta {$tagId} tiene un contexto parcial")
            ->assertFailed();
        $this->assertNull(DB::table('pqr_tags')->where('id', $tagId)->value('copropiedad_id'));
    }

    public function test_rejects_crossed_legacy_association_and_rolls_back(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $otraOrganizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $pqr = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();
        $tagId = DB::table('pqr_tags')->insertGetId(['name' => 'Inicial', 'color' => '#ff0000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pqr_pqr_tag')->insert(['pqr_id' => $pqr->id, 'pqr_tag_id' => $tagId]);

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutputToContain("La asociación PQR {$pqr->id} / etiqueta {$tagId} cruza contextos")
            ->assertFailed();
        $this->assertNull(DB::table('pqr_tags')->where('id', $tagId)->value('organizacion_id'));
        $this->assertNull(DB::table('pqr_pqr_tag')->where('pqr_id', $pqr->id)->value('organizacion_id'));
    }

    public function test_rejects_missing_institutional_context(): void
    {
        SiteSetting::query()->delete();

        $this->artisan('resuelve:contextualizar-etiquetas-pqrs')
            ->expectsOutputToContain('SiteSetting no está contextualizado')
            ->assertFailed();
    }

    public function test_context_closure_refuses_to_make_columns_required_when_nulls_remain(): void
    {
        DB::table('pqr_tags')->insert([
            'name' => 'Sin contexto',
            'color' => '#ff0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_07_31_140100_make_pqr_tag_context_required.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('existen etiquetas o asociaciones sin contexto completo');
        $migration->up();
    }
}
