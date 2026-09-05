<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrTagCatalogAdministrationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_migracion_modelo_y_rutas_conservan_contrato_sin_eliminacion(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create();
        $this->assertTrue($tag->fresh()->activo);
        $this->assertIsBool($tag->fresh()->activo);
        $this->assertFalse($tag->isFillable('activo'));
        $this->assertTrue(Schema::hasColumn('pqr_tags', 'activo'));
        $this->assertStringContainsString('pqr_tags_context_activo_index', collect(Schema::getIndexes('pqr_tags'))->pluck('name')->join(','));
        $this->assertFalse(collect(app('router')->getRoutes())->contains(fn ($route) => $route->getName() === 'management.tags.destroy'));
    }

    public function test_migracion_revierte_y_reaplica_solo_su_estructura_en_sqlite(): void
    {
        $migration = require database_path('migrations/2026_08_13_000000_add_activo_to_pqr_tags.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pqr_tags', 'activo'));
        try {
            $migration->up();
        } finally {
            if (! Schema::hasColumn('pqr_tags', 'activo')) $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('pqr_tags', 'activo'));
        $this->assertStringContainsString('pqr_tags_context_activo_index', collect(Schema::getIndexes('pqr_tags'))->pluck('name')->join(','));
    }

    public function test_crea_normalizando_nombre_color_y_audita_un_solo_evento_contextual(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['gestion.herramientas_gestionar']);

        $this->actingAsContextual($admin)->post(route('management.tags.store'), ['name' => "  Atención   común  ", 'color' => '#aabbcc', 'organizacion_id' => 999])->assertRedirect()->assertSessionHas('success');

        $tag = PqrTag::query()->sole();
        $this->assertSame('Atención común', $tag->name);
        $this->assertSame('#AABBCC', $tag->color);
        $this->assertSame($org->id, $tag->organizacion_id);
        $this->assertSame($cop->id, $tag->copropiedad_id);
        $log = AuditLog::query()->sole();
        $this->assertSame('etiqueta.creada', $log->action);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($org->id, $log->metadata['organizacion_id']);
    }

    public function test_valida_vacio_maximo_color_y_nombre_reservado_por_inactiva(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['gestion.herramientas_gestionar']);
        PqrTag::factory()->paraContexto($org, $cop)->create(['name' => 'Urgente', 'activo' => false]);

        foreach ([['   ', '#AABBCC', 'name'], [str_repeat('Á', 61), '#AABBCC', 'name'], ['Nueva', 'red', 'color'], ['Urgente', '#112233', 'name']] as [$name, $color, $error]) {
            $this->actingAsContextual($admin)->post(route('management.tags.store'), compact('name', 'color'))->assertSessionHasErrors($error);
        }
        $this->assertDatabaseCount('pqr_tags', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_edita_nombre_color_ambos_y_noop_no_escribe_auditoria(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['gestion.herramientas_gestionar']);
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create(['name' => 'Inicial', 'color' => '#112233']);

        $this->actingAsContextual($admin)->patch(route('management.tags.update', $tag), ['name' => '  Nueva   gráfica ', 'color' => '#abcdef'])->assertSessionHas('success');
        $this->assertDatabaseHas('pqr_tags', ['id' => $tag->id, 'name' => 'Nueva gráfica', 'color' => '#ABCDEF']);
        $this->assertSame(['nombre', 'color'], AuditLog::query()->sole()->metadata['campos']);
        $this->patch(route('management.tags.update', $tag), ['name' => 'Nueva gráfica', 'color' => '#ABCDEF'])->assertSessionHas('info');
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_desactiva_reactiva_y_repeticiones_son_noop_con_eventos_exactos(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['gestion.herramientas_gestionar']);
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create();

        $this->actingAsContextual($gestor)->patch(route('management.tags.status', $tag), ['activo' => 0])->assertSessionHas('success');
        $this->patch(route('management.tags.status', $tag), ['activo' => 0])->assertSessionHas('info');
        $this->patch(route('management.tags.status', $tag), ['activo' => 1])->assertSessionHas('success');
        $this->patch(route('management.tags.status', $tag), ['activo' => 1])->assertSessionHas('info');
        $this->assertTrue($tag->fresh()->activo);
        $this->assertSame(['etiqueta.desactivada', 'etiqueta.reactivada'], AuditLog::query()->pluck('action')->all());
    }

    public function test_autoriza_antes_de_validar_y_resuelve_recurso_contextual_sin_idor(): void
    {
        [$org, $cop] = $this->createInstitutionalContext(); [$otherOrg, $otherCop] = $this->createInstitutionalContext();
        $external = PqrTag::factory()->paraContexto($otherOrg, $otherCop)->create();
        foreach (['apoyo', 'auditor', 'residente'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->createContextualIdentity($actor, $org, $cop, $role, []);
            $this->actingAsContextual($actor)->patch(route('management.tags.update', $external), ['name' => '', 'color' => 'bad'])->assertForbidden()->assertSessionHasNoErrors();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['gestion.herramientas_gestionar']);
        $this->actingAsContextual($admin)->patch(route('management.tags.update', $external), ['name' => '', 'color' => 'bad'])->assertNotFound();
        $this->get(route('management.tools'))->assertOk()->assertDontSee($external->name);
    }

    public function test_contratos_no_autenticado_y_csrf_invalido(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create();
        $this->patch(route('management.tags.status', $tag), ['activo' => 0])->assertRedirect(route('login'));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['gestion.herramientas_gestionar']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAsContextual($admin)->withMiddleware(ValidateCsrfToken::class)->patch(route('management.tags.status', $tag), ['activo' => 0])->assertStatus(419);
    }

    public function test_inactiva_asignada_se_conserva_y_retira_pero_no_puede_agregarse_nuevamente(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar', 'gestion.herramientas_gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create();
        $tag = PqrTag::factory()->paraContexto($org, $cop)->create(['activo' => false]);
        $pqr->tags()->syncWithPivotValues([$tag->id], ['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id]);

        $this->actingAsContextual($gestor)->get(route('pqrs.show', $pqr))->assertOk()->assertSee('Desactivada')->assertSee('checked', false);
        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$tag->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pqr_activities', 0);
        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => []])->assertSessionHasNoErrors();
        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$tag->id]])->assertSessionHasErrors('tags.0');
        $this->assertDatabaseMissing('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $tag->id]);
        $this->patch(route('management.tags.status', $tag), ['activo' => 1])->assertSessionHas('success');
        $this->patch(route('pqrs.tags.sync', $pqr), ['tags' => [$tag->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pqr_pqr_tag', ['pqr_id' => $pqr->id, 'pqr_tag_id' => $tag->id]);
    }
}
