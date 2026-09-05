<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ContextualizarPqrsCommandTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('pqrs', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizacion_id')->nullable()->change();
            $table->unsignedBigInteger('copropiedad_id')->nullable()->change();
        });
    }

    public function test_contextualizes_empty_pqrs_and_preserves_functional_fields(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->sinContexto()->create([
            'asunto' => 'Asunto original',
            'descripcion' => 'Descripción original',
            'estado' => 'en_revision',
        ]);
        $before = $pqr->only([
            'asunto', 'descripcion', 'estado', 'user_id', 'assigned_to_id',
            'tipo_pqr_id', 'fecha_radicacion', 'fecha_limite_respuesta',
            'created_at', 'updated_at',
        ]);

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutput('Contextualizadas: 1')
            ->expectsOutput('Existentes: 0')
            ->expectsOutput('Inconsistencias: 0')
            ->assertSuccessful();

        $pqr->refresh();
        $this->assertSame($organizacion->id, $pqr->organizacion_id);
        $this->assertSame($copropiedad->id, $pqr->copropiedad_id);
        $this->assertEquals($before, $pqr->only(array_keys($before)));
    }

    public function test_second_execution_is_idempotent_and_reports_existing_pqrs(): void
    {
        $this->createInstitutionalContext();
        Pqr::factory()->sinContexto()->count(2)->create();

        $this->artisan('resuelve:contextualizar-pqrs')->assertSuccessful();
        $before = DB::table('pqrs')->orderBy('id')->get()->toArray();

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutput('Contextualizadas: 0')
            ->expectsOutput('Existentes: 2')
            ->expectsOutput('Inconsistencias: 0')
            ->assertSuccessful();

        $this->assertEquals($before, DB::table('pqrs')->orderBy('id')->get()->toArray());
    }

    public function test_rejects_missing_site_setting(): void
    {
        Pqr::factory()->sinContexto()->create();
        SiteSetting::query()->delete();

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain('No existe una fila persistida de site_settings')
            ->assertFailed();

        $this->assertDatabaseMissing('pqrs', ['organizacion_id' => 1]);
    }

    public function test_rejects_site_setting_without_context(): void
    {
        SiteSetting::create([
            'nombre_conjunto' => 'Sin contexto',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        Pqr::factory()->sinContexto()->create();

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain('SiteSetting no está contextualizado')
            ->assertFailed();

        $this->assertSame(1, Pqr::query()->whereNull('organizacion_id')->count());
    }

    public function test_rejects_nonexistent_institutional_context(): void
    {
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Contexto inválido',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $this->withoutForeignKeys(function () use ($settings): void {
            DB::table('site_settings')->where('id', $settings->id)->update([
                'organizacion_id' => 999998,
                'copropiedad_id' => 999999,
            ]);
        });
        Pqr::factory()->sinContexto()->create();

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain('La Organización 999998 no existe')
            ->assertFailed();

        $this->assertSame(1, Pqr::query()->whereNull('organizacion_id')->count());
    }

    public function test_rejects_partial_pqr_context_and_rolls_back_every_pqr(): void
    {
        [$organizacion] = $this->createInstitutionalContext();
        $pendiente = Pqr::factory()->sinContexto()->create();
        $parcial = Pqr::factory()->sinContexto()->create();
        DB::table('pqrs')->where('id', $parcial->id)->update([
            'organizacion_id' => $organizacion->id,
        ]);

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain("La PQR {$parcial->id} tiene un contexto parcial")
            ->expectsOutput('Inconsistencias: 1')
            ->assertFailed();

        $this->assertNull($pendiente->fresh()->organizacion_id);
        $this->assertNull($pendiente->fresh()->copropiedad_id);
    }

    public function test_rejects_nonexistent_pqr_references(): void
    {
        $this->createInstitutionalContext();
        $pqr = Pqr::factory()->sinContexto()->create();
        $this->withoutForeignKeys(function () use ($pqr): void {
            DB::table('pqrs')->where('id', $pqr->id)->update([
                'organizacion_id' => 999998,
                'copropiedad_id' => 999999,
            ]);
        });

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain("La PQR {$pqr->id} contiene referencias contextuales inexistentes")
            ->assertFailed();
    }

    public function test_rejects_crossed_context_references(): void
    {
        [$organizacion] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra',
            'estado' => 'activa',
        ]);
        $pqr = Pqr::factory()->sinContexto()->create();
        $this->withoutForeignKeys(function () use ($pqr, $organizacion, $otraCopropiedad): void {
            DB::table('pqrs')->where('id', $pqr->id)->update([
                'organizacion_id' => $organizacion->id,
                'copropiedad_id' => $otraCopropiedad->id,
            ]);
        });

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutputToContain("La PQR {$pqr->id} contiene referencias contextuales cruzadas")
            ->assertFailed();
    }

    public function test_does_not_modify_an_already_contextualized_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $before = DB::table('pqrs')->where('id', $pqr->id)->first();

        $this->artisan('resuelve:contextualizar-pqrs')
            ->expectsOutput('Contextualizadas: 0')
            ->expectsOutput('Existentes: 1')
            ->assertSuccessful();

        $this->assertEquals($before, DB::table('pqrs')->where('id', $pqr->id)->first());
    }

    private function withoutForeignKeys(callable $callback): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
            $callback();

            return;
        }

        Schema::disableForeignKeyConstraints();
        try {
            $callback();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
