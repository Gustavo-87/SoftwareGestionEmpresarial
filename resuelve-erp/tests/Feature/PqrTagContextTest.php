<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrTagContextTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_relacion_directa_lazy_y_eager_son_equivalentes_y_escalan_sin_n_mas_uno(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        [$otherOrg, $otherCop] = $this->createInstitutionalContext();
        $pqrs = Pqr::factory()->count(3)->paraContexto($org, $cop)->create();
        $tags = PqrTag::factory()->count(3)->paraContexto($org, $cop)->create();
        $external = PqrTag::factory()->paraContexto($otherOrg, $otherCop)->create();

        foreach ($pqrs as $index => $pqr) {
            $pqr->tags()->syncWithPivotValues([$tags[$index]->id], [
                'organizacion_id' => $org->id,
                'copropiedad_id' => $cop->id,
            ]);
        }

        $direct = $pqrs->mapWithKeys(fn (Pqr $pqr) => [$pqr->id => $pqr->tags()->pluck('pqr_tags.id')->all()]);
        $lazy = $pqrs->mapWithKeys(fn (Pqr $pqr) => [$pqr->id => $pqr->fresh()->tags->pluck('id')->all()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $eagerModels = Pqr::query()->whereKey($pqrs->modelKeys())->with('tags')->get();
        $eagerQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $eager = $eagerModels->mapWithKeys(fn (Pqr $pqr) => [$pqr->id => $pqr->tags->pluck('id')->all()]);

        $this->assertSame($direct->all(), $lazy->all());
        $this->assertSame($direct->all(), $eager->all());
        $this->assertSame(2, $eagerQueryCount);
        $this->assertFalse($eager->flatten()->contains($external->id));
        foreach ($pqrs as $index => $pqr) {
            $this->assertSame([$tags[$index]->id], $eager->get($pqr->id));
        }
    }

    public function test_tags_belong_to_a_valid_institutional_context_and_pqrs(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $tag = PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create();

        $pqr->tags()->syncWithPivotValues([$tag->id], [
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
        ]);

        $this->assertTrue($tag->organizacion->is($organizacion));
        $this->assertTrue($tag->copropiedad->is($copropiedad));
        $this->assertTrue($pqr->tags->first()->is($tag));
        $this->assertTrue($tag->pqrs->first()->is($pqr));
    }

    public function test_same_name_is_allowed_in_different_copropiedades_but_not_in_the_same_one(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);

        PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create(['name' => 'Urgente']);
        PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create(['name' => 'Urgente']);
        $this->assertDatabaseCount('pqr_tags', 2);

        $this->expectException(QueryException::class);
        PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create(['name' => 'Urgente']);
    }

    public function test_tools_and_pqr_show_only_expose_tags_from_active_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', [
            'gestion.herramientas_gestionar',
            'pqrs.ver_todas',
            'pqrs.gestionar',
        ]);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        PqrTag::factory()->paraContexto($organizacion, $copropiedad)->create(['name' => 'Etiqueta local']);
        PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create(['name' => 'Etiqueta externa']);

        $this->actingAs($admin)->get(route('management.tools'))
            ->assertOk()
            ->assertSee('Etiqueta local')
            ->assertDontSee('Etiqueta externa');
        $this->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Etiqueta local')
            ->assertDontSee('Etiqueta externa');
    }

    public function test_external_and_unknown_tags_are_rejected_equally_when_syncing(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $externa = PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->actingAs($admin)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$externa->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('tags.0');
        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [999999]])
            ->assertRedirect()
            ->assertSessionHasErrors('tags.0');
        $this->assertDatabaseCount('pqr_pqr_tag', 0);
    }

    public function test_composite_foreign_keys_reject_a_cross_context_association(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create();
        $tag = PqrTag::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();

        $this->expectException(QueryException::class);
        DB::table('pqr_pqr_tag')->insert([
            'pqr_id' => $pqr->id,
            'pqr_tag_id' => $tag->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
        ]);
    }
}
