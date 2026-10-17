<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\Mantenimiento;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class ReportIndexTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_global_admin_without_export_permission_is_denied(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $org, $property, 'lector', ['pqrs.listar']);
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_export_permission_does_not_replace_pqrs_authorization(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'exportador', ['informes.exportar']);
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_index_scopes_data_and_keeps_export_filters(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otherProperty = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar']);
        $visible = Pqr::factory()->paraContexto($org, $property)->create(['estado' => 'radicada']);
        Pqr::factory()->paraContexto($org, $property)->create(['estado' => 'cerrada']);
        Pqr::factory()->paraContexto($otherOrg, $otherProperty)->create(['estado' => 'radicada']);
        $filters = ['estado' => 'radicada', 'copropiedad_id' => $otherProperty->id];

        $this->actingAs($user)->get(route('reports.index', $filters))
            ->assertOk()->assertViewIs('reports.index')
            ->assertViewHas('rows', fn ($rows) => $rows->modelKeys() === [$visible->id])
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1)
            ->assertSee('Centro de Reportes')
            ->assertSee(route('reports.pdf', $filters));
    }

    public function test_resident_only_sees_own_records_and_empty_state(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'residente', ['pqrs.listar', 'pqrs.ver_propias', 'informes.exportar']);
        $own = Pqr::factory()->paraContexto($org, $property)->create(['user_id' => $user->id, 'estado' => 'radicada']);
        Pqr::factory()->paraContexto($org, $property)->create();

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->modelKeys() === [$own->id]);
        $this->get(route('reports.index', ['estado' => 'cerrada']))
            ->assertOk()->assertSee('No hay PQRS para los filtros aplicados.');
    }

    public function test_center_presents_four_sections_and_only_implemented_downloads(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'informes.exportar',
            'mantenimiento.ver_todas', 'documentos.consultar',
        ]);
        Pqr::factory()->paraContexto($org, $property)->create(['estado' => 'radicada']);
        Mantenimiento::forceCreate([
            'organizacion_id' => $org->id, 'copropiedad_id' => $property->id,
            'solicitante_id' => $user->id, 'titulo' => 'Solicitud', 'descripcion' => 'x',
            'estado' => 'pendiente', 'fecha_programada' => null,
        ]);
        Documento::factory()->paraContexto($org, $property)->create();

        $html = $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('id="reportes-pqrs"', false)
            ->assertSee('id="reportes-mantenimiento"', false)
            ->assertSee('id="reportes-documentos"', false)
            ->assertSee('id="reportes-consolidado"', false)
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['total'] === 1)
            ->assertViewHas('documentos', fn ($resumen) => $resumen['total'] === 1 && $resumen['activos'] === 1 && $resumen['archivados'] === 0)
            ->assertViewHas('consolidado', fn ($resumen) => $resumen['total'] === 3 && count($resumen['modulos']) === 3)
            ->assertSee('Descargar PDF')
            ->getContent();

        $this->assertSame(3, substr_count($html, 'Descargar PDF'));
        $this->assertSame(1, substr_count($html, 'Descargar Excel'));
        $this->assertSame(1, substr_count($html, 'Descargar CSV'));
        $this->assertSame(5, substr_count($html, 'Descargar'));
    }

    public function test_sections_without_module_permissions_show_restricted_message(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar']);
        Pqr::factory()->paraContexto($org, $property)->create();

        $html = $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen === null)
            ->assertViewHas('documentos', fn ($resumen) => $resumen === null)
            ->assertViewHas('consolidado', fn ($resumen) => $resumen['total'] === 1 && count($resumen['modulos']) === 1)
            ->getContent();

        $this->assertSame(2, substr_count($html, 'No tienes permisos para consultar los indicadores de este módulo.'));
    }

    public function test_mantenimiento_indicators_follow_contextual_visibility(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $otro = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'apoyo', ['pqrs.listar', 'pqrs.ver_propias', 'informes.exportar', 'mantenimiento.ver_propias']);
        $crear = fn (int $solicitante) => Mantenimiento::forceCreate([
            'organizacion_id' => $org->id, 'copropiedad_id' => $property->id,
            'solicitante_id' => $solicitante, 'titulo' => 'Solicitud', 'descripcion' => 'x',
            'estado' => 'pendiente', 'fecha_programada' => null,
        ]);
        $crear($user->id);
        $crear($otro->id);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['total'] === 1 && $resumen['pendientes'] === 1);
    }

    public function test_documentos_indicators_follow_module_access(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'lector', ['pqrs.listar', 'pqrs.ver_propias', 'informes.exportar', 'documentos.consultar']);
        Documento::factory()->paraContexto($org, $property)->create(['estado' => 'activo']);
        Documento::factory()->paraContexto($org, $property)->create(['estado' => 'archivado']);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('documentos', fn ($resumen) => $resumen['total'] === 2 && $resumen['activos'] === 1 && $resumen['archivados'] === 1);
    }

    public function test_tools_page_offers_reports_center_entry(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['gestion.herramientas_gestionar', 'informes.exportar']);

        $this->actingAs($user)->get(route('management.tools'))
            ->assertOk()
            ->assertSee('Reportes e Indicadores')
            ->assertSee(route('reports.index'));
    }

    public function test_tools_page_hides_reports_entry_without_export_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['gestion.herramientas_gestionar']);

        $this->actingAs($user)->get(route('management.tools'))
            ->assertOk()
            ->assertDontSee('Reportes e Indicadores');
    }
}
