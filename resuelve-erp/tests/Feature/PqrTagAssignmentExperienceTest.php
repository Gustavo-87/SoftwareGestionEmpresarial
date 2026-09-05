<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrTagAssignmentExperienceTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_admin_gestor_y_apoyo_efectivamente_autorizado_ven_el_control(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        foreach (['admin', 'gestor', 'apoyo'] as $rol) {
            $actor = User::factory()->create(['role' => $rol]);
            $this->createContextualIdentity($actor, $org, $cop, $rol, ['pqrs.ver_todas', 'pqrs.gestionar']);
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['assigned_to_id' => $rol === 'apoyo' ? $actor->id : null]);
            PqrTag::factory()->paraContexto($org, $cop)->create(['name' => "Etiqueta {$rol}"]);

            $this->actingAsContextual($actor)->get(route('pqrs.show', $pqr))->assertOk()
                ->assertSee('Selecciona las etiquetas de esta PQRS')
                ->assertSee("Etiqueta {$rol}")
                ->assertSee('Guardar reemplaza la selección actual.')
                ->assertSee('data-tag-assignment', false);
        }
    }

    public function test_apoyo_puede_gestionar_una_pqrs_sin_responsable(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $this->createContextualIdentity($apoyo, $org, $cop, 'apoyo', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['assigned_to_id' => null]);
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create();

        $this->actingAsContextual($apoyo)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$tag->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $tag->id]);
    }

    public function test_autorizacion_ocurre_antes_de_validar_y_oculta_controles_a_actores_sin_gestion_efectiva(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $otro = User::factory()->create(['role' => 'apoyo']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['assigned_to_id' => $otro->id]);

        foreach ([['apoyo', ['pqrs.ver_todas', 'pqrs.gestionar']], ['residente', ['pqrs.ver_todas']], ['auditor', ['pqrs.ver_todas']], ['gestor', ['pqrs.ver_todas']]] as [$rol, $permisos]) {
            $actor = User::factory()->create(['role' => $rol]);
            $this->createContextualIdentity($actor, $org, $cop, $rol, $permisos);
            $this->actingAsContextual($actor)->get(route('pqrs.show', $pqr))->assertOk()->assertDontSee('data-tag-assignment', false)->assertDontSee('Selecciona las etiquetas');
            $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => 'payload-invalido'])->assertForbidden()->assertSessionHasNoErrors();
        }
    }

    public function test_no_autenticado_csrf_invalido_y_recurso_externo_conservan_contratos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        [$otherOrg, $otherCop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $external = Pqr::factory()->paraContexto($otherOrg, $otherCop)->create();

        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => []])->assertRedirect(route('login'));
        $this->actingAsContextual($manager)->patch(route('pqrs.tags.sync', $external), ['tags' => []])->assertNotFound();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withMiddleware(ValidateCsrfToken::class)->patch(route('pqrs.tags.sync', $pqr), ['tags' => []])->assertStatus(419);
    }

    public function test_asigna_retira_vacia_normaliza_duplicados_y_registra_una_actividad_por_cambio(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        [$one, $two] = PqrTag::factory()->count(2)->paraContexto($org, $cop)->create()->all();

        $this->actingAsContextual($manager)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$one->id, $one->id, $two->id]])->assertSessionHas('success', 'Etiquetas actualizadas.');
        $this->assertDatabaseCount('pqr_pqr_tag', 2);
        $this->assertDatabaseCount('pqr_activities', 1);

        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$two->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $one->id]);
        $this->assertDatabaseCount('pqr_activities', 2);

        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pqr_pqr_tag', 0);
        $this->assertDatabaseCount('pqr_activities', 3);
    }

    public function test_seleccion_identica_es_noop_sin_pivote_ni_actividad_nueva(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create();
        $pqr->tags()->syncWithPivotValues([$tag->id], ['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id]);
        $pivot = $pqr->tags()->first()->pivot;

        $this->actingAsContextual($manager)->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$tag->id, $tag->id]])->assertSessionHas('success', 'Etiquetas actualizadas.');

        $this->assertDatabaseCount('pqr_pqr_tag', 1);
        $this->assertDatabaseCount('pqr_activities', 0);
        $this->assertSame($pivot->created_at?->toDateTimeString(), $pqr->fresh()->tags()->first()->pivot->created_at?->toDateTimeString());
    }

    public function test_etiqueta_asignada_aparece_marcada_como_seleccion_guardada(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create(['name' => 'Etiqueta asignada']);
        $pqr->tags()->syncWithPivotValues([$tag->id], ['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id]);

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))->assertOk()
            ->assertSee('Etiqueta asignada')
            ->assertSee('Selección guardada: 1 etiqueta.')
            ->assertSee('data-originally-checked="true"', false)
            ->assertSee('checked', false)
            ->assertSee('data-tag-restore disabled', false);
    }

    public function test_id_externo_o_inexistente_falla_atomicamente_sin_revelar_diferencias(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        [$otherOrg, $otherCop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $local = PqrTag::factory()->paraContexto($org, $cop)->create();
        $external = PqrTag::factory()->paraContexto($otherOrg, $otherCop)->create();
        $pqr->tags()->syncWithPivotValues([$local->id], ['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id]);

        foreach ([$external->id, 999999] as $invalid) {
            $this->actingAsContextual($manager)->from(route('pqrs.show', $pqr))->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$invalid]])
                ->assertRedirect(route('pqrs.show', $pqr))->assertSessionHasErrors(['tags.0' => 'La etiqueta seleccionada no es válida.']);
            $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $local->id]);
            $this->assertDatabaseCount('pqr_activities', 0);
        }
    }

    public function test_presenta_error_asociado_estado_vacio_nombres_largos_y_muchas_etiquetas(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('No hay etiquetas disponibles')->assertDontSee('Guardar etiquetas');

        PqrTag::factory()->count(12)->paraContexto($org, $cop)->create();
        $long = PqrTag::factory()->paraContexto($org, $cop)->create(['name' => str_repeat('E', 60)]);
        $this->followingRedirects()->from(route('pqrs.show', $pqr))->patch(route('pqrs.tags.sync', $pqr), ['tags' => ['invalida']])->assertOk()
            ->assertSee('No fue posible guardar las etiquetas.')
            ->assertSee('La etiqueta seleccionada no es válida.')
            ->assertSee('id="tag-errors" role="alert" tabindex="-1" data-tag-error', false)
            ->assertSee('aria-describedby="tag-help tag-errors"', false)
            ->assertSee($long->name)
            ->assertSee('data-tag-restore', false)
            ->assertSee('data-originally-checked', false);
    }
}
